<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class AdminBeautifyOAuth_Widget extends Widget_Abstract_Users
{
    private $referer = '';

    public function oauth()
    {
        if (!AdminBeautifyOAuth_Plugin::isAdminBeautifyReady()) {
            $this->redirectLoginWithNotice('未检测到 AdminBeautify，OAuth 代理已禁用。');
        }

        $slotKey = strtolower(trim((string)$this->request->get('type')));
        $providers = AdminBeautifyOAuth_Plugin::options('', true);
        if ($slotKey === '' || !isset($providers[$slotKey])) {
            $this->redirectLoginWithNotice('未启用该 OAuth 平台。');
        }
        $actualType = isset($providers[$slotKey]['_type']) ? (string)$providers[$slotKey]['_type'] : $slotKey;

        $this->startSession();
        $redirect = trim((string)$this->request->get('redirect'));
        if ($redirect === '') {
            $redirect = (string)$this->request->getReferer();
        }
        if ($this->isSameSiteUrl($redirect)) {
            $_SESSION['ab_oauth_referer'] = $redirect;
        } else {
            $_SESSION['ab_oauth_referer'] = Typecho_Common::url('profile.php', $this->options->adminUrl);
        }
        $_SESSION['ab_oauth_type'] = $slotKey;

        require_once __DIR__ . '/ThinkOauth.php';
        $sdk = ABOAuthThinkOauth::getInstance($slotKey, null, $actualType);
        if (!$sdk) {
            $this->redirectLoginWithNotice('OAuth SDK 不支持该平台。');
        }

        $this->response->redirect($sdk->getRequestCodeURL($slotKey));
    }

    public function callback()
    {
        if (!AdminBeautifyOAuth_Plugin::isAdminBeautifyReady()) {
            $this->redirectLoginWithNotice('未检测到 AdminBeautify，OAuth 代理已禁用。');
        }

        $this->startSession();
        $this->referer = isset($_SESSION['ab_oauth_referer']) ? (string)$_SESSION['ab_oauth_referer'] : '';
        $sessionType = isset($_SESSION['ab_oauth_type']) ? (string)$_SESSION['ab_oauth_type'] : '';
        unset($_SESSION['ab_oauth_referer']);
        unset($_SESSION['ab_oauth_type']);

        $slotKey = $this->resolveCallbackType($sessionType);
        $code = trim((string)$this->request->get('code'));
        $providers = AdminBeautifyOAuth_Plugin::options('', true);
        $slotKey = $this->resolveAvailableProviderType($slotKey, $sessionType, $providers);
        if ($slotKey === '' || $code === '' || !isset($providers[$slotKey])) {
            $this->redirectLoginWithNotice('OAuth 回调参数无效。');
        }
        $actualType = isset($providers[$slotKey]['_type']) ? (string)$providers[$slotKey]['_type'] : $slotKey;

        require_once __DIR__ . '/ThinkOauth.php';

        try {
            if (class_exists('AdminBeautifyOAuth_Plugin') && AdminBeautifyOAuth_Plugin::isRainbowType($actualType)) {
                require_once __DIR__ . '/sdk/RainbowSDK.class.php';
                $state = trim((string)$this->request->get('state'));
                if (!RainbowSDK::verifyCallbackState($state)) {
                    throw new Exception('彩虹聚合登录状态校验失败，请重试');
                }
            }

            $sdk = ABOAuthThinkOauth::getInstance($slotKey, null, $actualType);
            $token = $sdk->getAccessToken($slotKey, $code);
            if (!is_array($token) || empty($token['openid'])) {
                throw new Exception('未获取到 openid');
            }

            $userInfo = $this->fetchUserInfo($actualType, $token);
            $oauthUser = array(
                'type' => substr($actualType, 0, 32),
                'openid' => substr((string)$token['openid'], 0, 100),
                'nickname' => substr((string)(isset($userInfo['nickname']) ? $userInfo['nickname'] : $token['openid']), 0, 100),
                'avatar' => substr((string)(isset($userInfo['head_img']) ? $userInfo['head_img'] : ''), 0, 255),
                'access_token' => isset($token['access_token']) ? (string)$token['access_token'] : '',
                'refresh_token' => isset($token['refresh_token']) ? (string)$token['refresh_token'] : '',
                'expires_in' => isset($token['expires_in']) ? (int)$token['expires_in'] : 0,
            );

            if ($this->user->hasLogin()) {
                $this->bindUser((int)$this->user->uid, $oauthUser);
                $this->response->redirect(Typecho_Common::url('profile.php?abOAuth=bound&type=' . rawurlencode($actualType), $this->options->adminUrl));
            }

            $bound = $this->findBound($oauthUser['type'], $oauthUser['openid']);
            if (!empty($bound) && $this->hasUser((int)$bound['uid'])) {
                $this->useUidLogin((int)$bound['uid'], 0, $oauthUser['type']);
                $target = $this->referer !== '' ? $this->referer : Typecho_Common::url('index.php', $this->options->adminUrl);
                $this->response->redirect($target);
            }

            $this->response->redirect(Typecho_Common::url('/ab-oauth-missing?type=' . rawurlencode($actualType), $this->options->index));
        } catch (Exception $e) {
            $this->redirectLoginWithNotice('OAuth 登录失败：' . $e->getMessage());
        }
    }

    public function toggle()
    {
        if (!AdminBeautifyOAuth_Plugin::isAdminBeautifyReady()) {
            $this->redirectLoginWithNotice('未检测到 AdminBeautify，OAuth 代理已禁用。');
        }

        if (!$this->user->hasLogin()) {
            $this->redirectLoginWithNotice('请先登录。');
        }

        $slotKey = strtolower(trim((string)$this->request->get('type')));
        $action = strtolower(trim((string)$this->request->get('action')));
        $providers = AdminBeautifyOAuth_Plugin::options('', true);
        if ($slotKey === '' || !isset($providers[$slotKey])) {
            $this->response->redirect(Typecho_Common::url('profile.php', $this->options->adminUrl));
        }
        $actualType = isset($providers[$slotKey]['_type']) ? (string)$providers[$slotKey]['_type'] : $slotKey;

        if ($action === 'unbind') {
            $this->db->query(
                $this->db->delete('table.' . AdminBeautifyOAuth_Plugin::TABLE_NAME)
                    ->where('uid = ?', (int)$this->user->uid)
                    ->where('type = ?', $actualType)
            );
            $this->response->redirect(Typecho_Common::url('profile.php?abOAuth=unbound&type=' . rawurlencode($actualType), $this->options->adminUrl));
        }

        if ($action === 'bind') {
            $this->response->redirect(Typecho_Common::url('/ab-oauth?type=' . rawurlencode($slotKey) . '&redirect=' . rawurlencode(Typecho_Common::url('profile.php', $this->options->adminUrl)), $this->options->index));
        }

        $this->response->redirect(Typecho_Common::url('profile.php', $this->options->adminUrl));
    }

    public function missing()
    {
        $this->render('missing.php');
    }

    protected function fetchUserInfo($type, $token)
    {
        $userInfo = array(
            'nickname' => isset($token['openid']) ? $token['openid'] : 'oauth-user',
            'head_img' => '',
        );

        if (!method_exists($this, $type)) {
            if (class_exists('AdminBeautifyOAuth_Plugin') && AdminBeautifyOAuth_Plugin::isRainbowType($type)) {
                if (!empty($token['nickname'])) $userInfo['nickname'] = $token['nickname'];
                if (!empty($token['faceimg'])) $userInfo['head_img'] = $token['faceimg'];
                elseif (!empty($token['head_img'])) $userInfo['head_img'] = $token['head_img'];
                return $userInfo;
            }
            return $userInfo;
        }

        $res = $this->$type($token);
        if (is_array($res)) {
            if (!empty($res['nickname'])) $userInfo['nickname'] = $res['nickname'];
            if (!empty($res['head_img'])) $userInfo['head_img'] = $res['head_img'];
        }
        return $userInfo;
    }

    protected function bindUser($uid, array $oauthUser)
    {
        $now = (int)$this->options->gmtTime;

        $existsOther = $this->db->fetchRow(
            $this->db->select()
                ->from('table.' . AdminBeautifyOAuth_Plugin::TABLE_NAME)
                ->where('type = ?', $oauthUser['type'])
                ->where('openid = ?', $oauthUser['openid'])
                ->limit(1)
        );
        if (!empty($existsOther) && (int)$existsOther['uid'] !== (int)$uid) {
            throw new Exception('该第三方账号已绑定到其他用户');
        }

        $existsByType = $this->db->fetchRow(
            $this->db->select()
                ->from('table.' . AdminBeautifyOAuth_Plugin::TABLE_NAME)
                ->where('uid = ?', (int)$uid)
                ->where('type = ?', $oauthUser['type'])
                ->limit(1)
        );

        $data = array(
            'uid' => (int)$uid,
            'type' => $oauthUser['type'],
            'openid' => $oauthUser['openid'],
            'nickname' => $oauthUser['nickname'],
            'avatar' => $oauthUser['avatar'],
            'access_token' => $oauthUser['access_token'],
            'refresh_token' => $oauthUser['refresh_token'],
            'expires_in' => $oauthUser['expires_in'] > 0 ? ($now + $oauthUser['expires_in']) : 0,
            'updated' => $now,
        );

        if (!empty($existsByType)) {
            $this->db->query(
                $this->db->update('table.' . AdminBeautifyOAuth_Plugin::TABLE_NAME)
                    ->rows($data)
                    ->where('id = ?', (int)$existsByType['id'])
            );
        } else {
            $data['created'] = $now;
            $this->db->query($this->db->insert('table.' . AdminBeautifyOAuth_Plugin::TABLE_NAME)->rows($data));
        }
    }

    protected function findBound($type, $openid)
    {
        return $this->db->fetchRow(
            $this->db->select()
                ->from('table.' . AdminBeautifyOAuth_Plugin::TABLE_NAME)
                ->where('type = ?', $type)
                ->where('openid = ?', $openid)
                ->limit(1)
        );
    }

    protected function hasUser($uid)
    {
        $user = $this->db->fetchRow(
            $this->db->select()
                ->from('table.users')
                ->where('uid = ?', (int)$uid)
                ->limit(1)
        );
        return !empty($user);
    }

    protected function useUidLogin($uid, $expire = 0, $type = '')
    {
        $authCode = function_exists('openssl_random_pseudo_bytes') ?
            bin2hex(openssl_random_pseudo_bytes(16)) :
            sha1(Typecho_Common::randString(20));

        Typecho_Cookie::set('__typecho_uid', $uid, $expire);
        Typecho_Cookie::set('__typecho_authCode', Typecho_Common::hash($authCode), $expire);

        $this->db->query($this->db
            ->update('table.users')
            ->expression('logged', 'activated')
            ->rows(array('authCode' => $authCode))
            ->where('uid = ?', (int)$uid)
        );

        if ($type !== '') {
            $this->db->query($this->db
                ->update('table.' . AdminBeautifyOAuth_Plugin::TABLE_NAME)
                ->rows(array('updated' => (int)$this->options->gmtTime))
                ->where('uid = ?', (int)$uid)
                ->where('type = ?', $type)
            );
        }
    }

    protected function render($file)
    {
        if (!is_file(__DIR__ . '/' . $file)) {
            Typecho_Common::error(500);
        }
        require_once __DIR__ . '/' . $file;
    }

    private function startSession()
    {
        if (isset($_COOKIE[session_name()]) && $_COOKIE[session_name()]) {
            session_id($_COOKIE[session_name()]);
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params(3600);
            session_start();
        }
    }

    private function isSameSiteUrl($url)
    {
        if ($url === '') return false;
        return strpos($url, $this->options->index) === 0 || strpos($url, $this->options->adminUrl) === 0;
    }

    private function resolveCallbackType($sessionType = '')
    {
        $type = strtolower(trim((string)$this->request->get('type')));
        if ($type !== '') {
            return $type;
        }

        $path = trim((string)$this->request->getPathInfo());
        if ($path !== '' && preg_match('#/ab-oauth-callback/([^/?]+)#i', $path, $m)) {
            $fromPath = strtolower(trim(rawurldecode($m[1])));
            if ($fromPath !== '') {
                return $fromPath;
            }
        }

        return strtolower(trim((string)$sessionType));
    }

    private function resolveAvailableProviderType($type, $sessionType, array $providers)
    {
        $type = strtolower(trim((string)$type));
        $sessionType = strtolower(trim((string)$sessionType));

        if ($type !== '' && isset($providers[$type])) {
            return $type;
        }

        if (class_exists('AdminBeautifyOAuth_Plugin') && method_exists('AdminBeautifyOAuth_Plugin', 'normalizeRainbowLoginType')) {
            $normalized = (string) AdminBeautifyOAuth_Plugin::normalizeRainbowLoginType($type);
            if ($normalized !== '' && isset($providers[$normalized])) {
                return $normalized;
            }
        }

        if ($sessionType !== '' && isset($providers[$sessionType])) {
            if ($type === '') {
                return $sessionType;
            }

            if (class_exists('AdminBeautifyOAuth_Plugin') && method_exists('AdminBeautifyOAuth_Plugin', 'rainbowApiLoginType')) {
                $sessionApiType = (string) AdminBeautifyOAuth_Plugin::rainbowApiLoginType($sessionType);
                if ($sessionApiType !== '' && $sessionApiType === $type) {
                    return $sessionType;
                }
            }
        }

        return $type;
    }

    private function redirectLoginWithNotice($msg)
    {
        try {
            $this->widget('Widget_Notice')->set(array($msg), 'error');
        } catch (Exception $e) {
        }
        $this->response->redirect(Typecho_Common::url('login.php', $this->options->adminUrl));
        exit;
    }

    public function qq($token)
    {
        $qq = ABOAuthThinkOauth::getInstance('qq', $token);
        $data = $qq->call('user/get_user_info');
        if (!empty($data) && isset($data['ret']) && $data['ret'] == 0) {
            return array('nickname' => $data['nickname'], 'head_img' => $data['figureurl_2']);
        }
        return array();
    }

    public function wechat($token)
    {
        $sdk = ABOAuthThinkOauth::getInstance('wechat', $token);
        $data = $sdk->call('sns/userinfo');
        if (!empty($data) && empty($data['errcode'])) {
            return array('nickname' => $data['nickname'], 'head_img' => $data['headimgurl']);
        }
        return array();
    }

    public function github($token)
    {
        $sdk = ABOAuthThinkOauth::getInstance('github', $token);
        $data = $sdk->call('user');
        if (!empty($data) && !empty($data['id'])) {
            return array('nickname' => !empty($data['name']) ? $data['name'] : $data['login'], 'head_img' => $data['avatar_url']);
        }
        return array();
    }

    public function google($token)
    {
        $sdk = ABOAuthThinkOauth::getInstance('google', $token);
        $data = $sdk->call('userinfo');
        if (!empty($data) && !empty($data['id'])) {
            return array('nickname' => $data['name'], 'head_img' => !empty($data['picture']) ? $data['picture'] : '');
        }
        return array();
    }

    public function msn($token)
    {
        $sdk = ABOAuthThinkOauth::getInstance('msn', $token);
        $data = $sdk->call('me');
        if (!empty($data) && !empty($data['id'])) {
            $name = '';
            if (!empty($data['displayName'])) {
                $name = $data['displayName'];
            } elseif (!empty($data['name'])) {
                $name = $data['name'];
            } elseif (!empty($data['userPrincipalName'])) {
                $name = $data['userPrincipalName'];
            } elseif (!empty($data['mail'])) {
                $name = $data['mail'];
            }
            return array('nickname' => $name !== '' ? $name : $data['id'], 'head_img' => '');
        }
        return array();
    }

    public function sina($token)
    {
        $sdk = ABOAuthThinkOauth::getInstance('sina', $token);
        $data = $sdk->call('users/show', 'uid=' . $sdk->openid());
        if (!empty($data) && empty($data['error_code'])) {
            return array('nickname' => $data['screen_name'], 'head_img' => $data['avatar_large']);
        }
        return array();
    }

    public function douban($token)
    {
        $sdk = ABOAuthThinkOauth::getInstance('douban', $token);
        $data = $sdk->call('user/~me');
        if (!empty($data) && empty($data['code'])) {
            return array('nickname' => $data['name'], 'head_img' => !empty($data['avatar']) ? $data['avatar'] : '');
        }
        return array();
    }

    public function diandian($token)
    {
        $sdk = ABOAuthThinkOauth::getInstance('diandian', $token);
        $data = $sdk->call('user/info');
        if (!empty($data['meta']['status']) && $data['meta']['status'] == 200) {
            return array('nickname' => $data['response']['name'], 'head_img' => '');
        }
        return array();
    }

    public function taobao($token)
    {
        $sdk = ABOAuthThinkOauth::getInstance('taobao', $token);
        $fields = 'user_id,nick,avatar';
        $data = $sdk->call('taobao.user.buyer.get', 'fields=' . $fields);
        if (!empty($data['user_buyer_get_response']['user'])) {
            $user = $data['user_buyer_get_response']['user'];
            return array('nickname' => $user['nick'], 'head_img' => !empty($user['avatar']) ? $user['avatar'] : '');
        }
        return array();
    }

    public function baidu($token)
    {
        $sdk = ABOAuthThinkOauth::getInstance('baidu', $token);
        $data = $sdk->call('passport/users/getLoggedInUser');
        if (!empty($data['uid'])) {
            return array('nickname' => $data['uname'], 'head_img' => '');
        }
        return array();
    }

    public function customlogin($token)
    {
        $sdk = ABOAuthThinkOauth::getInstance('customlogin', $token);
        $data = $sdk->getUserInfo();
        if (!empty($data['sub'])) {
            return array(
                'nickname' => !empty($data['name']) ? $data['name'] : (!empty($data['preferred_username']) ? $data['preferred_username'] : $data['sub']),
                'head_img' => !empty($data['picture']) ? $data['picture'] : ''
            );
        }
        return array();
    }
}

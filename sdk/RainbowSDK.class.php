<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

class RainbowSDK
{
    private const API_BASE_DEFAULT = 'https://u.cccyun.cc/';
    private const SESSION_STATE_KEY = 'ab_oauth_rainbow_state';

    private array $token = array();
    private string $slotKey = '';

    public function __construct($token = null, $slotKey = null)
    {
        $this->token = is_array($token) ? $token : array();
        $this->slotKey = ($slotKey !== null && $slotKey !== '') ? strtolower(trim((string)$slotKey)) : '';
    }

    public function getRequestCodeURL($type)
    {
        $slotKey = $this->slotKey !== '' ? $this->slotKey : strtolower(trim((string)$type));
        $config = AdminBeautifyOAuth_Plugin::options($slotKey);
        $actualType = isset($config['_type']) ? (string)$config['_type'] : $slotKey;
        $apiType = $this->resolveApiType($actualType);
        if ($apiType === '') {
            throw new Exception('彩虹登录类型无效：' . $actualType);
        }
        if (empty($config['id']) || empty($config['key'])) {
            throw new Exception('请配置 ' . $type . ' 的 APPID 和 APP Key');
        }
        $apiUrl = $this->buildApiUrl(isset($config['apiBase']) ? (string)$config['apiBase'] : '');

        $callback = Typecho_Common::url('/ab-oauth-callback/' . rawurlencode($slotKey), Typecho_Widget::widget('Widget_Options')->index);
        $state = $this->createState();
        $params = array(
            'act' => 'login',
            'appid' => $config['id'],
            'appkey' => $config['key'],
            'type' => $apiType,
            'redirect_uri' => $callback,
            'state' => $state,
        );

        $result = $this->request($apiUrl . '?' . http_build_query($params));
        $data = json_decode($result, true);
        if (!is_array($data)) {
            throw new Exception('彩虹聚合登录返回数据解析失败');
        }
        if (!isset($data['code']) || (int)$data['code'] !== 0 || empty($data['url'])) {
            $msg = isset($data['msg']) ? (string)$data['msg'] : '获取登录地址失败';
            throw new Exception($msg);
        }

        return (string)$data['url'];
    }

    public function getAccessToken($type, $code, $extend = null)
    {
        $slotKey = $this->slotKey !== '' ? $this->slotKey : strtolower(trim((string)$type));
        $config = AdminBeautifyOAuth_Plugin::options($slotKey);
        $actualType = isset($config['_type']) ? (string)$config['_type'] : $slotKey;
        $apiType = $this->resolveApiType($actualType);
        if ($apiType === '') {
            throw new Exception('彩虹登录类型无效：' . $actualType);
        }
        if (empty($config['id']) || empty($config['key'])) {
            throw new Exception('请配置 ' . $type . ' 的 APPID 和 APP Key');
        }
        $apiUrl = $this->buildApiUrl(isset($config['apiBase']) ? (string)$config['apiBase'] : '');

        $params = array(
            'act' => 'callback',
            'appid' => $config['id'],
            'appkey' => $config['key'],
            'type' => $apiType,
            'code' => $code,
        );

        $result = $this->request($apiUrl . '?' . http_build_query($params));
        $data = json_decode($result, true);
        if (!is_array($data)) {
            throw new Exception('彩虹聚合登录返回数据解析失败');
        }
        if (!isset($data['code']) || (int)$data['code'] !== 0) {
            $msg = isset($data['msg']) ? (string)$data['msg'] : '登录失败';
            throw new Exception($msg);
        }

        $data['type'] = $actualType;
        $data['openid'] = isset($data['social_uid']) ? (string)$data['social_uid'] : '';
        $data['head_img'] = isset($data['faceimg']) ? (string)$data['faceimg'] : '';
        $this->token = $data;
        return $data;
    }

    public function query($type, $social_uid)
    {
        $slotKey = $this->slotKey !== '' ? $this->slotKey : strtolower(trim((string)$type));
        $config = AdminBeautifyOAuth_Plugin::options($slotKey);
        $actualType = isset($config['_type']) ? (string)$config['_type'] : $slotKey;
        $apiType = $this->resolveApiType($actualType);
        if ($apiType === '') {
            throw new Exception('彩虹登录类型无效：' . $actualType);
        }
        if (empty($config['id']) || empty($config['key'])) {
            throw new Exception('请配置 ' . $type . ' 的 APPID 和 APP Key');
        }
        $apiUrl = $this->buildApiUrl(isset($config['apiBase']) ? (string)$config['apiBase'] : '');

        $params = array(
            'act' => 'query',
            'appid' => $config['id'],
            'appkey' => $config['key'],
            'type' => $apiType,
            'social_uid' => $social_uid,
        );

        $result = $this->request($apiUrl . '?' . http_build_query($params));
        $data = json_decode($result, true);
        return is_array($data) ? $data : array();
    }

    private function buildApiUrl($base)
    {
        $base = trim((string)$base);
        if ($base === '') {
            $base = self::API_BASE_DEFAULT;
        }
        $base = preg_replace('#/connect\.php/?$#i', '', $base);
        if ($base === null || $base === '') {
            $base = self::API_BASE_DEFAULT;
        }
        return rtrim($base, '/') . '/connect.php';
    }

    private function request($url)
    {
        if (!function_exists('curl_init')) {
            throw new Exception('当前环境未启用 curl，无法请求彩虹聚合登录接口');
        }

        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ));
        $data = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('请求彩虹聚合登录接口失败：' . $error);
        }

        return (string)$data;
    }

    public static function verifyCallbackState($state)
    {
        $state = trim((string)$state);
        if ($state === '') {
            return false;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $expected = isset($_SESSION[self::SESSION_STATE_KEY]) ? (string)$_SESSION[self::SESSION_STATE_KEY] : '';
        unset($_SESSION[self::SESSION_STATE_KEY]);
        return $expected !== '' && hash_equals($expected, $state);
    }

    private function createState()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            if (function_exists('random_bytes')) {
                $state = bin2hex(random_bytes(16));
            } else {
                $state = sha1(uniqid((string)mt_rand(), true));
            }
            $_SESSION[self::SESSION_STATE_KEY] = $state;
            return $state;
        }

        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16));
        }
        return sha1(uniqid((string)mt_rand(), true));
    }

    private function resolveApiType($type)
    {
        if (class_exists('AdminBeautifyOAuth_Plugin') && method_exists('AdminBeautifyOAuth_Plugin', 'rainbowApiLoginType')) {
            return (string)AdminBeautifyOAuth_Plugin::rainbowApiLoginType($type);
        }
        return strtolower(trim((string)$type));
    }
}

<?php
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

define('ABOAUTH_CALLBACK_URL', Typecho_Common::url('/ab-oauth-callback?type=', Typecho_Widget::widget('Widget_Options')->index));

return array(
    'THINK_SDK_QQ' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'qq',
    ),
    'THINK_SDK_WECHAT' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'wechat',
    ),
    'THINK_SDK_GITHUB' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'github',
    ),
    'THINK_SDK_MSN' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'msn',
    ),
    'THINK_SDK_GOOGLE' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'google',
    ),
    'THINK_SDK_SINA' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'sina',
    ),
    'THINK_SDK_DOUBAN' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'douban',
    ),
    'THINK_SDK_DIANDIAN' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'diandian',
    ),
    'THINK_SDK_TAOBAO' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'taobao',
    ),
    'THINK_SDK_BAIDU' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'baidu',
    ),
    'THINK_SDK_CUSTOMLOGIN' => array(
        'CALLBACK' => ABOAUTH_CALLBACK_URL . 'customlogin',
        'AUTHORIZE' => 'scope=openid profile email',
    ),
);

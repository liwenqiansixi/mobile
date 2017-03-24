<?php
/**
 * ECTouch Open Source Project
 * ============================================================================
 * Copyright (c) 2012-2015 http://ectouch.cn All rights reserved.
 * ----------------------------------------------------------------------------
 * 文件名称：wechat.php
 * ----------------------------------------------------------------------------
 * 功能描述：wechat登录插件
 * ----------------------------------------------------------------------------
 * Licensed ( http://www.ectouch.cn/docs/license.txt )
 * ----------------------------------------------------------------------------
 */

/* 访问控制 */
defined('IN_ECTOUCH') or die('Deny Access');

$payment_lang = LANG_PATH  . C('shop.lang') . '/connect/' . basename(__FILE__);

if (file_exists($payment_lang)) {
    include_once ($payment_lang);
    L($_LANG);
}
/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE) {
    $i = isset($modules) ? count($modules) : 0;
    /* 类名 */
    $modules[$i]['name'] = 'Wechat';
    // 文件名，不包含后缀
    $modules[$i]['type'] = 'wechat';

    $modules[$i]['className'] = 'wechat';
    // 作者信息
    $modules[$i]['author'] = 'ECTouch';

    // 作者QQ
    $modules[$i]['qq'] = '800007167';

    // 作者邮箱
    $modules[$i]['email'] = 'support@ecmoban.com';

    // 申请网址
    $modules[$i]['website'] = 'http://mp.weixin.qq.com';

    // 版本号
    $modules[$i]['version'] = '1.0';

    // 更新日期
    $modules[$i]['date'] = '2014-10-03';

    /* 配置信息 */
    $modules[$i]['config'] = array(
        array('type' => 'text', 'name' => 'app_id', 'value' => ''),
        array('type' => 'text', 'name' => 'app_secret', 'value' => ''),
        array('type' => 'radio', 'name' => 'oauth_status', 'value' => '0'),
    );
    return;
}

class wechat
{

    private $wechat = '';

    /**
     * 构造函数
     *
     * @param unknown $config
     */
    public function __construct($config)
    {
        $options = array(
            'appid' => $config['app_id'],
            'appsecret' => $config['app_secret'],
        );
        $this->wechat = new \ectouch\Wechat($options);
    }

    /**
     * 获取授权地址
     */
    public function redirect($callback_url, $state = 'wechat_oauth', $snsapi = 'snsapi_userinfo')
    {
        if (is_dir(APP_WECHAT_PATH) && is_wechat_browser() && isset($_COOKIE['ectouch_ru_id'])){
            $snsapi = 'snsapi_base'; // 静默授权
        }
        return $this->wechat->getOauthRedirect($callback_url, $state, $snsapi);
    }

    /**
     * 回调用户数据
     */
    public function callback($callback_url, $code)
    {
        if (!empty($code)) {
            $token = $this->wechat->getOauthAccessToken();
            $userinfo = $this->wechat->getOauthUserinfo($token['access_token'], $token['openid']);
            if (!empty($userinfo)) {
                 // 对昵称有特殊字符进行替换
                include('emoji.php');
                $userinfo['nickname'] = strip_tags(emoji_unified_to_html($userinfo['nickname']));//过滤emoji表情产生的html标签
                $_SESSION['openid'] = $userinfo['openid'];
                $_SESSION['nickname'] = $userinfo['nickname'];
                $_SESSION['avatar'] = $userinfo['headimgurl'];
                $identify = (isset($userinfo['unionid']) && !empty($userinfo['unionid'])) ? $userinfo['unionid'] : $userinfo['openid'];
                $data = array(
                    'openid' => $identify,
                    'name' => $userinfo['nickname'],
                    'sex' => $userinfo['sex'],
                    'avatar' => $userinfo['headimgurl']
                );
                //更新粉丝信息
                if (is_dir(APP_WECHAT_PATH) && is_wechat_browser()) $this->updateInfo($userinfo);
                return $data;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 粉丝信息更新
     */
    private function updateInfo($res = array())
    {
        if (empty($res)) {
            return false;
        }
        // 移除用户特权数据
        $res['privilege'] = serialize($res['privilege']);
        $unionid = false;
        if (isset($res['unionid']) && !empty($res['unionid'])) {
            $userinfo = dao('wechat_user')->where(array('unionid' => $res['unionid']))->find();
            $unionid = true;
        } else {
            $userinfo = dao('wechat_user')->where(array('openid' => $res['openid']))->find();
        }
        if (empty($userinfo)) {
            $res['ect_uid'] = 0;
            $res['wechat_id'] = 1;
            dao('wechat_user')->data($res)->add();
        } else {
            if ($unionid) {
                $condition = array('unionid' => $res['unionid']);
            } else {
                $condition = array('openid' => $res['openid']);
            }
            dao('wechat_user')->data($res)->where($condition)->save();
        }
    }
}

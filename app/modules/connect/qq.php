<?php

/**
 * ECTouch Open Source Project
 * ============================================================================
 * Copyright (c) 2012-2014 http://ectouch.cn All rights reserved.
 * ----------------------------------------------------------------------------
 * 文件名称：qq.php
 * ----------------------------------------------------------------------------
 * 功能描述：腾讯qq登录插件
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
    $modules[$i]['name'] = 'QQ';
    // 文件名，不包含后缀
    $modules[$i]['type'] = 'qq';

    $modules[$i]['className'] = 'qq';
    // 作者信息
    $modules[$i]['author'] = 'ECTouch';

    // 作者QQ
    $modules[$i]['qq'] = '800007167';

    // 作者邮箱
    $modules[$i]['email'] = 'support@ecmoban.com';

    // 申请网址
    $modules[$i]['website'] = 'http://open.qq.com';

    // 版本号
    $modules[$i]['version'] = '1.0';

    // 更新日期
    $modules[$i]['date'] = '2016-01-10';
    /* 配置信息 */
    $modules[$i]['config'] = array(
        array('type' => 'text', 'name' => 'app_id', 'value' => ''),
        array('type' => 'text', 'name' => 'app_key', 'value' => ''),
    );
    return;
}

class qq
{

    public $api_url = 'https://graph.qq.com/';
    private $appid = '';
    private $appkey = '';
    private $access_token = '';
    private $scope = '';

    /**
     * 构造函数
     *
     * @param unknown $config
     * @param unknown $access_token
     */
    public function __construct($config, $access_token = NULL)
    {
        $this->appid = $config['app_id'];
        $this->appkey = $config['app_key'];
        $this->access_token = $access_token;
        $this->scope = 'get_user_info, add_share';
    }

    /**
     * 获取授权地址
     *
     * @param unknown $info
     * @param unknown $url
     * @return mixed
     */
    public function redirect($callback_url)
    {
        return $this->login_url($callback_url, $this->scope);
    }

    /**
     * 回调用户数据
     *
     * @param unknown $info
     * @param unknown $url
     * @param unknown $code
     * @return boolean
     */
    public function callback($callback_url, $code)
    {
        $result = $this->access_token($callback_url, $code);
        if (isset($result['access_token']) && $result['access_token'] != '') {
            $this->access_token = $result['access_token'];
            $openid = $this->get_openid();
            // 获取用户信息
            $userinfo = $this->get_user_info($openid);
            if($userinfo['gender'] == '男'){
                $userinfo['gender'] = 1;
            }elseif ($userinfo['gender'] == '女'){
                $userinfo['gender'] = 2;
            }else {
                $userinfo['gender'] = 0;
            }
            $_SESSION['nickname'] = $this->get_user_name($userinfo);
            $_SESSION['avatar'] = $userinfo['figureurl_qq_2'] ? $userinfo['figureurl_qq_2'] : $userinfo['figureurl_qq_1'];
            $data = array(
                'openid' => $openid,
                'name' => $this->get_user_name($userinfo),
                'sex' => $userinfo['gender'],
                'avatar' => $userinfo['figureurl_qq_2'] ? $userinfo['figureurl_qq_2'] : $userinfo['figureurl_qq_1']
            );
            return $data;
        } else {
            // echo "授权失败";
            return false;
        }
    }

    /**
     * 生成授权网址
     *
     * @param unknown $callback_url
     * @param string $scope
     * @return string
     */
    public function login_url($callback_url, $scope = '')
    {
        $params = array(
            'client_id' => $this->appid,
            'redirect_uri' => $callback_url,
            'response_type' => 'code',
            'scope' => $scope
        );
        return 'https://graph.qq.com/oauth2.0/authorize?' . http_build_query($params, '', '&');
    }

    /**
     * 获取access token
     *
     * @param unknown $callback_url
     * @param unknown $code
     * @return multitype:
     */
    public function access_token($callback_url, $code)
    {
        $params = array(
            'grant_type' => 'authorization_code',
            'client_id' => $this->appid,
            'client_secret' => $this->appkey,
            'code' => $code,
            'state' => '',
            'redirect_uri' => $callback_url
        );
        $url = 'https://graph.qq.com/oauth2.0/token?' . http_build_query($params, '', '&');
        $result_str = $this->http($url);
        $json_r = array();
        if ($result_str != '')
            parse_str($result_str, $json_r);
        return $json_r;
    }

    /**
     * 获取登录用户的openid
     *
     * @return Ambigous <>
     */
    public function get_openid()
    {
        $params = array(
            'access_token' => $this->access_token
        );
        $url = 'https://graph.qq.com/oauth2.0/me?' . http_build_query($params, '', '&');
        $result_str = $this->http($url);
        $json_r = array();
        if ($result_str != '') {
            preg_match('/callback\(\s+(.*?)\s+\)/i', $result_str, $result_a);
            $json_r = json_decode($result_a[1], true);
        }
        return $json_r['openid'];
    }

    /**
     * 根据openid获取用户信息
     *
     * @param unknown $openid
     * @return Ambigous <multitype:, mixed>
     */
    public function get_user_info($openid)
    {
        $params = array(
            'openid' => $openid
        );
        return $this->api('user/get_user_info', $params);
    }

    /**
     * 获取用户名
     *
     * @param unknown $user_info
     * @return Ambigous <multitype:, mixed>
     */
    public function get_user_name($userinfo)
    {

        return $userinfo['nickname'];

    }

    /**
     * 发布分享
     *
     * @param unknown $openid
     * @param unknown $title
     * @param unknown $url
     * @param unknown $site
     * @param unknown $fromurl
     * @param string $images
     * @param string $summary
     * @return Ambigous <multitype:, mixed>
     */
    public function add_share($openid, $title, $url, $site, $fromurl, $images = '', $summary = '')
    {
        $params = array(
            'openid' => $openid,
            'title' => $title,
            'url' => $url,
            'site' => $site,
            'fromurl' => $fromurl,
            'images' => $images,
            'summary' => $summary
        );
        return $this->api('share/add_share', $params, 'POST');
    }

    /**
     * 调用接口
     *
     * @param unknown $url
     * @param unknown $params
     * @param string $method
     * @return Ambigous <multitype:, mixed>
     *         //示例：根据openid获取用户信息
     *         $result=$qq->api('user/get_user_info', array('openid'=>$openid), 'GET');
     */
    public function api($url, $params = array(), $method = 'GET')
    {
        $url = $this->api_url . $url;
        $params['access_token'] = $this->access_token;
        $params['oauth_consumer_key'] = $this->appid;
        $params['format'] = 'json';
        if ($method == 'GET') {
            $query_url = $url . '?' . http_build_query($params, '', '&');
            $result_str = $this->http($query_url);
        } else {
            $query = http_build_query($params, '', '&');
            $result_str = $this->http($url, $query, 'POST');
        }
        $result = array();
        if ($result_str != '')
            $result = json_decode($result_str, true);
        return $result;
    }

    /**
     * 提交请求
     *
     * @param unknown $url
     * @param string $postfields
     * @param string $method
     * @param unknown $headers
     * @return mixed
     */
    private function http($url, $postfields = '', $method = 'GET', $headers = array())
    {
        $ci = curl_init();
        curl_setopt($ci, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ci, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ci, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ci, CURLOPT_TIMEOUT, 30);
        if ($method == 'POST') {
            curl_setopt($ci, CURLOPT_POST, TRUE);
            if ($postfields != '')
                curl_setopt($ci, CURLOPT_POSTFIELDS, $postfields);
        }
        $headers[] = 'User-Agent: ECTouch.cn';
        curl_setopt($ci, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ci, CURLOPT_URL, $url);
        $response = curl_exec($ci);
        curl_close($ci);
        return $response;
    }

}

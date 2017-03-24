<?php
namespace app\http\base\controllers;

use Think\Hook;
use app\classes\Ecshop;
use app\classes\Mysql;
use app\classes\Error;

abstract class Frontend extends Foundation
{
    public $province_id = 0;
    public $city_id = 0;
    public $district_id = 0;
    public $caching = false;
    public $custom = '';
    public $customs = '';

    public function __construct()
    {
        parent::__construct();
        $this->start();
        // 当前城市
        $this->geocoding();
        //ecjia验证登录
        $this->ecjia_login();
        // app验证登录
        $this->app_login();
    }

    /**
     * 根据ip地址获取当前城市信息并定位
     */
    private function geocoding()
    {
        $current_city_id = cookie('lbs_city');
        $current_city_info = get_region_name(intval($current_city_id));
        if (empty($current_city_info)) {
            // 请求API
            $res_city_name = $this->getApiCityName();
            // 获取城市信息
            $sql = "select `region_id`, `region_name`, `parent_id` from " . $GLOBALS['ecs']->table('region') .
                " where region_type = 2 and region_name = '{$res_city_name}'";
            $current_city_info = $GLOBALS['db']->getRow($sql);
            // 默认商店地区
            if (empty($current_city_info)) {
                $current_city_info = get_region_name(C('shop.shop_city'));
            }
            // 保存cookie
            setrawcookie('lbs_city_name', rtrim($current_city_info['region_name'], '市'));
            cookie('lbs_city', $current_city_info['region_id']);
            cookie('province', $current_city_info['parent_id']);
            cookie('city', $current_city_info['region_id']);
            cookie('district', 0);
        }
        $this->assign('current_city', $current_city_info);
    }

    /**
     * ecjia验证登录
     *&origin=app&openid=openid&token=token
     */
    private function ecjia_login()
    {
        if (isset($_GET['origin']) && $_GET['origin'] == 'app') {
            $openid = I('get.openid');
            $token = I('get.token');
            $sql= "select cu.token,u.user_name from {pre}connect_user as cu LEFT JOIN {pre}users as u on cu.user_id = u.user_id where open_id = '$openid' ";
            $user = $this->db->getRow($sql);
            if ($token == $user['token']) {
                /* 设置成登录状态 */
                $GLOBALS['user']->set_session($user['user_name']);
                $GLOBALS['user']->set_cookie($user['user_name']);

            }
        }

    }

    /**
     * app端验证登录 开发
     * @param mobile=13391365859&status=1&sign=6214e6ec6b85696d9ecfc0559eeb0def
     *  mobile=13391365859&status=0&sign=7c293cf8cb29acf663b96b9d3c997de1
     */
    private function app_login()
    {
        if (isset($_GET['mobile']) && isset($_GET['sign'])) {
            $mobile = I('get.mobile', '', 'trim');
            $status = I('get.status', 0, 'intval');
            $get_sign = I('get.sign', '', 'trim');

            //验证签名
            $key = 'onetoall';
            if(!empty($mobile)){
                $sign = md5(md5($mobile.$status).$key);
            }
            if ($get_sign && $sign === $get_sign) {
                // 手机号为唯一条件  用户名作为手机号 或者填写了手机号
                $condition['user_name'] = $mobile;
                $condition['mobile_phone'] = $mobile;
                $condition['_logic'] = 'OR';
                $user_name = dao('users')->where($condition)->getField('user_name');
                if($user_name){
                    $_SESSION['user_status'] = $status; // 记录用户身份状态信息
                    /* 设置成登录状态 */
                    $GLOBALS['user']->set_session($user_name);
                    $GLOBALS['user']->set_cookie($user_name);
                    $this->redirect('/');
                }
            }
        }
    }

    private function start()
    {
        $this->init();
        $this->init_user();
        $this->init_gzip();
        $this->init_assign();
        $this->init_area();
        $ru_id = get_ru_id();
        if($ru_id > 0){
            $wechat = '\\app\\http\\wechat\\controllers\\Index';
            $wechat::snsapi_base($ru_id);
        }else{
           $this->init_oauth();
        }
        Hook::listen('frontend_init');
        $this->assign('lang', array_change_key_case(L()));
        $this->assign('charset', CHARSET);
    }

    /**
     * 应用程序初始化
     * @access public
     * @return void
     */
    private function init()
    {
        // 加载helper文件
        $helper_list = array('time', 'base', 'common', 'main', 'insert', 'goods');
        $this->load_helper($helper_list);
        // 全局对象
        $this->ecs = $GLOBALS['ecs'] = new Ecshop(config('DB_NAME'), config('DB_PREFIX'));
        $this->db = $GLOBALS['db'] = new Mysql();
        $this->err = $GLOBALS['err'] = new Error('message');
        // 全局配置
        $GLOBALS['_CFG'] = load_ecsconfig();
        $GLOBALS['_CFG']['template'] = 'default';
        $GLOBALS['_CFG']['rewrite'] = 0;
        // config('URL_MODEL', 2);
        config('shop', $GLOBALS['_CFG']);
        // 应用配置
        $app_config = MODULE_BASE_PATH . 'config/web.php';
        config('app', file_exists($app_config) ? require $app_config : array());
        // 全局语言包
        L(require(LANG_PATH . config('shop.lang') . '/common.php'));
        // 应用模块语言包
        $app_lang = MODULE_BASE_PATH . 'language/' . config('shop.lang') . '/' . strtolower(MODULE_NAME) . '.php';
        L(file_exists($app_lang) ? require $app_lang : array());
        // 控制器语言包
        $app_lang = MODULE_BASE_PATH . 'language/' . config('shop.lang') . '/' . strtolower(CONTROLLER_NAME) . '.php';
        L(file_exists($app_lang) ? require $app_lang : array());
        // 应用helper文件
        $this->load_helper('function', 'app');
        // 商店关闭了，输出关闭的消息
        if (config('shop_closed') == 1) {
            exit('<p>' . L('shop_closed') . '</p><p>' . config('close_comment') . '</p>');
        }
        // 定义session_id
        if (!defined('INIT_NO_USERS')) {
            session(array('name' => 'ECS_ID'));
            session('[start]');
            define('SESS_ID', real_cart_mac_ip());
        }
        //加载商创helper文件
        $schelper_list = array('scecmoban', 'scfunction');
        $this->load_helper($schelper_list);
    }

    private function init_user()
    {
        if (!defined('INIT_NO_USERS')) {
            // 会员信息
            $GLOBALS['user'] = $this->users = init_users();
            if (!isset($_SESSION['user_id'])) {
                /* 获取投放站点的名称 */
                $site_name = isset($_GET['from']) ? htmlspecialchars($_GET['from']) : addslashes(L('self_site'));
                $from_ad = !empty($_GET['ad_id']) ? intval($_GET['ad_id']) : 0;

                $wechat_from = array('timeline', 'groupmessage', 'singlemessage');//如果在微信分享链接，referer为touch
                if (in_array($site_name, $wechat_from)) {
                    $site_name = addslashes(L('self_site'));
                }
                $_SESSION['from_ad'] = $from_ad; // 用户点击的广告ID
                $_SESSION['referer'] = stripslashes($site_name); // 用户来源

                unset($site_name);

                if (!defined('INGORE_VISIT_STATS')) {
                    visit_stats();
                }
            }

            if (empty($_SESSION['user_id'])) {
                if ($this->users->get_cookie()) {
                    /* 如果会员已经登录并且还没有获得会员的帐户余额、积分以及优惠券 */
                    if ($_SESSION['user_id'] > 0) {
                        update_user_info();
                    }
                } else {
                    $_SESSION['user_id'] = 0;
                    $_SESSION['user_name'] = '';
                    $_SESSION['email'] = '';
                    $_SESSION['user_rank'] = 0;
                    $_SESSION['discount'] = 1.00;
                    if (!isset($_SESSION['login_fail'])) {
                        $_SESSION['login_fail'] = 0;
                    }
                }
            }

            // 设置推荐会员
            if (isset($_GET['u'])) {
                set_affiliate();
            }

            // 设置商家ID cookie
            if (isset($_GET['ru_id'])) {
                set_ru_id();
            }

            // session 不存在，检查cookie
            if (!empty($_COOKIE['ECS']['user_id']) && !empty($_COOKIE['ECS']['password'])) {
                // 找到了cookie, 验证cookie信息
                $condition = array(
                    'user_id' => intval($_COOKIE['ECS']['user_id']),
                    'password' => $_COOKIE['ECS']['password']
                );
                $row = $this->db->table('users')->where($condition)->find();

                if (!$row) {
                    $time = time() - 3600;
                    cookie('ECS[user_id]', '');
                    cookie('ECS[password]', '');
                } else {
                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['user_name'] = $row['user_name'];
                    update_user_info();
                }
            }

            if (isset($this->tpl)) {
                $this->tpl->assign('ecs_session', $_SESSION);
            }
        }
    }

    //映射公用模板的值
    private function init_assign()
    {
        //热搜
        $search_keywords = config('shop.search_keywords');
        $hot_keywords = array();
        if ($search_keywords) {
            $hot_keywords = explode(',', $search_keywords);
        }

        $this->assign('hot_keywords', $hot_keywords);
        $history = '';
        if (!empty($_COOKIE['ECS']['keywords'])) {
            $history = explode(',', $_COOKIE['ECS']['keywords']);
            $history = array_unique($history);  //移除数组中的重复的值，并返回结果数组。
        }
        $this->assign('history_keywords', $history);
        // WXSDK  微信浏览器内访问并安装了微信通
        $is_wechat = (is_wechat_browser() && is_dir(APP_WECHAT_PATH)) ? 1 : 0;
        $this->assign('is_wechat', $is_wechat);

        $user_status = (isset($_SESSION['user_status']) && $_SESSION['user_status'] == 1) ? 1 : 0;
        $this->assign('user_status', $user_status);
    }

    /**
     * 地区选择
     */
    public function init_area()
    {
        //判断地区关联是否选择完毕 start
        $city_district_list = get_isHas_area($_COOKIE['type_city']);
        if (!$city_district_list) {
            cookie('type_district', 0);
            $_COOKIE['type_district'] = 0;
        }

        $provinceT_list = get_isHas_area($_COOKIE['type_province']);
        $cityT_list = get_isHas_area($_COOKIE['type_city'], 1);
        $districtT_list = get_isHas_area($_COOKIE['type_district'], 1);

        if ($_COOKIE['type_province'] > 0 && $provinceT_list) {
            if ($city_district_list) {
                if ($cityT_list['parent_id'] == $_COOKIE['type_province'] && $_COOKIE['type_city'] == $districtT_list['parent_id']) {
                    $_COOKIE['province'] = $_COOKIE['type_province'];
                    if ($_COOKIE['type_city'] > 0) {
                        $_COOKIE['city'] = $_COOKIE['type_city'];
                    }

                    if ($_COOKIE['type_district'] > 0) {
                        $_COOKIE['district'] = $_COOKIE['type_district'];
                    }
                }
            } else {
                if ($cityT_list['parent_id'] == $_COOKIE['type_province']) {
                    $_COOKIE['province'] = $_COOKIE['type_province'];
                    if ($_COOKIE['type_city'] > 0) {
                        $_COOKIE['city'] = $_COOKIE['type_city'];
                    }

                    if ($_COOKIE['type_district'] > 0) {
                        $_COOKIE['district'] = $_COOKIE['type_district'];
                    }
                }
            }
        }
        //判断地区关联是否选择完毕 end
        $this->province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : 0;
        $this->city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : 0;
        $this->district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : 0;

        //判断仓库是否存在该地区
        $warehouse_date = array('region_id', 'region_name');
        $warehouse_where = "regionId = '$this->province_id'";
        $warehouse_province = get_table_date('region_warehouse', $warehouse_where, $warehouse_date);

        $sellerInfo = get_seller_info_area();
        if (!$warehouse_province) {
            $this->province_id = $sellerInfo['province'];
            $this->city_id = $sellerInfo['city'];
            $this->district_id = $sellerInfo['district'];
        }

        cookie('province', $this->province_id);
        cookie('city', $this->city_id);
        cookie('district', $this->district_id);
    }

    //判断是否支持 Gzip 模式
    private function init_gzip()
    {
        if (!defined('INIT_NO_SMARTY') && gzip_enabled()) {
            ob_start('ob_gzhandler');
        } else {
            ob_start();
        }
    }

    /**
     * 自动授权跳转
     */
    private function init_oauth()
    {
        if (is_wechat_browser() && empty($_SESSION['openid']) && MODULE_NAME != 'oauth') {
            $sql = "SELECT `auth_config` FROM" . $GLOBALS['ecs']->table('touch_auth') . " WHERE `type` = 'wechat' AND `status` = 1";
            $auth_config = $GLOBALS['db']->getOne($sql);
            if ($auth_config) {
                $res = unserialize($auth_config);
                $config = array();
                foreach ($res as $key => $value) {
                    $config[$value['name']] = $value['value'];
                }
                if($config['oauth_status'] == 1){
                    $back_url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                    $this->redirect('oauth/index/index', array('type' => 'wechat', 'back_url' => urlencode($back_url)));
                }
            }
        }
    }

}

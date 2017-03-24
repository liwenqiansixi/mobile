<?php
namespace app\http\base\controllers;

use app\classes\Ecshop;
use app\classes\Mysql;
use app\classes\Error;

abstract class Backend extends Foundation
{

    public function __construct()
    {
        parent::__construct();
        // 加载helper文件
        $helper_list = array('time', 'base', 'common', 'main', 'insert', 'goods');
        $this->load_helper($helper_list);
        // 全局对象
        $this->ecs = $GLOBALS['ecs'] = new Ecshop(config('DB_NAME'), config('DB_PREFIX'));
        $this->db = $GLOBALS['db'] = new Mysql();
        // 同步登录
        if (!defined('INIT_NO_USERS')) {
            session(array('name' => 'ECSCP_ID'));
            session('[start]');
            $condition['sesskey'] = substr(cookie('ECSCP_ID'), 0, 32);
            $session = $this->model->table('sessions')->where($condition)->find();
            $_SESSION = unserialize($session['data']);
            $_SESSION['user_id'] = $session['userid'];
            $_SESSION['admin_id'] = $session['adminid'];
            $_SESSION['user_name'] = $session['user_name'];
            $_SESSION['user_rank'] = $session['user_rank'];
            $_SESSION['discount'] = $session['discount'];
            $_SESSION['email'] = $session['email'];

            define('SESS_ID', substr($session['sesskey'], 0, 32));

            // 商家后台登录
            if(empty($_SESSION['admin_id'])){
                session(array('name' => 'ECSCP_SELLER_ID'));
                session('[start]');
                $condition['sesskey'] = substr(cookie('ECSCP_SELLER_ID'), 0, 32);
                $session_seller = $this->model->table('sessions_data')->where($condition)->find();
                $_SESSION = unserialize($session_seller['data']);
                $_SESSION['user_id'] = 0;
                $_SESSION['admin_id'] = 0;
                $_SESSION['user_name'] = 0;
                $_SESSION['user_rank'] = 0;
                $_SESSION['discount'] = 0;
                $_SESSION['email'] = 0;
            }

        }

        // 全局配置
        $GLOBALS['_CFG'] = load_ecsconfig();
        $GLOBALS['_CFG']['template'] = 'default';
        C('shop', $GLOBALS['_CFG']);

        //验证管理员
        if(isset($_SESSION['admin_id']) && $_SESSION['admin_id'] > 0){
            $this->checkLogin();
        }// 验证商家
        elseif(isset($_SESSION['seller_id']) && $_SESSION['seller_id'] > 0){
            $this->checkSellerLogin();
        }

        // 全局语言包
        L(require(LANG_PATH . C('shop.lang') . '/common.php'));
    }

    /**
     * 操作成功之后跳转,默认三秒钟跳转
     *
     * @param unknown $msg
     * @param unknown $url
     * @param string $type
     * @param number $waitSecond
     */
    public function message($msg, $url = NULL, $type = '1', $seller = false, $waitSecond = 3)
    {
        if ($url == NULL)
            $url = 'javascript:history.back();';
        if ($type == '2') {
            $title = L('error_information');
        } else {
            $title = L('prompt_information');
        }
        $data['title'] = $title;
        $data['message'] = $msg;
        $data['type'] = $type;
        $data['url'] = $url;
        $data['second'] = $waitSecond;
        $this->assign('data', $data);
        $tpl = ($seller == true) ? 'admin/seller_message' : 'admin/message';
        $this->display($tpl);
        exit();
    }

    /**
     * 判断管理员登录
     * @return [type] [description]
     */
    private function checkLogin()
    {
        $condition['user_id'] = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
        $action_list = $this->model->table('admin_user')->where($condition)->getField('action_list');
        // 手机端登录权限校验
        if (empty($action_list)) {
            redirect('../admin/index.php?act=main');
        }

    }
    /**
     * 判断商家管理员登录
     * @return [type] [description]
     */
    private function checkSellerLogin()
    {
        $condition['user_id'] = isset($_SESSION['seller_id']) ? intval($_SESSION['seller_id']) : 0;
        $action_list = $this->model->table('admin_user')->where($condition)->getField('action_list');
        // 手机端登录商家后台权限校验
        if (empty($action_list)) {
            redirect('../seller/privilege.php?act=login');
        }

    }

    /**
     * 判断管理员对某一个操作是否有权限。
     *
     * 根据当前对应的action_code，然后再和用户session里面的action_list做匹配，以此来决定是否可以继续执行。
     * @param     string $priv_str 操作对应的priv_str
     * @param     string $msg_type 返回的类型
     * @return true/false
     */
    public function admin_priv($priv_str)
    {
        $condition['user_id'] = isset($_SESSION['admin_id']) ? intval($_SESSION['admin_id']) : 0;
        $action_list = $this->model->table('admin_user')->where($condition)->getField('action_list');

        if ($action_list == 'all') {
            return true;
        }

        if (strpos(',' . $action_list . ',', ',' . $priv_str . ',') === false) {
            redirect('../admin/index.php?act=main');
        } else {
            return true;
        }
    }

    /**
     * 判断商家管理员对某一个操作是否有权限。
     *
     * 根据当前对应的action_code，然后再和用户session里面的action_list做匹配，以此来决定是否可以继续执行。
     * @param     string $priv_str 操作对应的priv_str
     * @param     string $msg_type 返回的类型
     * @return true/false
     */
    public function seller_admin_priv($priv_str)
    {
        $condition['user_id'] = isset($_SESSION['seller_id']) ? intval($_SESSION['seller_id']) : 0;
        $action_list = $this->model->table('admin_user')->where($condition)->getField('action_list');

        if ($action_list == 'all') {
            return true;
        }

        if (strpos(',' . $action_list . ',', ',' . $priv_str . ',') === false) {
            // redirect('../seller/privilege.php?act=login');
            return true;
        } else {
            return true;
        }
    }
}

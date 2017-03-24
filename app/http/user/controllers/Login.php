<?php
namespace app\http\user\controllers;

use app\http\base\controllers\Frontend;
use Think\Verify;
use ectouch\Form;

class Login extends Frontend
{

    public $user;
    public $user_id;

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH  . C('shop.lang') . '/user.php'));
        $file = array(
            'passport',
            'clips',
        );
        $this->load_helper($file);
        // 属性赋值
        $this->user_id = $_SESSION['user_id'];
    }

    /**
     * 用户登录
     */
    public function actionIndex()
    {
        // 是否为post提交
        if (IS_POST) {
            $username = isset($_POST['username']) ? trim($_POST['username']) : '';
            $password = isset($_POST['password']) ? trim($_POST['password']) : '';
            $back_act = isset($_POST['back_act']) ? trim($_POST['back_act']) : '';
            $back_act = empty($back_act) ? url('user/index/index') : $back_act;
            // 验证
            $form = new Form();
            if ($form->isEmail($username, 1)) {
                $login = $this->db->getOne("SELECT user_name FROM {pre}users WHERE email='$username'");
                if ($login) {
                    $username = $login;
                }
            } else if ($form->isMobile($username, 1)) {
                $login = $this->db->getOne("SELECT user_name FROM {pre}users WHERE mobile_phone='$username'");
                if ($login) {
                    $username = $login;
                }
            }
            if ($this->users->login($username, $password)) {
                update_user_info();
                recalculate_price();
                exit(json_encode(array('status' => 'y', 'info' => L('login_success'), 'url' => $back_act)));
            } else {
                $_SESSION['login_fail']++;
                exit(json_encode(array('status' => 'n', 'info' => L('login_failure'))));
            }

            exit;
        }
        if ($this->user_id > 0) {
            $this->redirect('/user');
        }
        $back_act = input('back_act', '', 'urldecode');
        if (empty($back_act)) {
            if (empty($back_act) && isset($GLOBALS['_SERVER']['HTTP_REFERER'])) {
                $back_act = strpos($GLOBALS['_SERVER']['HTTP_REFERER'], url('user/index/index')) ? url('user/index/index') : $GLOBALS['_SERVER']['HTTP_REFERER'];
            } else {
                $back_act = url('user/index/index');
            }
        }
        $back_act=htmlspecialchars($back_act);
        // 显示社会化登录插件
        $condition = array(
            'status' => 1
        );
        $oauth_list = $this->model->table('touch_auth')->where($condition)->order('sort asc, id asc')->select();
        foreach ($oauth_list as $key => $vo) {
            if ($vo['type'] == 'wechat' && !is_wechat_browser()) {
                unset($oauth_list[$key]);
            }
        }
        $this->assign('oauth_list', $oauth_list);

        $this->assign('back_act', $back_act);
        $this->assign('page_title', L('log_user'));
        $this->assign('passport_js', L('passport_js'));
        $this->display();
    }

    /**
     * 找回密码 填写用户信息页面  处理用户提交
     */
    public function actionGetPassword(){
        if(IS_POST){
            $username = I('post.username');
            $result = array('error' => 0, 'content' => '');
            if(empty($username)){
                $result['error'] = 1;
                $result['content'] = '没有找到用户信息';
                echo json_encode($result);
                exit;
            }

            $userInfo  = $this->getUserInfo($username);//获取用户信息

            if(empty($userInfo)){
                $result['error'] = 1;
                $result['content'] = '没有找到用户信息';
            }else{
                session('forget_user_data', array(
                    'user_id' => $userInfo['user_id'],
                    'email'=>$userInfo['email'],
                    'user_name'=>$userInfo['user_name'],
                    'phone'=>$userInfo['mobile_phone'],
                    'reg_time'=>$userInfo['reg_time'],
                ));

                if(empty($userInfo['email']) && empty($userInfo['mobile_phone'])){
                    $result['error'] = 1;
                    $result['content'] = '没有找到用户信息';
                }else{
                    $result['mail_or_phone'] = (($userInfo['email'] == $username) ? 'email' : (($userInfo['mobile_phone'] == $username) ? 'phone' : ((empty($userInfo['mobile_phone'])) ? 'email' : 'phone')));
                }
            }

            echo json_encode($result);
            exit;
        }
        $this->assign('page_title', L('get_password'));
        $this->display();
    }
    /** 获取用户信息 */
    private function getUserInfo ($username){
        $userInfo = $this->db->getRow("SELECT user_id, email, user_name, mobile_phone, reg_time FROM {pre}users WHERE email = '" . $username . "' OR user_name = '" . $username . "' OR mobile_phone = '" . $username . "'");

        return $userInfo;
    }

    /**
     * 忘记密码
     * 显示手机页面
     * 处理验证码验证
     * 通过则跳转
     */
    public function actionGetPasswordShow()
    {
        if(IS_POST){
            $result = array('error'=>0, 'content'=>'');
            $code = I('code', '');

            if(empty($code)){
                $result['error'] = 1;
                $result['content'] = '验证码不能为空';
            }

            if(session('forget_user_data.verify_str') == md5($code.session('forget_user_data.user_id').session('forget_user_data.reg_time'))){
                $result['error'] = 0;
                $result['content'] = '验证通过';
            }else{
                $result['error'] = 1;
                $result['content'] = '验证码错误，请重新输入';
            }

            echo json_encode($result);
            exit;
        }
        $type = I('type');
        $this->assign('page_title', L('get_password'));
        $this->assign('type', $type);
        $this->assign('user_name', session('forget_user_data.user_name'));
        $this->assign('mobile_phone', session('forget_user_data.phone'));
        $this->assign('email', session('forget_user_data.email'));
        $this->display();
    }

    /**
     * 发送验证码
     * 短信或邮件
     */
    public function actionSendSms(){
        $result = array('error'=>0, 'content'=>'');
        $number = I('post.number'); //手机号码或邮箱
        $type = I('post.type');

        if($type == 'email'){
            // 初始化会员用户名和邮件地址
            $user_name = $this->db->getOne("SELECT user_name FROM {pre}users WHERE email='$number'");
            // 用户信息
            $user_info = $this->users->get_user_info($user_name);

            if ($user_info['user_name'] == $user_name && $user_info['email'] == $number) {
                // 生成code
                $code = $this->generateCodeString();
                // 发送邮件的函数
                if (send_pwd_email($user_info['user_id'], $user_name, $number, $code)) {
                    $result['content'] = L('send_success');
                } else {
                    // 发送邮件出错
                    $result['error'] = 1;
                    $result['content'] = L('fail_send_password');
                }
            } else {
                // 用户名与邮件地址不匹配
                $result['error'] = 1;
                $result['content'] = L('username_no_email');
            }
        } elseif ($type == 'phone') {
            $code = $this->generateCodeString();//生成验证码

            //发送短信
            $template = L('you_auth_code') . $code . L('please_protect_authcode');
            if (!preg_match('/1{1}[0-9]{10}/', $number)) {
                $result['error'] = 1;
                $result['content'] = '手机号码错误';
                exit(json_encode($result));
            }
            // 组装数据
            $message = array(
                'code' => $code
            );
            if (send_sms($number, 'sms_code', $message) === true) {
                $result['error'] = 0;
                $result['content'] = '短信发送成功';
            } else {
                $result['error'] = 1;
                $result['content'] = '短信发送失败';
            }
            //发送短信end
        } else {
            $result['error'] = 1;
            $result['content'] = '操作有误';
        }
        echo json_encode($result);
    }
    /**
     * 生成短信邮箱验证码加密串
     */
    private  function generateCodeString(){
        $code = rand(1000, 9999);
        $verify_string = md5($code.session('forget_user_data.user_id').session('forget_user_data.reg_time'));
        $forgetdata = session('forget_user_data');
        $forgetdata = array_merge($forgetdata, array('verify_str'=>$verify_string));
        session('forget_user_data', $forgetdata);
        return $code;
    }

    /**
     * 检验填写信息
     * 修改密码
     */
    public function actionEditForgetPassword()
    {
        if (IS_POST) {
            $password = I('password', '');
            $uid = session('forget_user_data.user_id');
            if (empty($password)) {
                show_message(L('log_pwd_notnull'));
            }
            if ($uid < 1) {
                show_message(L('log_opration_error'));
            }
            $sql = "SELECT user_name FROM {pre}users WHERE  user_id=" . $uid;
            $user_name = $this->db->getOne($sql);
            if ($this->users->edit_user(array('username' => $user_name, 'old_password' => $password, 'password' => $password), 0)) {
                $sql = "UPDATE {pre}users SET `ec_salt`='0' WHERE user_id= '" . $uid . "'";
                $this->db->query($sql);
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['user_name']);
                show_message(L('edit_sucsess'), L('back_login'), url('user/login/index'), 'success');
            }
            show_message(L('edit_error'), L('retrieve_password'), url('user/login/get_password_phone', array('enabled_sms' => 2)), 'info');
        }
        $this->display();
    }

    /**
     * 修改密码
     */
    public function actionEditPassword()
    {
        // 修改密码处理
        if (IS_POST) {
            $old_password = I('old_password', null);
            $new_password = I('userpassword2', '');
            $user_id = I('uid', $this->user_id);
            $code = I('code', ''); // 邮件code
            $mobile = I('mobile', ''); // 手机号
            if (strlen($new_password) < 6) {
//                show_message("密码不能小于6位");
                show_message(L('log_pwd_six'));
            }
            $user_info = $this->users->get_profile_by_id($user_id); // 论坛记录
            // 短信找回，邮件找回，问题找回，登录修改密码
            if ((!empty($mobile) && base64_encode($user_info['mobiles']) == $mobile) || ($user_info && (!empty($code) && md5($user_info['user_id'] . C('hash_code') . $user_info['reg_time']) == $code)) || ($_SESSION['user_id'] > 0 && $_SESSION['user_id'] == $user_id && $this->load->user->check_user($_SESSION['user_name'], $old_password))) {

                if ($this->load->user->edit_user(array(
                    'username' => ((empty($code) && empty($mobile) && empty($question)) ? $_SESSION['user_name'] : $user_info['user_name']),
                    'old_password' => $old_password,
                    'password' => $new_password
                ), empty($code) ? 0 : 1)
                ) {

                    $data['ec_salt'] = 0;
                    $where['user_id'] = $user_id;
                    $this->db->table('users')
                        ->data($data)
                        ->where($where)
                        ->save();
                    $this->load->user->logout();
                    show_message(L('edit_password_success'), L('relogin_lnk'), url('login'), 'info');
                } else {
                    show_message(L('edit_password_failure'), L('back_page_up'), '', 'info');
                }
            } else {
                show_message(L('edit_password_failure'), L('back_page_up'), '', 'info');
            }
        }
        // 显示修改密码页面

        if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
            $this->assign('title', L('edit_password'));
            // 判断登录方式
            if ($this->is_third_user($_SESSION['user_id'])) {
                $this->assign('is_third', 1);
            }
            $this->assign('page_title', L('edit_password'));
            $this->display();
        } else {
            $this->redirect('login', array('referer' => urlencode(url($this->action))));
        }
    }


    /**
     * 退出
     */
    public function actionLogout()
    {
        if ((!isset($this->back_act) || empty($this->back_act)) && isset($_SERVER['HTTP_REFERER'])) {
            $this->back_act = stripos($_SERVER['HTTP_REFERER'], 'profile') ? url('user/index/index') : $_SERVER['HTTP_REFERER'];
        } else {
            $this->back_act = url('user/login/index');
        }
        $this->users->logout();
        show_message(L('logout'), array(
            L('back_up_page'),
            L('back_home_lnk')
        ), array(
            $this->back_act,
            url('/')
        ), 'success');
    }

    /**
     * 清空浏览历史
     */
    public function clear_history()
    {
        // ajax请求
        if (IS_AJAX) {
            cookie('ECS[history]', '');
            echo json_encode(array('status' => 1));
        } else {
            echo json_encode(array('status' => 0));
        }
    }


    /**
     * 用户注册
     */
    public function actionRegister()
    {

        if (IS_POST) {
            $back_act = isset($_POST['back_act']) ? trim($_POST['back_act']) : url('user/index/index');
            if (I('enabled_sms') == 1) {
                //$username = isset($_POST['username']) ? trim($_POST['username']) : '';
                $username = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
                $mobile = isset($_POST['mobile']) ? trim($_POST['mobile']) : '';
                $password = isset($_POST['smspassword']) ? trim($_POST['smspassword']) : '';
                $sms_code = isset($_POST['mobile_code']) ? trim($_POST['mobile_code']) : '';
                $repassword = isset($_POST['repassword']) ? trim($_POST['repassword']) : '';

                if ($mobile != $_SESSION['sms_mobile'] or $sms_code != $_SESSION['sms_mobile_code']) {
                    exit(json_encode(array('status' => 'n', 'info' => L('log_mobile_verify_error'))));
                }

                if (strlen($username) < 3) {
                    exit(json_encode(array('status' => 'n', 'info' => L('passport_js.username_shorter'))));
                }

                if (strlen($password) < 6) {
                    exit(json_encode(array('status' => 'n', 'info' => L('passport_js.password_shorter'))));
                }

                if (strpos($password, ' ') > 0) {
                    exit(json_encode(array('status' => 'n', 'info' => L('passwd_balnk'))));
                }

                /*if ($password != $repassword) {
                    exit(json_encode(array('status' => 'n', 'info' => L('both_password_error'))));
                }*/

                $email = $username . '@qq.com';
                $other = array(
                    'mobile_phone' => $mobile,
                );
            } elseif (I('enabled_sms') == 2) {
                $username = isset($_POST['username']) ? trim($_POST['username']) : '';
                $email = isset($_POST['email']) ? trim($_POST['email']) : '';
                $password = isset($_POST['password']) ? trim($_POST['password']) : '';
                $repassword = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';

                $passport_js = L("passport_js");
                if (strlen($username) < 3) {
                    exit(json_encode(array('status' => 'n', 'info' => $passport_js['username_shorter'])));
                }
                if(! is_email($email)){
                    exit(json_encode(array('status' => 'n', 'info' => $passport_js['email_invalid'])));
                }
                if (strlen($password) < 6) {
                    exit(json_encode(array('status' => 'n', 'info' => $passport_js['password_shorter'])));
                }
                if (strpos($password, ' ') > 0) {
                    exit(json_encode(array('status' => 'n', 'info' => L('passwd_balnk'))));
                }
                if ($password != $repassword) {
                    exit(json_encode(array('status' => 'n', 'info' => L('both_password_error'))));
                }

                /* 验证码验证 */
                if ((intval(C('shop.captcha')) & CAPTCHA_REGISTER) && gd_version() > 0) {
                    if (empty($_POST['captcha'])) {
                        exit(json_encode(array('status' => 'n', 'info' => L('invalid_captcha'))));
                    }
                    $validator = new Verify();
                    if (!$validator->check($_POST['captcha'])) {
                        exit(json_encode(array('status' => 'n', 'info' => L('invalid_captcha'))));
                    }
                }

                $other = array();
            }

            if (register($username, $password, $email, $other) !== false) {
                /* 判断是否需要自动发送注册邮件 */
                if (C('member_email_validate') && C('send_verify_email')) {
                    send_regiter_hash($_SESSION['user_id']);
                }
                exit(json_encode(array('status' => 'y', 'info' => sprintf(L('register_success'), $username), 'url' => $back_act)));
            } else {               
                $ec_error = $GLOBALS['err']->last_message();
                exit(json_encode(array('status' => 'n', 'info' => $ec_error[0])));
            }

        }
        if ((!isset($back_act) || empty($back_act)) && isset($GLOBALS['_SERVER']['HTTP_REFERER'])) {
            $back_act = strpos($GLOBALS['_SERVER']['HTTP_REFERER'], 'user.php') ? './index.php' : $GLOBALS['_SERVER']['HTTP_REFERER'];
        }

        /* 验证码相关设置 */
        if ((intval(C('shop.captcha')) & CAPTCHA_REGISTER) && gd_version() > 0) {
            $this->assign('enabled_captcha', 1);
            $this->assign('rand', mt_rand());
        }

        $this->assign('flag', 'register');
        $this->assign('back_act', $back_act);
        $this->assign('page_title', '新用户注册');
        $this->assign('page_title', L('registered_user'));
        $this->assign('show', $GLOBALS['_CFG']['sms_signin']);
        $this->display();
    }

    /**
     * 验证验证码
     */
    public function actionCheckcode()
    {
        if (IS_AJAX) {
            $verify = new Verify();
            $code = I('code');
            $code = $verify->check($code);
            if ($code == true) {
                $code = 1;
                echo json_encode($code);
            } else {
                $code = 0;
                echo json_encode($code);
            }
        }
    }

    /**
     * 生成验证码
     */
    public function actionVerify()
    {
        $verify = new Verify();
        $this->assign('code', $verify->entry());
    }

    /**
     * 验证是否登录
     */
    public function actionchecklogin()
    {
        if (!$this->user_id) {
            $url = urlencode(__HOST__ . $_SERVER['REQUEST_URI']);
            if (IS_POST) {
                $url = urlencode($_SERVER['HTTP_REFERER']);
            }
            ecs_header("Location: " . url('user/login/index', array('back_act' => $url)));
            exit;
        }
    }


}

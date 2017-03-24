<?php
namespace app\http\oauth\controllers;

use app\http\base\controllers\Frontend;
use ectouch\Wechat;
use ectouch\Form;

class Index extends Frontend
{
    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH . C('shop.lang') . '/other.php'));
        $this->load_helper('passport');
    }

    public function actionIndex()
    {
        $type = I('get.type');
        $back_url = I('get.back_url', '', 'urldecode');
        $file = ADDONS_PATH . 'connect/' . $type . '.php';
        if (file_exists($file)) {
            include_once($file);
        } else {
            show_message(L('msg_plug_notapply'), L('msg_go_back'), url('user/login/index'));
        }
        // 处理url
        $url = url('/', array(), false, true);
        $param = array(
            'm' => 'oauth',
            'type' => $type,
            'back_url' => empty($back_url) ? url('user/index/index') : $back_url
        );
        $url .= '?' . http_build_query($param, '', '&');
        $config = $this->getOauthConfig($type);
        // 判断是否安装
        if (!$config) {
            show_message(L('msg_plug_notapply'), L('msg_go_back'), url('user/login/index'));
        }
        $obj = new $type($config);

        // 授权回调
        if (isset($_GET['code']) && $_GET['code'] != '') {
            if ($res = $obj->callback($url, $_GET['code'])) {
                // 授权登录
                if ($this->oauthLogin($res, $type) === true) {
                    redirect($back_url);
                }
                // 处理推荐u参数
                $param = get_url_query($back_url);
                $up_uid = get_affiliate();  // 获得推荐uid
                $res['parent_id'] = (!empty($param['u']) && $param['u'] == $up_uid) ? intval($param['u']) : 0;

                session('openid', $res['openid']);
                session('parent_id', $res['parent_id']);
                // 跳转到绑定页面
                $bind_url = url('/', array(), false, true);
                $bind_param = array(
                    'm' => 'oauth',
                    'c' => 'index',
                    'a' => 'bind',
                    'type' => $type,
                    'back_url' => empty($back_url) ? url('user/index/index') : $back_url
                );
                $bind_url .= '?' . http_build_query($bind_param, '', '&');
                redirect($bind_url);

            } else {
                show_message(L('msg_authoriza_error'), L('msg_go_back'), url('user/login/index'), 'error');
            }
            return;
        }
        // 授权开始
        $url = $obj->redirect($url);
        redirect($url);
    }

    /**
     * 用户绑定 / 一键注册
     */
    public function actionBind()
    {
        if (IS_POST) {
            $username = I('username', '', 'trim');
            // 验证
            $form = new Form();
            // 验证手机号并通过手机号查找用户名
            if ($form->isMobile($username, 1)) {
                $user_name = dao('users')->field('user_name')->where(array('mobile_phone' => $username))->find();
                $username = $user_name['user_name'];
            }
            // 验证邮箱并通过邮箱查找用户名
            if ($form->isEmail($username, 1)) {
                $user_name = dao('users')->field('user_name')->where(array('email' => $username))->find();
                $username = $user_name['user_name'];
            }
            $password = I('password', '', 'trim');
            $type = I('type', '', 'trim');
            $back_url = I('back_url', '', 'urldecode');
            // 数据验证
            if (!$form->isEmpty($username, 1) || !$form->isEmpty($password, 1)) {
                show_message(L('msg_input_namepwd'), L('msg_go_back'), '', 'error');
            }

            // 查询users用户是否存在
            $bind_user_id = $this->users->check_user($username, $password);
            if ($bind_user_id > 0) {
                // 查询users用户是否已绑定
                $where = array('user_id' => $bind_user_id);
                $rs = dao('connect_user')->field('user_id')->where($where)->count();
                if($rs > 0){
                    show_message(L('msg_account_bound'), L('msg_rebound'), '', 'error');
                }
                // 同步社会化登录用户信息
                $res = array(
                    'openid' => session('openid'),
                    'nickname' => session('nickname'),
                    'user_id' => $bind_user_id
                );
                $this->update_connnect_user($res, $type);

                if (is_dir(APP_WECHAT_PATH)) {
                    // 查找微信用户是否已经绑定过
                    $where = array('ect_uid' => $bind_user_id);
                    $result = dao('wechat_user')->where($where)->find();
                    if (!empty($result)) {
                        show_message(L('msg_account_bound'), L('msg_go_back'), '', 'error');
                    }
                    // 更新微信用户绑定
                    if (isset($_SESSION['openid']) && !empty($_SESSION['openid'])) {
                        $condition['openid'] = $_SESSION['openid'];
                        $condition['unionid'] = $_SESSION['openid'];
                        $condition['_logic'] = 'OR';
                        dao('wechat_user')->data(array('ect_uid' => $bind_user_id))->where($condition)->save();
                    }
                }

                // 重新登录
                $this->doLogin($username);
                $back_url = empty($back_url) ? url('user/index/index') : $back_url;
                redirect($back_url);
            } else {
                show_message(L('msg_account_bound_fail'), L('msg_rebound'), '', 'error');
            }
            return;
        }
        if(empty($_SESSION['openid']) || !isset($_SESSION['openid'])){
            show_message(L('msg_authoriza_error'), L('msg_go_back'), url('user/login/index'), 'error');
        }
        // 是否自动注册 反之手动绑定
        $is_auto = I('get.is_auto', 0, 'intval');
        $type = I('get.type', '', 'trim');
        $back_url = I('back_url', '', 'urldecode');
        if($is_auto == 1 && !empty($_SESSION['openid']) && isset($_SESSION['openid'])){
            // 自动一键注册
            $res['openid'] = session('openid');
            $res['parent_id'] = session('parent_id');
            $this->doRegister($res, $type, $back_url);
        }
        $this->assign('type', $type);
        $this->assign('back_url', $back_url);
        $this->assign('page_title', L('msg_bound_account'));
        $this->display();
    }

    /**
     * 手动注册新帐号
     */
    public function actionRegister()
    {
        if (IS_POST) {
            $username = I('username', '', 'trim');
            $password = I('password', '', 'trim');
            $email = time() . rand(1,9999) . '@qq.com';
            $type = I('type', '', 'trim');
            $back_url = I('back_url', '', 'urldecode');
            $extends = array(
                'parent_id' => session('parent_id'),
                'nick_name' => session('nickname'),
                'user_picture' => session('avatar'),
            );
            if (register($username, $password, $email, $extends) !== false) {
                // 同步社会化登录用户信息
                $res = array(
                    'openid' => session('openid'),
                    'nickname' => session('nickname'),
                    'user_id' => session('user_id')
                );
                $this->update_connnect_user($res, $type);

                $back_url = empty($back_url) ? url('user/index/index') : $back_url;
                redirect($back_url);
            } else {
                show_message(L('msg_author_register_error'), L('msg_re_registration'), '', 'error');
            }
            return;
        }
        $type = I('get.type', '', 'trim');
        $back_url = I('back_url', '', 'urldecode');
        $this->assign('type', $type);
        $this->assign('back_url', $back_url);
        $this->assign('page_title', L('msg_author_register'));
        $this->display();
    }

    // 重新绑定合并帐号
    public function actionMergeUsers(){
        if($_SESSION['user_id']){
            if (IS_POST) {
                $username = I('username', '', 'trim');
                // 验证
                $form = new Form();
                // 验证手机号并通过手机号查找用户名
                if ($form->isMobile($username, 1)) {
                    $user_name = dao('users')->field('user_name')->where(array('mobile_phone' => $username))->find();
                    $username = $user_name['user_name'];
                }
                // 验证邮箱并通过邮箱查找用户名
                if ($form->isEmail($username, 1)) {
                    $user_name = dao('users')->field('user_name')->where(array('email' => $username))->find();
                    $username = $user_name['user_name'];
                }
                $password = I('password', '', 'trim');
                $back_url = I('back_url', '', 'urldecode');
                // 数据验证
                if (!$form->isEmpty($username, 1) || !$form->isEmpty($password, 1)) {
                    show_message(L('msg_input_namepwd'), L('msg_go_back'), '', 'error');
                }
                $from_user_id = $_SESSION['user_id'];
                // 查询users用户是否存在
                $new_user_id = $this->users->check_user($username, $password);
                if ($new_user_id > 0) {
                    // 查询users用户是否已绑定
                    // $where = array('user_id' => $new_user_id);
                    // $rs = dao('connect_user')->field('user_id')->where($where)->count();
                    // if($rs > 0){
                    //     show_message(L('msg_account_bound'), L('msg_rebound'), '', 'error');
                    // }
                    // 同步社会化登录用户信息
                    $from_connect_user = dao('connect_user')->field('user_id')->where(array('user_id'=> $from_user_id))->select();
                    if(!empty($from_connect_user)){
                        foreach ($from_connect_user as $key => $value) {
                            dao('connect_user')->where('user_id = ' . $value['user_id'])->setField('user_id',$new_user_id);
                        }
                    }
                    if (is_dir(APP_WECHAT_PATH)) {
                        // 微信用户
                        $from_wechat_user = dao('wechat_user')->field('ect_uid')->where(array('ect_uid'=> $from_user_id))->find();
                        if(!empty($from_wechat_user)){
                            dao('wechat_user')->where('ect_uid = ' . $from_wechat_user['ect_uid'])->setField('ect_uid',$new_user_id);
                        }
                    }

                    // 合并绑定会员数据 $from_user_id  $new_user_id
                    $res = merge_user($from_user_id, $new_user_id);
                    if($res){
                        // 退出重新登录
                        $this->users->logout();
                        $back_url = empty($back_url) ? url('user/index/index') : $back_url;
                        show_message(L('logout'), array(L('back_up_page'), "返回首页"), array($back_url, url('/')), 'success');
                    }
                    return;

                } else {
                    show_message(L('msg_account_bound_fail'), L('msg_rebound'), '', 'error');
                }
                return;
            }
            $back_url = I('back_url', '', 'urldecode');
            $this->assign('back_url', $back_url);
            $this->assign('page_title', "重新绑定帐号");
            $this->display();
        }else{
            show_message("请登录", L('msg_go_back'), url('user/login/index'), 'error');
        }
    }

    /**
     * 获取第三方登录配置信息
     *
     * @param type $type
     * @return type
     */
    private function getOauthConfig($type)
    {
        $sql = "SELECT auth_config FROM {pre}touch_auth WHERE `type` = '$type' AND `status` = 1";
        $info = $this->db->getRow($sql);
        if ($info) {
            $res = unserialize($info['auth_config']);
            $config = array();
            foreach ($res as $key => $value) {
                $config[$value['name']] = $value['value'];
            }
            return $config;
        }
        return false;
    }

    /**
     * 授权自动登录
     * @param unknown $res
     */
    private function oauthLogin($res, $type = '')
    {
        // 兼容老用户
        $condition = array('aite_id' => $type . '_' .$res['openid']);
        $userinfo = dao('users')->field('user_name, user_id')->where($condition)->find();

        if (!empty($userinfo)) {
            // 清空原始表aite_id
            $data = array('aite_id' => '');
            dao('users')->data($data)->where($condition)->save();
            // 同步社会化登录用户信息表
            $res['user_id'] = $userinfo['user_id'];
            $this->update_connnect_user($res, $type);
        } else {
            // 查询新用户
            $sql = "SELECT u.user_name, u.user_id FROM {pre}users u, {pre}connect_user cu WHERE u.user_id = cu.user_id AND cu.open_id = '" . $res['openid'] . "'";
            $userinfo = $this->db->getRow($sql);
        }
        // 已经绑定过的 授权自动登录
        if ($userinfo) {
            $this->doLogin($userinfo['user_name']);
            // 更新昵称和头像
            $user_data = array(
                'nick_name' => !empty($_SESSION['nickname']) ? $_SESSION['nickname'] : $res['name'],
                'user_picture' => !empty($_SESSION['avatar']) ? $_SESSION['avatar'] : $res['avatar'],
                );
            dao('users')->data($user_data)->where(array('user_id' => $userinfo['user_id']))->save();
            return true;
        } else {
            return false;
        }
    }

    /**
     * 设置成登录状态
     * @param unknown $username
     */
    private function doLogin($username)
    {
        $this->users->set_session($username);
        $this->users->set_cookie($username);
        update_user_info();
        recalculate_price();
    }

    /**
     * 授权注册
     * @param $res
     * @param string $back_url
     */
    private function doRegister($res, $type = '', $back_url = '')
    {
        $username = substr(md5($res['openid']), -2) . time() . rand(100, 999);
        $password = mt_rand(100000, 999999);
        $email = $username . '@qq.com';
        $extends = array(
            'parent_id' => $res['parent_id'],
            'nick_name' => !empty($_SESSION['nickname']) ? $_SESSION['nickname'] : $res['name'],
            'user_picture' => !empty($_SESSION['avatar']) ? $_SESSION['avatar'] : $res['avatar'],
        );
        if (register($username, $password, $email, $extends) !== false) {

            // 同步社会化登录用户信息表
            $res = array(
                    'openid' => session('openid'),
                    'nickname' => session('nickname'),
                    'user_id' => session('user_id')
                );
            $this->update_connnect_user($res, $type);
            // 更新微信用户绑定信息
            if (is_dir(APP_WECHAT_PATH)) {
                if (isset($_SESSION['openid']) && !empty($_SESSION['openid'])) {
                    $data = array('ect_uid' => $_SESSION['user_id']);
                    $condition['openid'] = $_SESSION['openid'];
                    $condition['unionid'] = $_SESSION['openid'];
                    $condition['_logic'] = 'OR';
                    dao('wechat_user')->data($data)->where($condition)->save();
                    //关注送红包
                    $this->sendBonus();
                }
            }
            // 跳转链接
            $back_url = empty($back_url) ? url('user/index/index') : $back_url;
            redirect($back_url);
        } else {
            show_message(L('msg_author_register_error'), L('msg_re_registration'), '', 'error');
        }
        return;
    }

    /**
     * 同步社会化登录用户信息表
     * @param  [type] $res, $type:qq,sina,wechat
     * @return
     */
    private function update_connnect_user($res, $type = '')
    {
        $data = array(
            'connect_code' => 'sns_' . $type,
            'user_id' => $res['user_id'],
            'open_id' => $res['openid'],
            'profile' => serialize($res),
            'create_at' => gmtime()
        );
        // 查询是否绑定
        $where = array('user_id' => $res['user_id']);
        $connect_userinfo = dao('connect_user')->field('open_id')->where($where)->find();

        if (empty($connect_userinfo)) {
            // 未绑定插入记录
            dao('connect_user')->data($data)->add();
        } else {
            // 已经绑定更新记录
            dao('connect_user')->data($data)->where($where)->save();
        }
    }

    /**
     * 关注送红包
     */
    private function sendBonus()
    {
        // 查询平台微信配置信息
        $wxinfo = dao('wechat')->field('id, token, appid, appsecret, encodingaeskey')->where(array('default_wx' => 1, 'status' => 1))->find();
        if ($wxinfo) {
            // 查询功能扩展 是否安装
            $rs = $this->db->query("SELECT name, keywords, command, config FROM {pre}wechat_extend WHERE command = 'bonus' and enable = 1 and wechat_id = " . $wxinfo['id'] . " ORDER BY id ASC");
            $addons = reset($rs);
            $file = ADDONS_PATH . 'wechat/' . $addons['command'] . '/' . ucfirst($addons['command']) . '.php';
            if (file_exists($file)) {
                require_once($file);
                $new_command = '\\app\\modules\\wechat\\' . $addons['command'] . '\\' . ucfirst($addons['command']);
                $wechat = new $new_command();
                $data = $wechat->returnData($_SESSION['openid'], $addons);
                if (!empty($data)) {
                    $config['token'] = $wxinfo['token'];
                    $config['appid'] = $wxinfo['appid'];
                    $config['appsecret'] = $wxinfo['appsecret'];
                    $config['encodingaeskey'] = $wxinfo['encodingaeskey'];
                    $weObj = new Wechat($config);
                    $weObj->sendCustomMessage($data['content']);
                }
            }
        }

    }
}

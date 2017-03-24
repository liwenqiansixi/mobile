<?php
namespace app\http\oauth\controllers;

use app\http\base\controllers\Backend;

class Admin extends Backend
{

    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH . C('shop.lang') . '/other.php'));
        $this->assign('lang', array_change_key_case(L()));
        $this->admin_priv('oauth_admin');
    }

    /**
     * 授权列表
     */
    public function actionIndex()
    {
        $modules = $this->read_modules(BASE_PATH . 'modules/connect');
        foreach ($modules as $key => $value) {
            $modules[$key]['install'] = dao('touch_auth')->where(array('type' => $value['type']))->count();
        }
        $this->assign('modules', $modules);
        $this->display();
    }

    /**
     * 安装授权登录
     */
    public function actionInstall()
    {
        if (IS_POST) {
            $data['type'] = I('type');
            $data['status'] = I('status', 0, 'intval');
            $data['sort'] = I('sort', 0, 'intval');
            $cfg_value = I('cfg_value');
            $cfg_name = I('cfg_name');
            $cfg_type = I('cfg_type');
            $cfg_label = I('cfg_label');
            // 取得配置信息
            $auth_config = array();
            if (isset($cfg_value) && is_array($cfg_value)) {
                for ($i = 0; $i < count($cfg_value); $i++) {
                    $auth_config[] = array(
                        'name' => trim($cfg_name[$i]),
                        'type' => trim($cfg_type[$i]),
                        'value' => trim($cfg_value[$i])
                    );
                }
            }
            $data['auth_config'] = serialize($auth_config);
            // 插入配置信息
            $this->model->table('touch_auth')->data($data)->add();
            $this->message(L('msg_ins_success'), url('index'));
            return;
        }
        $type = I('type');
        $oauth_config = $this->getOauthConfig($type);
        // 安装过跳转到列表页面
        if ($oauth_config !== false) {
            $this->redirect('index');
        }
        $filepath = BASE_PATH . 'modules/connect/' . $type . '.php';
        if (file_exists($filepath)) {
            $set_modules = true;
            include_once($filepath);
            $info = $modules[$i];
            foreach ($info['config'] as $key => $value) {
                $info['config'][$key] = $value + array('label' => L($value['name']));
            }
        }
        $this->assign('info', $info);
        $this->assign('ur_here', L('plug_install'));
        $this->display();
    }

    /**
     * 编辑授权
     */
    public function actionEdit()
    {
        if (IS_POST) {
            $data['type'] = I('type');
            $data['status'] = I('status', 0, 'intval');
            $data['sort'] = I('sort', 0, 'intval');
            $cfg_value = I('cfg_value');
            $cfg_name = I('cfg_name');
            $cfg_type = I('cfg_type');
            $cfg_label = I('cfg_label');
            // 取得配置信息
            $auth_config = array();
            if (isset($cfg_value) && is_array($cfg_value)) {
                for ($i = 0; $i < count($cfg_value); $i++) {
                    $auth_config[] = array(
                        'name' => trim($cfg_name[$i]),
                        'type' => trim($cfg_type[$i]),
                        'value' => trim($cfg_value[$i])
                    );
                }
            }
            $data['auth_config'] = serialize($auth_config);
            dao('touch_auth')->data($data)->where(array('type' => $data['type']))->save();

            $this->message(L('edit_success'), url('index'));
            return;
        }
        $type = I('type');
        $oauth_config = $this->getOauthConfig($type);
        // 没有安装过跳转到列表页面
        if ($oauth_config === false) {
            $this->redirect('index');
        }
        $filepath = BASE_PATH . 'modules/connect/' . $type . '.php';
        if (file_exists($filepath)) {
            $set_modules = true;
            include_once($filepath);
            $info = $modules[$i];
            foreach ($info['config'] as $key => $value) {
                $info['config'][$key] = $value + array('label' => L($value['name']));
            }
        }
        // 循环配置插件中所有属性
        foreach ($info['config'] as $key => $value) {
            if (isset($oauth_config[$value['name']])) {
                $info['config'][$key]['value'] = $oauth_config[$value['name']];
            } else {
                $info['config'][$key]['value'] = $value['value'];
            }
        }
        $info['status'] = $oauth_config['status'];
        $info['sort'] = $oauth_config['sort'];

        $this->assign('info', $info);
        $this->assign('ur_here', L('edit_plug'));
        $this->display();
    }

    /**
     * 卸载授权
     */
    public function actionUninstall()
    {
        $condition['type'] = I('type');
        dao('touch_auth')->where($condition)->delete();

        $this->message(L('upload_success'), url('index'));
    }

    private function getOauthConfig($type)
    {
        $condition['type'] = $type;
        $info = dao('touch_auth')->field('auth_config, status, sort')->where($condition)->find();
        if ($info) {
            $user = unserialize($info['auth_config']);
            $config = array('status' => $info['status'], 'sort' => $info['sort']);
            foreach ($user as $key => $value) {
                $config[$value['name']] = $value['value'];
            }
            return $config;
        }
        return false;
    }

    /**
     * 获得所有模块的名称以及链接地址
     *
     * @access public
     * @param string $directory
     *            插件存放的目录
     * @return array
     */
    private function read_modules($directory = '.')
    {
        $dir = @opendir($directory);
        $set_modules = true;
        $modules = array();
        while (false !== ($file = @readdir($dir))) {
            if (preg_match("/^.*?\.php$/", $file)) {
                include_once($directory . '/' . $file);
            }
        }
        @closedir($dir);
        unset($set_modules);
        foreach ($modules as $key => $value) {
            ksort($modules[$key]);
        }
        ksort($modules);
        return $modules;
    }
}

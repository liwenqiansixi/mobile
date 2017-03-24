<?php

namespace app\http\upgrade\controllers;

use app\http\base\controllers\Backend;
use ectouch\Http;
use ectouch\Install;
use ectouch\Zip;

class Index extends Backend
{

    private $md5_arr = array();
    private $_filearr = array('admin', 'api', 'include', 'plugins', '');
    private $_wechat = 'wechat';
    private $_extend = 'extend';
    // md5验证地址
    private $_upgrademd5 = 'http://www.ectouch.cn/upgrademd5/';
    // 补丁地址
    private $_patchurl = 'http://upgrade.ecmoban.com/dscmall/1.0/';

    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
        $this->charset = str_replace('-', '', CHARSET);
        L(require(LANG_PATH . C('shop.lang') . '/upgrade.php'));
        defined('UPGRADE_PATH') or define('UPGRADE_PATH', ROOT_PATH . 'storage/upgrade/');
    }

    /**
     * 升级列表
     */
    public function actionIndex()
    {
        $pathlist = $this->pathlist();
        $this->assign('pathlist', $pathlist);
        $this->assign('page_title', '在线更新');
        $this->display();
    }

    /**
     * 在线升级
     */
    public function actionInit()
    {
        // 开始升级
        $do = I('get.do');
        $cover = I('cover', 0);
        if (empty($do)) {
            $this->message(L('upgradeing'), url('init', array('do' => 1, 'cover' => $cover)));
        }
        // 获取补丁列表
        $pathlist = $this->pathlist();
        if (empty($pathlist)) {
            $this->message(L('upgrade_success'), url('checkfile'));
        }
        // 创建缓存文件夹
        if (!file_exists(UPGRADE_PATH)) {
            @mkdir(UPGRADE_PATH);
        }
        foreach ($pathlist as $k => $v) {
            // 补丁文件名
            $patch_name = $v . '_patch.zip';
            // 补丁版本号
            $release = str_replace('v', '', $v);
            // 远程压缩包地址
            $upgradezip_url = $this->_patchurl . 'patch/' . $patch_name . '?t=' . time();
            // 保存到本地地址
            $upgrade_zip = UPGRADE_PATH . $patch_name;
            // 解压路径
            $upgrade_path = UPGRADE_PATH . basename($patch_name, ".zip") . '/';
            // 下载压缩包
            file_put_contents($upgrade_zip, Http::curlPost($upgradezip_url));

            // 解压缩
            $zip = new Zip();
            if ($zip->decompress($upgrade_zip, $upgrade_path) == 0) {
                die("Error : unpack the failure.");
            }

            // 拷贝文件夹到根目录
            $this->copyfailnum = 0;
            $this->copydir($upgrade_path, dirname(ROOT_PATH), $cover);
            // 检查文件操作权限，是否复制成功
            if ($this->copyfailnum > 0) {
                // 如果失败，终止操作
                $this->message(L('please_check_filepri'), url('index'));
            }

            // 执行sql
            // sql目录地址
            $sql_path = $upgrade_path . 'upgrade/';
            $file_list = glob($sql_path . '*');
            /*if (!empty($file_list)) {
                foreach ($file_list as $fk => $fv) {
                    $file_path = strtolower($fv);
                    if (in_array(substr($file_path, -3, 3), array('php', 'sql')) && substr($file_path, -10, 10) != 'config.php') {
                        if (substr($file_path, -3, 3) == 'sql') {
                            // 更新sql文件
                            $sqlData = Install::mysql($file_path, 'ecs_', C('DB_PREFIX'));
                            // 执行sql文件
                            $model = new EcModel();
                            if (is_array($sqlData)) {
                                foreach ($sqlData as $sql) {
                                    @$model->db->query($sql);
                                }
                            }
                        } elseif (strtolower(substr($file_list[$fk], -3, 3)) == 'php' && file_exists($file_path)) {
                            include $file_path;
                        }
                    }
                }
            }*/

            // 删除文件
            @unlink($upgrade_zip);
            // 删除文件夹
            $this->del_dir($upgrade_path);

            // 提示语
            $tmp_k = $k + 1;
            if (!empty($pathlist[$tmp_k])) {
                $next_update = '<br />' . L('upgradeing') . basename($pathlist[$tmp_k], ".zip");
            } else {
                $next_update = '';
            }
            // 是否升级成功
            $this->message($v . L('upgrade_success') . $next_update, url('init', array('do' => 1, 'cover' => $cover)));
        }

    }

    // 检查文件md5值
    public function checkfile()
    {
        $do = I('get.do', 0);
        if (!empty($do)) {
            $this->ec_readdir('.');
            // 读取接口
            $ectouch_md5 = Http::doGet($this->_upgrademd5 . RELEASE . '_' . $this->patch_charset . ".php");
            $ectouch_md5_arr = json_decode($ectouch_md5, 1);
            $ectouch_md5_arr = empty($ectouch_md5_arr) ? array() : $ectouch_md5_arr;
            // 计算数组差集
            $diff = array_diff($ectouch_md5_arr, $this->md5_arr);
            // 丢失文件列表
            $lostfile = array();
            foreach ($ectouch_md5_arr as $k => $v) {
                if (!in_array($k, array_keys($this->md5_arr))) {
                    $lostfile[] = $k;
                    unset($diff[$k]);
                }
            }
            // 未知文件列表
            $unknowfile = array_diff(array_keys($this->md5_arr), array_keys($ectouch_md5_arr));
            // 赋值
            $this->assign('diff', $diff);
            $this->assign('lostfile', $lostfile);
            $this->assign('unknowfile', $unknowfile);
            $this->display();
        } else {
            $this->message(L('begin_checkfile'), url('index', array('do' => 1)));
        }
    }

    /**
     * 生成核心文件md5
     */
    public function buildhash()
    {
        $this->ec_readdir('.');
        file_put_contents(CACHE_PATH . RELEASE . '_' . $this->patch_charset . '.php', json_encode($this->md5_arr));
        $this->message(L('build_success'), url('index'));
    }

    /**
     * 获取补丁列表
     */
    private function pathlist()
    {
        $patch_url = $this->_patchurl . 'index.html?t=' . time();
        $pathlist_str = Http::doGet($patch_url);
        $patch_list = $allpathlist = array();
        $key = -1;

        // 获取压缩包列表
        preg_match_all("/\"(patch\/v(.*)_patch\.zip)\"/", $pathlist_str, $allpathlist);
        $allpathlist = $allpathlist[1];

        // 获取可供当前版本升级的压缩包
        foreach ($allpathlist as $k => $v) {
            if (strstr($v, 'v' . VERSION . '_patch.zip')) {
                $key = $k;
                break;
            }
        }
        $key = $key < 0 ? 9999 : $key;
        foreach ($allpathlist as $k => $v) {
            if ($k > $key) {
                $patch_list[$k] = str_replace(array('patch/', '_patch.zip'), '', $v);
            }
        }
        return $patch_list;
    }

    private function ec_readdir($path = '')
    {
        $dir_arr = explode('/', dirname($path));
        if (is_dir($path)) {
            $handler = opendir($path);
            while (($filename = @readdir($handler)) !== false) {
                if (substr($filename, 0, 1) != ".") {
                    $this->ec_readdir($path . '/' . $filename);
                }
            }
            closedir($handler);
        } else {
            if (dirname($path) == '.' || (isset($dir_arr[1]) && in_array($dir_arr[1], $this->_filearr))) {
                $pos_wechat = strpos(strtolower($path), $this->_wechat);
                $pos_extend = strpos(strtolower($path), $this->_extend);
                if ($pos_wechat === false && $pos_extend === false) {
                    $this->md5_arr[base64_encode($path)] = md5_file($path);
                }
            }
        }
    }

    private function copydir($dirfrom, $dirto, $cover = '')
    {
        // 如果遇到同名文件无法复制，则直接退出
        if (is_file($dirto)) {
            die(L('have_no_pri') . $dirto);
        }

        // 如果目录不存在，则建立之
        if (!file_exists($dirto)) {
            mkdir($dirto);
        }

        $handle = opendir($dirfrom);
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $filefrom = $dirfrom . '/' . $file;
                $fileto = $dirto . '/' . $file;
                if (is_dir($filefrom)) {
                    $this->copydir($filefrom, $fileto, $cover);
                } else {
                    if (!copy($filefrom, $fileto)) {
                        $this->copyfailnum++;
                        echo L('copy') . $filefrom . L('to') . $fileto . L('failed') . "<br />";
                    }
                }
            }
        }
    }

    /**
     * 遍历删除目录和目录下所有文件
     * @param unknown $dir
     * @return boolean
     */
    private function del_dir($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }
        $handle = opendir($dir);
        while (($file = readdir($handle)) !== false) {
            if ($file != "." && $file != "..") {
                is_dir("$dir/$file") ? $this->del_dir("$dir/$file") : @unlink("$dir/$file");
            }
        }
        if (readdir($handle) == false) {
            closedir($handle);
            @rmdir($dir);
        }
    }


}

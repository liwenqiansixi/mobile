<?php
namespace app\behavior;

use Think\Model;
use Symfony\Component\Filesystem\Filesystem;

/**
 * 系统行为扩展：版本兼容
 */
class CompatibleBehavior
{
    private $fs;
    private $model;

    // 兼容nav 图片资源
    public function run()
    {
        $nav_path = dirname(ROOT_PATH) . '/data/attached/nav';
        if (!is_dir($nav_path)) {
            $this->model = new Model();
            $this->fs = new Filesystem();
            $this->fs->mirror(ROOT_PATH . 'statics/img/more-nav', $nav_path);
            $this->model->execute('update {pre}touch_nav set `pic` = replace(`pic`, "more-nav/","")');
        }
    }
}

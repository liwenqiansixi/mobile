<?php
namespace app\http\captcha\controllers;

use app\http\base\controllers\Frontend;
use Think\Verify;

class Index extends Frontend {

    /**
     * 验证码
     */
    public function actionIndex()
    {
        $params = array(
            'fontSize' => 14, // 验证码字体大小
            'length' => 4, // 验证码位数
            'useNoise' => false, // 关闭验证码杂点
            'fontttf' => '4.ttf',
            'bg' => array(255, 255, 255)
        );
        $verify = new Verify($params);
        $verify->entry();
    }
}
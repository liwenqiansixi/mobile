<?php
namespace app\http\search\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend {

    /**
     * 首页信息
     */
    public function actionIndex()
    {
        $this->display();
    }
}

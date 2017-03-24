<?php
namespace app\custom\site\controllers;

use app\http\site\controllers\Index as Foundation;

class Index extends Foundation
{
    /**
     * URL路由访问地址: mobile/index.php?m=site&c=index&a=about
     */
    public function actionAbout()
    {
        $this->display();
    }

    public function actionPhpinfo(){
        // phpinfo();
    }
}
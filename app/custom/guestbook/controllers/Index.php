<?php
namespace app\custom\guestbook\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend
{

    public function actionIndex()
    {
        echo 'this guestbook list. ';
        echo '<a href="' . url('add') . '">Goto Add</a>';
    }

    public function actionAdd()
    {
        $this->display();
    }

    public function actionSave()
    {
        $post = array(
            'title' => I('title'),
            'content' => I('content')
        );

        // 验证数据
        // todo

        // 保存数据        
        // $this->model->table('guestbook')->data($post)->add();

        // 页面跳转
        $this->redirect('index');
    }
}
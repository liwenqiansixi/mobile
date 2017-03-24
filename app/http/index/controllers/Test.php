<?php
namespace app\http\index\controllers;

use app\http\base\controllers\Frontend;
use app\repository\Article;

class Test extends Frontend
{

    private $article;

    public function __construct(Article $articles)
    {
        parent::__construct();
        $this->article = $articles;
    }

    public function actionIndex()
    {
        // 返回id=58的文章
        $res = $this->article->find(58);
        dump($res);
    }

}

<?php
namespace app\http\index\controllers;

use app\http\base\controllers\Frontend;
use app\classes\Compile;
use ectouch\Http;

class Index extends Frontend
{

    public function __construct()
    {
        parent::__construct();
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: X-HTTP-Method-Override, Content-Type, x-requested-with, Authorization');
    }

    /**
     * 首页信息
     * post: /index.php?m=index
     * param: null
     * return: module
     */
    public function actionIndex()
    {
        if (IS_POST) {
            $preview = input('preview', 0);
            if ($preview) {
                $module = Compile::getModule('preview');
            } else {
                $module = Compile::getModule();
            }
            if ($module === false) {
                $module = Compile::initModule();
            }
            $this->response(array('error' => 0, 'data' => $module ? $module : ''));
        }
        $this->assign('page_title', config('shop.shop_name'));
        $this->assign('description', config('shop.shop_desc'));
        $this->assign('keywords', config('shop.shop_keywords'));
        $this->display();
    }

    /**
     * 站内快讯
     */
    public function actionNotice()
    {
        $condition = array(
            'is_open' => 1,
            'cat_id' => 12
        );
        $list = $this->db->table('article')->field('article_id, title, author, add_time, file_url, open_type')
            ->where($condition)->order('article_type DESC, article_id DESC')->limit(5)->select();
        $res = array();
        foreach ($list as $key => $vo) {
            $res[$key]['text'] = $vo['title'];
            $res[$key]['url'] = build_uri('article', array('aid' => $vo['article_id']));
        }
        $this->response(array('error' => 0, 'data' => $res));
    }

    /**
     * 返回商品列表
     * post: /index.php?m=admin&c=editor&a=goods
     * param:
     * return:
     */
    public function actionGoods()
    {
        $condition = array(
            'intro' => input('post.type', '')
        );
        $list = $this->getGoodsList($condition);
        $res = array();
        $endtime = gmtime(); // time() + 7 * 24 * 3600;
        foreach ($list as $key => $vo) {
            $res[$key]['desc'] = $vo['name']; // 描述
            $res[$key]['sale'] = $vo["sales_volume"]; // 销量
            $res[$key]['stock'] = $vo['goods_number']; // 库存
            $res[$key]['price'] = $vo['shop_price']; // 价格
            $res[$key]['marketPrice'] = $vo["market_price"]; // 市场价
            $res[$key]['img'] = $vo['goods_thumb']; // 图片地址
            $res[$key]['link'] = $vo['url']; // 图片链接
            $endtime = $vo['promote_end_date'] > $endtime ? $vo['promote_end_date'] : $endtime;
        }
        $this->response(array('error' => 0, 'data' => $res, 'endtime' => date('Y-m-d H:i:s', $endtime)));
    }

    /**
     * 返回商品列表
     * @param string $param
     * @return array
     */
    protected function getGoodsList($param = array())
    {
        $data = array(
            'id' => 0,
            'brand' => 0,
            'intro' => '',
            'price_min' => 0,
            'price_max' => 0,
            'filter_attr' => 0,
            'sort' => 'goods_id',
            'order' => 'desc',
            'keyword' => '',
            'isself' => 0,
            'hasgoods' => 0,
            'promotion' => 0,
            'page' => 1,
            'type' => 1,
            'size' => 10,
            config('VAR_AJAX_SUBMIT') => 1
        );
        $data = array_merge($data, $param);
        $cache_id = md5(serialize($data));
        $list = cache($cache_id);
        if ($list === false) {
            $url = url('category/index/products', $data, false, true);
            $res = Http::doGet($url);
            if($res === false){
               $res = file_get_contents($url);
            }
            if ($res) {
                $data = json_decode($res, 1);
                $list = empty($data['list']) ? false : $data['list'];
                cache($cache_id, $list, 600);
            }
        }
        return $list;
    }

}

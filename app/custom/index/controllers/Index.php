<?php
namespace app\custom\index\controllers;

use app\http\index\controllers\Index as Foundation;

class Index extends Foundation
{
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
            $res[$key]['price'] = (isset($_SESSION['user_status']) && $_SESSION['user_status'] == 1) ? $vo['shop_price'] : ''; // 价格
            $res[$key]['marketPrice'] = $vo["market_price"]; // 市场价
            $res[$key]['img'] = $vo['goods_thumb']; // 图片地址
            $res[$key]['link'] = $vo['url']; // 图片链接
            $endtime = $vo['promote_end_date'] > $endtime ? $vo['promote_end_date'] : $endtime;
        }
        $this->response(array('error' => 0, 'data' => $res, 'endtime' => date('Y-m-d H:i:s', $endtime)));
    }

}
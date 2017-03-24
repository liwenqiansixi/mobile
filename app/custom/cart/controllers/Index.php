<?php
namespace app\custom\cart\controllers;

use app\http\cart\controllers\Index as ParentFrontend;

class Index extends ParentFrontend
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 购物车列表 连接到index
     */
    public function actionIndex()
    {
        /* 标记购物流程为普通商品 */
        $_SESSION['flow_type'] = CART_GENERAL_GOODS;

        /* 如果是一步购物，跳到结算中心 */
        if (C('shop.one_step_buy') == '1') {
            unset($_SESSION['cart_value']);
            ecs_header("Location: " . url('flow/index/index') . "\n");
            exit;
        }
        /* 取得优惠活动 */
        $favourable_list = favourable_list($_SESSION['user_rank']);

        usort($favourable_list, 'cmp_favourable');

        /* 计算折扣 */
        $discount = compute_discount(3);

        $fav_amount = $discount['discount'];

        /* 取得商品列表，计算合计 */
        $cart_goods = get_cart_goods('', 0, $favourable_list);

        // 获取每个商品是否有配件
        $cart_show = array();
        if ($cart_goods['goods_list']) {
            foreach ($cart_goods['goods_list'] as $k => $list) {
                if ($list['goods_list']) {
                    $fitting_key = 0;
                    foreach ($list['goods_list'] as $key => $val) {
                        $num = get_goods_fittings(array($val['goods_id']));
                        $cart_goods['goods_list'][$k]['goods_list'][$key]['store_name'] = getStoresName($val['store_id']);
                        $count = count($num);
                        if ($fitting_key != 1 && !empty($count)) {
                            $cart_goods['goods_list'][$k]['fitting'] = $count > 0 ? $count : 0;
                            $fitting_key = 1;
                        }
                        $cart_show['cart_goods_number'] += $val['goods_number'];
                        /* 取得超值礼包图片 */
                        if($cart_goods['goods_list'][$k]['goods_list'][$key]['extension_code'] =='package_buy'){
                            $sql = "SELECT activity_thumb FROM {pre}goods_activity WHERE review_status = 3 AND act_id =" . $cart_goods['goods_list'][$k]['goods_list'][$key]['goods_id'] . ' and is_finished = 0';
                            $activity_thumb = $this->db->getRow($sql);
                            $cart_goods['goods_list'][$k]['goods_list'][$key]['goods_thumb'] = get_image_path($activity_thumb['activity_thumb']);
                        }
                        // 最小起订量 start add by yang
                        $quantity_result = get_goods_min_quantity($val['goods_id'], $val['goods_attr_id'], $val['user_id'], $this->region_id, $this->area_info['region_id']);
                        $cart_goods['goods_list'][$k]['goods_list'][$key]['min_quantity'] = $quantity_result['min_quantity'];
                    }
                }
            }
        }
        /** 过滤赠品 */
        foreach($cart_goods['goods_list'] as $k => $v){
            $cart_goods['goods_list'][$k]['is_show_favourable'] = 1;
            $num = 0;
            foreach($v['favourable'] as $fk => $fv){
                if($v['amount'] < $fv['min_amount'] || ($v['amount'] > $fv['max_amount'] && $fv['max_amount'] != 0)){
                    $cart_goods['goods_list'][$k]['favourable'][$fk]['is_show'] = 0;
                    $num++;
                }
            }

            if($num == count($v['favourable'])){
                $cart_goods['goods_list'][$k]['is_show_favourable'] = 0;
            }
        }
        if($cart_goods['total']['goods_amount']){
            $cart_goods['total']['goods_amount'] = $cart_goods['total']['goods_amount'] - $fav_amount;
            $cart_goods['total']['goods_price'] = price_format($cart_goods['total']['goods_amount']);
        }else{
            $result['save_total_amount'] = 0;
        }
        $this->assign('cart_show', $cart_show);//购物车商品数&购物车总价
        $this->assign('goods_list', $cart_goods['goods_list']);//商品列表
        $this->assign('total', $cart_goods['total']);
        $this->assign('relation', $this->relation_goods($this->region_id, $this->area_info['region_id']));//推荐商品
        $this->assign('currency_format', sub_str(strip_tags($GLOBALS['_CFG']['currency_format']),1,false));//货币格式
        $this->assign('page_title', '购物车');
        $this->display();
    }


    /*
     * 加入购物车
     */
    public function actionAddToCart()
    {
        $goods = I('goods', '', 'stripcslashes');
        $goods_id = I('post.goods_id', 0, 'intval');
        $result = array('error' => 0, 'message' => '', 'content' => '', 'goods_id' => '', 'url' => '');
        if (!empty($goods_id) && empty($goods)) {
            if (!is_numeric($goods_id) || intval($goods_id) <= 0) {
                //跳转到首页
                $result['error'] = 1;
                $result['url'] = url('/');
                die(json_encode($result));
            }
        }
        if (empty($goods)) {
            $result['error'] = 1;
            $result['url'] = url('/');
            die(json_encode($result));
        }
        // 非APP进入 user_status = 0 限制加入购物车
        if($_SESSION['user_status'] == 0){
            $result['error'] = 1;
            $result['message'] = "无法购买";
            // $result['url'] = url('/');
            die(json_encode($result));
        }
        $goods = stripslashes($goods);
        $goods = json_decode($goods);
        $warehouse_id = intval($goods->warehouse_id);
        $area_id = intval($goods->area_id);
        /* 检查：该地区是否支持配送 ecmoban模板堂 --zhuo */
        if (C('shop.open_area_goods') == 1) {

            $leftJoin = '';
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

            $sql = "SELECT g.user_id, g.review_status, g.model_attr, " .
                ' IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number ' .
                " FROM " . $GLOBALS['ecs']->table('goods') . " as g " .
                $leftJoin .
                " WHERE g.goods_id = '" . $goods->goods_id . "'";
            $goodsInfo = $GLOBALS['db']->getRow($sql);

            $area_list = get_goods_link_area_list($goods->goods_id, $goodsInfo['user_id']);

            if ($area_list['goods_area']) {
                if (!in_array($area_id, $area_list['goods_area'])) {
                    $no_area = 2;
                }
            } else {
                $no_area = 2;
            }

            if ($goodsInfo['model_attr'] == 1) {
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '$warehouse_id'";
            } elseif ($goodsInfo['model_attr'] == 2) {
                $table_products = "products_area";
                $type_files = " and area_id = '$area_id'";
            } else {
                $table_products = "products";
                $type_files = "";
            }

            $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '" . $goods->goods_id . "'" . $type_files . " LIMIT 0, 1";
            $prod = $GLOBALS['db']->getRow($sql);

            if (empty($prod)) { //当商品没有属性库存时
                $prod = 1;
            } else {
                $prod = 0;
            }

            if ($no_area == 2) {
                $result['error'] = 1;
                $result['message'] = L('not_support_delivery');

                die(json_encode($result));
            } elseif ($goodsInfo['review_status'] <= 2) {
                $result['error'] = 1;
                $result['message'] = L('down_shelves');

                die(json_encode($result));
            }

        }

        /* 检查：如果商品有规格，而post的数据没有规格，把商品的规格属性通过JSON传到前台 */
        if (empty($goods->spec) AND empty($goods->quick)) {
            //ecmoban模板堂 --zhuo start
            $groupBy = " group by ga.goods_attr_id ";
            $leftJoin = '';

            $shop_price = "wap.attr_price, wa.attr_price, g.model_attr, ";

            $leftJoin .= " left join " . $GLOBALS['ecs']->table('goods') . " as g on g.goods_id = ga.goods_id";
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_attr') . " as wap on ga.goods_id = wap.goods_id and wap.warehouse_id = '$warehouse_id' and ga.goods_attr_id = wap.goods_attr_id ";
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_attr') . " as wa on ga.goods_id = wa.goods_id and wa.area_id = '$area_id' and ga.goods_attr_id = wa.goods_attr_id ";
            //ecmoban模板堂 --zhuo end

            $sql = "SELECT a.attr_id, a.attr_name, a.attr_type, " .
                "ga.goods_attr_id, ga.attr_value, IF(g.model_attr < 1, ga.attr_price, IF(g.model_attr < 2, wap.attr_price, wa.attr_price)) as attr_price " .
                'FROM ' . $GLOBALS['ecs']->table('goods_attr') . ' AS ga ' .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('attribute') . ' AS a ON a.attr_id = ga.attr_id ' . $leftJoin .
                "WHERE a.attr_type != 0 AND ga.goods_id = '" . $goods->goods_id . "' " . $groupBy .
                'ORDER BY a.sort_order, IF(g.model_attr < 1, ga.attr_price, IF(g.model_attr < 2, wap.attr_price, wa.attr_price)), ga.goods_attr_id';

            $res = $this->db->query($sql);
            if (!empty($res)) {
                $spe_arr = array();
                foreach ($res AS $row) {
                    $spe_arr[$row['attr_id']]['attr_type'] = $row['attr_type'];
                    $spe_arr[$row['attr_id']]['name'] = $row['attr_name'];
                    $spe_arr[$row['attr_id']]['attr_id'] = $row['attr_id'];
                    $spe_arr[$row['attr_id']]['values'][] = array(
                        'label' => $row['attr_value'],
                        'price' => $row['attr_price'],
                        'format_price' => price_format($row['attr_price'], false),
                        'id' => $row['goods_attr_id']);
                }
                $i = 0;
                $spe_array = array();
                foreach ($spe_arr AS $row) {
                    $spe_array[] = $row;
                }
                $result['error'] = ERR_NEED_SELECT_ATTR;
                $result['goods_id'] = $goods->goods_id;
                $result['warehouse_id'] = $warehouse_id;
                $result['area_id'] = $area_id;
                $result['parent'] = $goods->parent;
                $result['message'] = $spe_array;
                $result['goods_number'] = cart_number();

                die(json_encode($result));
            }
        }

        /* 更新：如果是一步购物，先清空购物车 */
        if (C('shop.one_step_buy') == '1') {
            clear_cart();
        }
        $goods_number = intval($goods->number);

        /* 检查：商品数量是否合法 */
        if (!is_numeric($goods_number) || $goods_number <= 0) {
            $result['error'] = 1;
            $result['message'] = L('invalid_number');
        }
        /* 更新：购物车 */
        else {
            // 最小起订量 start
            if(!empty($goods->spec)){
                $goods->spec = array_filter($goods->spec);
            }
            $quantity_result = get_goods_min_quantity($goods->goods_id, $goods->spec, 0, $warehouse_id, $area_id);
            if($goods_number == 1 && !empty($quantity_result['min_quantity']) && $quantity_result['min_quantity'] > 0){
                $goods_number = $quantity_result['min_quantity'];
            }
            // 最小起订量 end

            //ecmoban模板堂 --zhuo start 限购
            $xiangouInfo = get_purchasing_goods_info($goods->goods_id);
            if ($xiangouInfo['is_xiangou'] == 1) {
                $user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

                $sql = "SELECT goods_number FROM " . $this->ecs->table('cart') . "WHERE goods_id = " . $goods->goods_id . " and " . $this->sess_id;
                $cartGoodsNumInfo = $this->db->getRow($sql);//获取购物车数量

                $start_date = $xiangouInfo['xiangou_start_date'];
                $end_date = $xiangouInfo['xiangou_end_date'];
                $orderGoods = get_for_purchasing_goods($start_date, $end_date, $goods->goods_id, $user_id);

                $nowTime = gmtime();
                if ($nowTime > $start_date && $nowTime < $end_date) {
                    if ($orderGoods['goods_number'] >= $xiangouInfo['xiangou_num']) {
                        $result['error'] = 1;
                        $max_num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                        $result['message'] = L('cannot_buy');
                        die(json_encode($result));
                    } else {
                        if ($xiangouInfo['xiangou_num'] > 0) {
                            if ($cartGoodsNumInfo['goods_number'] + $orderGoods['goods_number'] + $goods_number > $xiangouInfo['xiangou_num']) {
                                $result['error'] = 1;
                                $result['message'] = L('beyond_quota_limit');
                                die(json_encode($result));
                            }
                        }
                    }
                }
            }
            //ecmoban模板堂 --zhuo end 限购
            // 更新：添加到购物车
            if (addto_cart($goods->goods_id, $goods_number, $goods->spec, $goods->parent, $warehouse_id, $area_id, $goods->store_id)) {
                if (C('shop.cart_confirm') > 2) {
                    $result['message'] = '';
                } else {
                    $result['message'] = C('shop.cart_confirm') == 1 ? L('addto_cart_success_1') : L('addto_cart_success_2');
                }
                if($goods->store_id > 0){
                    $result['store_id'] = $goods->store_id;
                    $cart_value = $GLOBALS['db']->getOne("SELECT rec_id FROM ". $GLOBALS['ecs']->table('cart')." WHERE goods_id='$goods->goods_id' AND user_id='".$_SESSION['user_id']."' AND store_id=".$goods->store_id);
                    $result['cart_value'] = $cart_value;
                }

                $result['content'] = insert_cart_info();
                $result['one_step_buy'] = C('shop.one_step_buy');
            } else {
                $result['message'] = $this->err->last_message();
                $result['error'] = $this->err->error_no;
                $result['goods_id'] = stripslashes($goods->goods_id);
                if (is_array($goods->spec)) {
                    $result['product_spec'] = implode(',', $goods->spec);
                } else {
                    $result['product_spec'] = $goods->spec;
                }
            }
        }
        $result['confirm_type'] = C('shop.cart_confirm') ? C('shop.cart_confirm') : 2;

        $result['goods_number'] = cart_number();
        die(json_encode($result));
    }



}
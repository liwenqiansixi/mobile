<?php
namespace app\http\cart\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend
{
    public $sess_id = '';
    public $a_sess = '';
    public $b_sess = '';
    public $c_sess = '';
    public $sess_cart = '';
    public $region_id = 0;
    public $area_info = array();

    /**
     * 构造，加载文件语言包和helper文件
     */
    public function __construct()
    {
        parent::__construct();

        L(require(LANG_PATH  . C('shop.lang') . '/user.php'));
        L(require(LANG_PATH  . C('shop.lang') . '/flow.php'));
        $files = array(
            'order',
        );
        $this->load_helper($files);
        //ecmoban模板堂 --zhuo start
        if (!empty($_SESSION['user_id'])) {
            $this->sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";

            $this->a_sess = " a.user_id = '" . $_SESSION['user_id'] . "' ";
            $this->b_sess = " b.user_id = '" . $_SESSION['user_id'] . "' ";
            $this->c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";

            $this->sess_cart = "";
        } else {
            $this->sess_id = " session_id = '" . real_cart_mac_ip() . "' ";

            $this->a_sess = " a.session_id = '" . real_cart_mac_ip() . "' ";
            $this->b_sess = " b.session_id = '" . real_cart_mac_ip() . "' ";
            $this->c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";

            $this->sess_cart = real_cart_mac_ip();
        }
        //ecmoban模板堂 --zhuo end
        //初始化位置信息
        $this->init_params();
        $this->assign('area_id', $this->area_info['region_id']);
        $this->assign('warehouse_id', $this->region_id);
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
                        if($cart_goods['goods_list'][$k]['goods_list'][$key]['extension_code']=='package_buy'){
                            $sql = "SELECT activity_thumb FROM {pre}goods_activity WHERE review_status = 3 AND act_id =" . $cart_goods['goods_list'][$k]['goods_list'][$key]['goods_id'] . ' and is_finished = 0';
                            $activity_thumb = $this->db->getRow($sql);
                            $cart_goods['goods_list'][$k]['goods_list'][$key]['goods_thumb'] = get_image_path($activity_thumb['activity_thumb']);
                        }
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
     * 优惠活动
     */
    function actionActivity()
    {
        $act_id = I('act_id','','intval');
        $sql = "SELECT * FROM {pre}favourable_activity WHERE review_status = 3 AND act_id=" . $act_id;
        $obj = $this->db->getRow($sql);
        $list = unserialize($obj['gift']);
        foreach ($list as $key => $v) {
            $sql = "SELECT * FROM {pre}goods WHERE goods_id=" . $v['id'] . ' and is_on_sale=1 and is_delete=0 and goods_number>0';
            $info = $this->db->getRow($sql);
            if ($info) {
                //根据商品模式判断当前库存
                if ((int)$info['model_attr'] === 1) {
                    $sql = "SELECT region_number FROM {pre}warehouse_goods WHERE region_id=" . $this->region_id;
                    $number = $this->db->getRow($sql);
                    $goods_number = $number['region_number'];
                } elseif ((int)$info['model_attr'] === 2) {
                    $sql = "SELECT region_number FROM {pre}warehouse_area_goods WHERE region_id=" . $this->area_info['region_id'];
                    $number = $this->db->getRow($sql);
                    $goods_number = $number['region_number'];
                } else {
                    $goods_number = $info['goods_number'];
                }
                $list[$key]['goods_id'] = $v['id'];
                $list[$key]['goods_img'] = get_image_path($info['goods_thumb']);
                $list[$key]['goods_name'] = $v['name'];
                $list[$key]['goods_number'] = $goods_number;
                $list[$key]['url'] = build_uri('goods', array('gid' => $v['id']));
                $list[$key]['act_price'] = $v['price'];
                $list[$key]['price'] = price_format($v['price']);
                if ((int)$goods_number === 0) {
                    unset($list[$key]);
                }
            } else {
                unset($list[$key]);
            }
        }
        $this->assign('page_title', '赠品列表');
        $this->assign('act_id', $act_id);
        $this->assign('list', $list);
        $this->display();
    }

    /*
     *
    * 添加优惠活动（赠品）到购物车
    * @param   int     $act_id     优惠活动id
    * @param   int     $id         赠品id
    * @param   float   $price      赠品价格
    */
    function actionAddGiftToCart()
    {
        $act_id = I('act_id','','intval');
        $id = I('id','','intval');
        $price = I('price');
        //ecmoban模板堂 --zhuo start
        $sess = $this->sess_cart;
        /** 取得优惠活动信息 */
        $act_id = intval($_POST['act_id']);
        $favourable = favourable_info($act_id);
        if (empty($favourable))
        {
//            $result['error'] = 1;
            $result['error'] = L('favourable_not_exist');
            die(json_encode($result));
        }

        /** 判断用户能否享受该优惠 */
        if (!favourable_available($favourable))
        {
//            $result['error'] = 2;
            $result['error'] = L('favourable_not_available');
            die(json_encode($result));
        }

        /** 检查购物车中是否已有该优惠 */
        $cart_favourable = cart_favourable();
        if (favourable_used($favourable, $cart_favourable))
        {
//            $result['error'] = 3;
            $result['error'] = L('favourable_used');
            die(json_encode($result));
        }
        /** 检查赠品是否已在购物车*/
        if(!empty($_SESSION['user_id'])){
            $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        }else{
            $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        }
        $sql = "SELECT goods_name" .
            " FROM {pre}cart" .
            " WHERE " . $sess_id .
            " AND rec_type = '" . CART_GENERAL_GOODS . "'" .
            " AND is_gift = '$act_id'" .
            " AND goods_id = " . $id;
        $gift_name = $this->db->getCol($sql);
        if (!empty($gift_name))
        {
            $result['error'] = sprintf(L('gift_in_cart'), join(',', $gift_name));
            die(json_encode($result));
        }

        /** 添加赠品到购物车 */
        $sql = "INSERT INTO {pre}cart (" .
            "user_id, session_id, goods_id, goods_sn, goods_name, market_price, goods_price, " .
            "goods_number, is_real, extension_code, parent_id, is_gift, rec_type, ru_id ) " .
            "SELECT $_SESSION[user_id], '" . $sess . "', goods_id, goods_sn, goods_name, market_price, " .
            "'$price', 1, is_real, extension_code, 0, '$act_id', '" . CART_GENERAL_GOODS . "', user_id " .
            "FROM {pre}goods" .
            " WHERE goods_id = '$id'";
        if ($this->db->query($sql)) {
            $info['error'] = L('in_shopping_cart');
            die(json_encode($info));
        }
    }

    /**
     * 相关配件
     */
    public function actionGoodsFittings()
    {

        $goods_list = explode(',', I('goods_list'));

        $fittings_list = get_goods_fittings($goods_list);

        if (empty($fittings_list)) {

            show_message(L('no_accessories'));
            exit();
        }
        $this->assign('fittings_list', $fittings_list);
        $this->display('activity');

    }

    /*
    * 商品属性
    */
    public function actionGoodsTranslation($id)
    {

        $sql = 'SELECT sales_volume, goods_id, goods_name, goods_number, promote_start_date, promote_end_date, is_promote, market_price, promote_price, shop_price, goods_thumb, market_price
                FROM {pre}goods WHERE goods_id=' . $id;

        $get = $this->db->getRow($sql);

        $properties = get_goods_properties($id);

        $info = $this->good_info_array($get, $properties);

        return $info;

    }

    public function good_info_array($get, $properties)
    {

        $info = get_goods_info($get['goods_id'], $this->region_id, $this->area_info['region_id']);
        $properties = get_goods_properties($get['goods_id'], $this->region_id, $this->area_info['region_id']);
        foreach ($properties['spe'] as $key => $val) {
            $checked = 1;
            if (count($val) > 2) {

                foreach ($val['values'] as $k => $v) {
                    if ($v['checked'] == 1) {
                        $checked = 0;
                    }
                }
                if ($checked) {
                    foreach ($val['values'] as $k => $v) {
                        if ($k == 0) {
                            $properties['spe'][$key]['values'][$k]['checked'] = 1;
                        }
                    }
                }
            }
        }
        $info['spe'] = $properties['spe'];
        return $info;
    }

    /*
     * 推荐商品
     */
    public function relation_goods($warehouse_id = 0, $area_id = 0)
    {
        $where = " g.is_on_sale = 1 AND g.is_alone_sale = 1 AND " . "g.is_delete = 0 AND g.review_status > 2 ";
        $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";

        $leftJoin = " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

        if($GLOBALS['_CFG']['open_area_goods'] == 1){
            $leftJoin .= " left join " .$GLOBALS['ecs']->table('link_area_goods'). " as lag on g.goods_id = lag.goods_id ";
            $where .= " and lag.region_id = '$area_id' ";
        }

        $sql = 'SELECT g.goods_id, g.user_id, g.goods_name, ' .$shop_price. ' g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot, ' .
            ' IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, ' .
            ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, g.model_price, ' .
            "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
            "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, g.goods_type, " .
            'g.promote_start_date, g.promote_end_date, g.is_promote, g.goods_brief, g.goods_thumb , g.goods_img ' .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
            $leftJoin.
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            "WHERE $where ORDER BY g.click_count desc, g.goods_id desc LIMIT 12";
        $result = $GLOBALS['db']->getAll($sql);

        $info = array();
        foreach ($result as $row) {
            $goods_list[] = $this->actionGoodsTranslation($row['goods_id']);
        }
        foreach ($goods_list as $key => $val) {
            $val['promote_price'] = str_replace('¥', '', $val['promote_price']);
            if ($val['promote_price'] > 0) {
                $promote_price = bargain_price($val['promote_price'], $val['promote_start_date'], $val['promote_end_date']);
            } else {
                $promote_price = 0;
            }
            /**
             * 重定义商品价格
             * 商品价格 + 属性价格
             * start
             */
            $price_other = array(
                'market_price' => $val['market_price'],
                'org_price' => $val['org_price'],
                'shop_price' => $val['shop_price'],
                'promote_price' => $promote_price,
            );

            $price_info = get_goods_one_attr_price($val['goods_id'], $warehouse_id, $area_id, $price_other);
            //$val = !empty($val) ? array_merge($val, $price_info) : $val;
            $promote_price = $price_info['promote_price'];
            /**
             * 重定义商品价格
             * end
             */
            $time = time();
            // $goods_list[$key]['goods_img'] = get_image_path($val['goods_img']);
            // $goods_list[$key]['goods_thumb'] = get_image_path($val['goods_thumb']);
            $goods_list[$key]['shop_price'] = price_format($val['shop_price']);
            $goods_list[$key]['shop_price_formated'] = price_format($val['shop_price']);
            $goods_list[$key]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
            //$goods_list[$key]['url'] = build_uri('goods', array('gid'=>$val['goods_id']));;
            $goods_list[$key]['url'] = build_uri('goods', array('gid' => $val['goods_id'],'u'=>$_SESSION['user_id']));
            if ($time > $val['promote_start_date'] && $time < $val['promote_end_date'] && $val['is_promote']==1) {
                $goods_list[$key]['current_price'] = strip_tags($val['promote_price']);
            } else {
                $goods_list[$key]['current_price'] = price_format($val['shop_price']);
            }
            if (empty($val['promote_start_date'])|| empty($val['promote_end_date'])) {
                $goods_list[$key]['current_price'] = price_format($val['shop_price']);
            }
        }
        return $goods_list;
    }

    /*
     * 购物车商品数量修改
     */
    public function actionCartGoodsNumber()
    {
        if(IS_AJAX){
            $rec_id = I('id', '','intval');
            $goods_number = I('number', '');
            $none = I('none', '');
            $arr = I('arr', '');
            $act_id= I('act_id', '');
            if (!empty($arr)) {
                $arr = substr($arr, 0, strlen($arr) - 1);
            }
            $sql = "SELECT `goods_id`, `goods_attr_id`,`product_id`, `extension_code`, `warehouse_id`, `area_id` FROM" .$GLOBALS['ecs']->table('cart').
                " WHERE rec_id='$rec_id' AND " . $this->sess_id;
            $goods =$GLOBALS['db']->getRow($sql);

            $warehouse_id = intval($goods['warehouse_id']);
            $area_id = intval($goods['area_id']);
            $attr_id = explode(',', $goods['goods_attr_id']);
            $goods_id = intval($goods['goods_id']);
            /* 处理超值礼包 */
            if ($goods['extension_code'] !== 'package_buy'){
                if($GLOBALS['_CFG']['add_shop_price'] == 1){
                    $add_tocart = 1;
                }else{
                    $add_tocart = 0;
                }
                $shopprice = get_final_price($goods_id, $goods_number, true, $attr_id, $warehouse_id, $area_id, 0, 0, $add_tocart);
                $result['shop_price'] = price_format($shopprice);
            }
            $leftJoin = "";
            //ecmoban模板堂 --zhuo start
            $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wg.region_number as wg_number, wag.region_price, wag.region_promote_price, wag.region_number as wag_number, ";
            $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
            $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
            //ecmoban模板堂 --zhuo end

            $sql = "SELECT g.goods_name," .$shop_price. " g.model_price, g.model_inventory, g.model_attr, g.goods_number, g.group_number, " .
                "c.group_id, c.extension_code, c.goods_name AS act_name " .
                "FROM ".$GLOBALS['ecs']->table('goods'). " AS g left join ".
                $GLOBALS['ecs']->table('cart'). " AS c on g.goods_id =c.goods_id ".
                $leftJoin .
                "WHERE c.rec_id = '$rec_id'";
            $row = $GLOBALS['db']->getRow($sql);
            //ecmoban模板堂 --zhuo start 限购
            $xiangouInfo = get_purchasing_goods_info($goods['goods_id']);

            if($xiangouInfo['is_xiangou'] == 1){
                $user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
                $start_date = $xiangouInfo['xiangou_start_date'];
                $end_date = $xiangouInfo['xiangou_end_date'];
                $orderGoods = get_for_purchasing_goods($start_date, $end_date, $goods['goods_id'], $user_id);
                $nowTime = gmtime();
                if ($row['goods_number'] < $goods_number) {
                    $goods_number = $row['goods_number'];
                    $result['error'] = 1;
                    $result['msg'] =sprintf(L('stock_insufficiency'), $row['goods_name'],$row['goods_number'], $row['goods_number']);
                }
                if($nowTime > $start_date && $nowTime < $end_date){
                    if($orderGoods['goods_number'] >= $xiangouInfo['xiangou_num']){
                        $result['msg'] = "该" . $row['goods_name'] . L('cannot_buy');
                        $result['num'] = $goods_number;
                        //更新购物车中的商品数量
                        $sql = "UPDATE " .$GLOBALS['ecs']->table('cart').
                            " SET goods_number = 0 WHERE rec_id='$rec_id'";
                        $GLOBALS['db']->query($sql);
                        $result['error']   = 1;
                        die(json_encode($result));
                    }else{
                        if($xiangouInfo['xiangou_num'] > 0){
                            if($xiangouInfo['is_xiangou'] == 1 && $orderGoods['goods_number'] + $goods_number > $xiangouInfo['xiangou_num']){
                                $result['msg'] = "该" . $row['goods_name'] . '商品已经累计超过限购数量';

                                //更新购物车中的商品数量
                                $cart_Num = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
                                $sql = "UPDATE " .$GLOBALS['ecs']->table('cart').
                                    " SET goods_number = '$cart_Num' WHERE rec_id='$rec_id'";
                                $GLOBALS['db']->query($sql);
                                $result['error']   = 1;
                                $result['num']   = $cart_Num;
                                die(json_encode($result));
                            }
                        }
                    }
                }
                //ecmoban模板堂 --zhuo end 限购
            }else{
                //查询：系统启用了库存，检查输入的商品数量是否有效
                if(intval($GLOBALS['_CFG']['use_storage']) > 0 &&$goods['extension_code'] != 'package_buy'){
                    //ecmoban模板堂 --zhuo start
                    if($row['model_inventory'] == 1){
                        $row['goods_number'] = $row['wg_number'];
                    }elseif($row['model_inventory'] == 2){
                        $row['goods_number'] = $row['wag_number'];
                    }
                    //ecmoban模板堂 --zhuo end
                    /* 是货品*/
                    $goods['product_id'] = trim($goods['product_id']);
                    if (!empty($goods['product_id'])){
                        //ecmoban模板堂 --zhuo start
                        if($row['model_attr'] == 1){
                            $table_products = "products_warehouse";
                        }elseif($row['model_attr'] == 2){
                            $table_products = "products_area";
                        }else{
                            $table_products = "products";
                        }
                        //ecmoban模板堂 --zhuo end
                        $sql = "SELECT product_number FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$goods['goods_id']. "' and product_id = '" .$goods['product_id'] ."' LIMIT 1";
                        $product_number =$GLOBALS['db']->getOne($sql);

                        if ($product_number < $goods_number) {
                            $goods_number = $product_number;
                            $result['error'] = 2;
                            $result['msg'] =sprintf(L('stock_insufficiency'), $row['goods_name'], $product_number, $product_number);
                        }
                    }else{
                        if ($row['goods_number'] < $goods_number) {
                            $goods_number = $row['goods_number'];
                            $result['error'] = 1;
                            $result['msg'] =sprintf(L('stock_insufficiency'), $row['goods_name'],$row['goods_number'], $row['goods_number']);
                        }
                    }
                }
                elseif (intval($GLOBALS['_CFG']['use_storage'])> 0 && $goods['extension_code'] == 'package_buy') {
                    if(judge_package_stock($goods['goods_id'], $goods_number)){
                        $result['error'] = 3;
                        $result['msg'] = L('package_stock_insufficiency');
                    }
                }
            }

            /* 处理超值礼包 */
            if ($goods['extension_code'] == 'package_buy')
            {
                //更新购物车中的商品数量
                $sql = "UPDATE {pre}cart SET goods_number='$goods_number' WHERE rec_id='$rec_id'";
            }
            /* 处理普通商品或非优惠的配件 */
            else
            {
                $sql = "UPDATE {pre}cart SET goods_price = '$shopprice', goods_number='$goods_number' WHERE rec_id='$rec_id'";
            }
            if ($this->db->query($sql)) {
                $sql = "SELECT goods_id, goods_price, goods_number, parent_id FROM {pre}cart WHERE rec_id in (" . $arr . ")";
                $count = $this->db->getAll($sql);
                $count_price = 0;
                foreach ($count as $key) {
//                    $count_price += floatval($key['goods_number']) * floatval($key['goods_price']);
                    $goods_amount = floatval($key['goods_number']) * floatval($key['goods_price']);

                    $goods_con = get_con_goods_amount($goods_amount, $key['goods_id'], 0, 0, $key['parent_id']);

                    $goods_con['amount'] = explode(',', $goods_con['amount']);
                    $count_price += min($goods_con['amount']);
                }
                /* 计算折扣 */
                $discount = compute_discount(3);
                $fav_amount = $discount['discount'];
                $count_price = $count_price - $fav_amount;

                $result['error'] = 0;
                $result['content'] = price_format($count_price);
            } else {
                $result['error'] = 1;
                $data['msg'] = '#ADD_NO';
            }

            if($act_id>0) {
             // 当优惠活动商品不满足最低金额时-删除赠品
                if (!empty($_SESSION['user_id']))
                    $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
                else
                    $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
                $favourable = favourable_info($act_id);
                $favourable_available = favourable_available($favourable);
                if (!$favourable_available) {
                    $sql = "SELECT rec_id FROM {pre}cart WHERE " . $sess_id . " AND is_gift = '$act_id'";
                    $result['remove_rec_id'] = $this->db->getCol($sql);

                    //
                    $sql = "DELETE FROM {pre}cart WHERE " . $sess_id . " AND is_gift = '$act_id'";
                    $this->db->query($sql);
                    $result['is_show_favourable'] = 0;
                }else{
                    $result['is_show_favourable'] = 1;
                }
            }
            $result['num'] = $goods_number;
            $result['max_number'] = $row['goods_number'] > $product_number?$row['goods_number']:$product_number;
            $result['none'] = $none;
            die(json_encode($result));
        }
    }

    /*
     * label选中价格
     */
    public function actionCartLabelCount()
    {

        $rec_id = I('id', '');
        $rec_id = str_replace('undefined', '', $rec_id);
        $rec_id = substr($rec_id, 0, str_len($rec_id) - 1);
        if ($rec_id) {
            $sql = "SELECT rec_id, goods_price, goods_number FROM {pre}cart WHERE rec_id in ($rec_id)";

            $count = $this->db->getAll($sql);
        }

        $num = 0;
        if (count($count) > 0) {
            foreach ($count as $key) {
                $count_price += floatval($key['goods_number']) * floatval($key['goods_price']);
                $num += $key['goods_number'];
            }
        } else {
            $count_price = '0.00';
        }

        $result['content'] = price_format($count_price);
        $result['cart_number'] = $num;

        die(json_encode($result));
    }

    /*
     * 优惠卷
     */
    public function actionCartBonus()
    {
        $ru_id = I('ru_id', '', 'intval');
        if (IS_INT($ru_id)) {
            //该店铺是否存在优惠卷
            $bonus = $this->db->getAll("SELECT cou_id, cou_name, cou_money, cou_start_time,cou_end_time,  cou_man  FROM {pre}coupons WHERE (( instr(`cou_ok_user`, $_SESSION[user_rank]) ) or (`cou_ok_user`=0)) AND review_status = 3 AND ru_id=" . $ru_id . " AND cou_end_time>" . time());

            $str = '<ul>';
            foreach ($bonus as $key) {
                $num = 1;
                if ($key['cou_money'] >= 0) {
                    if ($key['cou_money'] >= 50) {
                        if ($key['cou_money'] >= 100) {
                            $num = 1;
                        } else {
                            $num = 2;
                        }
                    } else {
                        $num = 3;
                    }
                } else {
                    $num = 3;
                }
                if($_SESSION['user_id']){
                    $pan .= "onclick='javascript:receivebonus(" . $key['cou_id'] . ")'";
                }else{
                    $pan .="";
                }
                $key['cou_money'] = round($key['cou_money']);
                $key['cou_man'] = round($key['cou_man']);
                $str .= "<li class='dis-box big-remark-all'>
                            <div class='box-flex remark-all temark-" . $num . "'>
                                <p>
                                    <span class='b-r-a-price fl'><sup>¥</sup>" . $key['cou_money'] . "</span>
                                    <span class='b-r-a-con fl text-left '><em>优惠券</em><em>满" . $key['cou_man'] . "元可使用</em></span>
                                </p>
                                <p class='text-left b-r-a-time'>使用期限：" . date('Y.m.d', $key['cou_start_time']) . " ~ " . date('Y.m.d', $key['cou_end_time']) . "</p>
                            </div>
                            <a href='#' class='ts-1active b-r-a-btn b-color-f temark-" . $num . "-text tb-lr-center' bonus-id='" . $key['cou_id'] . "' cou_id='" . $key['cou_id'] . "' " . $pan . " >立即<br />领取</a>
                         </li>";
            }
            $str .= '</ul>';

            $result['number'] = count($bonus);
            $result['data'] = $str;

            die(json_encode($result));
        }

        $result['number'] = 0;
        $result['data'] = 0;
        die(json_encode($result));
    }

    /*
     * 领取优惠卷
     */
    public function actionReceiveBonus()
    {
        $bonus_id = I('bonus_id', '','intval');
        //不考虑红包编号重复
        if ($_SESSION['user_id'] > 0) {
            $time = gmtime();
            $res = $this->db->getRow("SELECT type_name FROM {pre}bonus_type WHERE send_start_date < '$time' and type_id='$bonus_id' and send_end_date > " . $time);
            if ($res) {
                $number = $this->db->getRow("SELECT user_id FROM {pre}user_bonus WHERE bonus_type_id='$bonus_id'  and user_id='$_SESSION[user_id]' ");
            }
            if (count($number) == 0 && isset($number)) {
                $res2 = $this->db->getRow("SELECT bonus_id FROM {pre}user_bonus WHERE bonus_type_id='$bonus_id'  and user_id= 0 ");
                if ($res2) {
                    $error = $this->db->query("update {pre}user_bonus set user_id = $_SESSION[user_id],bind_time = $time where  user_id = 0 and bonus_type_id = '$bonus_id' limit 1");
                    if ($error) {
                        $result['msg'] = L('coupon_in_account');
                        $result['code'] = 0;
                    }
                } else {
                    $result['msg'] = L('no_coupon');
                    $result['code'] = 0;
                }
            } else {
                $result['msg'] = L('already_receive_coupons');
                $result['code'] = 0;
            }
        } else {
            $result['msg'] = L('yet_login');
            $result['code'] = 1;
        }
        die(json_encode($result));
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
                $result['url'] = url('site');
                die(json_encode($result));
            }
        }
        if (empty($goods)) {
            $result['error'] = 1;
            $result['url'] = url('site');
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
        } /* 更新：购物车 */
        else {
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


    // 更新购物车

    public function actionUpdate_cart()
    {

        //格式化返回数组
        $result = array(
            'error' => 0,
            'message' => ''
        );
        // 是否有接收值
        if (isset($_POST ['rec_id']) && isset($_POST ['goods_number'])) {
            $key = intval($_POST ['rec_id']);
            $val = $_POST ['goods_number'];
            $val = intval(make_semiangle($val));
            if ($val <= 0 && !is_numeric($key)) {
                $result ['error'] = 99;
                $result ['message'] = '';
                die(json_encode($result));
            }
            // 查询：
            $condition['rec_id'] = $key;
            $condition['session_id'] = SESS_ID;
            $goods = $this->db->table('cart')->field('goods_id,goods_attr_id,product_id,extension_code')->where($condition)->find();

            $sql = "SELECT g.goods_name,g.goods_number " . "FROM {pre}goods AS g, {pre}cart AS c " . "WHERE g.goods_id =c.goods_id AND c.rec_id = '$key'";
            $res = $this->db->query($sql);
            $row = $res[0];
            // 查询：系统启用了库存，检查输入的商品数量是否有效
            if (intval(C('shop.use_storage')) > 0 && $goods ['extension_code'] != 'package_buy') {
                if ($row ['goods_number'] < $val) {
                    $result ['error'] = 1;
                    $result ['message'] = sprintf(L('stock_insufficiency'), $row ['goods_name'], $row ['goods_number'], $row ['goods_number']);
                    $result ['err_max_number'] = $row ['goods_number'];
                    die(json_encode($result));
                }
                /* 是货品 */
                $goods ['product_id'] = trim($goods ['product_id']);
                if (!empty($goods ['product_id'])) {
                    $condition = " goods_id = '" . $goods ['goods_id'] . "' AND product_id = '" . $goods ['product_id'] . "'";
                    $product_number = $this->db->table('products')->field('product_number')->where($condition)->find();
                    $product_number = $product_number['product_number'];
                    if ($product_number < $val) {
                        $result ['error'] = 2;
                        $result ['message'] = sprintf(L('stock_insufficiency'), $row ['goods_name'], $product_number, $product_number);
                        die(json_encode($result));
                    }
                }
            } elseif (intval(C('shop.use_storage')) > 0 && $goods ['extension_code'] == 'package_buy') {
                if (judge_package_stock($goods ['goods_id'], $val)) {
                    $result ['error'] = 3;
                    $result ['message'] = L('package_stock_insufficiency');
                    die(json_encode($result));
                }
            }
            /* 查询：检查该项是否为基本件 以及是否存在配件 */
            /* 此处配件是指添加商品时附加的并且是设置了优惠价格的配件 此类配件都有parent_idgoods_number为1 */
            $sql = "SELECT b.goods_number,b.rec_id
            FROM {pre}cart a, {pre}cart b
                WHERE a.rec_id = '$key'
                AND a.session_id = '" . SESS_ID . "'
            AND a.extension_code <>'package_buy'
            AND b.parent_id = a.goods_id
            AND b.session_id = '" . SESS_ID . "'";

            $offers_accessories_res = $this->db->getAll($sql);

            // 订货数量大于0
            if ($val > 0) {
                /* 判断是否为超出数量的优惠价格的配件 删除 */
                $row_num = 1;
                foreach ($offers_accessories_res as $offers_accessories_row) {
                    if ($row_num > $val) {
                        $where['session_id'] = SESS_ID;
                        $where['rec_id'] = $offers_accessories_row ['rec_id'];
                        $this->db->table('cart')->where()->delete();
                    }

                    $row_num++;
                }

                /* 处理超值礼包 */
                if ($goods ['extension_code'] == 'package_buy') {
                    // 更新购物车中的商品数量
                    $sql = "UPDATE {pre}cart SET goods_number= '$val' WHERE rec_id='$key' AND session_id='" . SESS_ID . "'";
                } /* 处理普通商品或非优惠的配件 */
                else {
                    if($GLOBALS['_CFG']['add_shop_price'] == 1){
                        $add_tocart = 1;
                    }else{
                        $add_tocart = 0;
                    }

                    $attr_id = empty($goods ['goods_attr_id']) ? array() : explode(',', $goods ['goods_attr_id']);
                    $goods_price = get_final_price($goods ['goods_id'], $val, true, $attr_id, $_POST['warehouse_id'], $_POST['area_id'], 0, 0, $add_tocart);

                    // 更新购物车中的商品数量
                    $sql = "UPDATE {pre}cart SET goods_number= '$val', goods_price = '$goods_price' WHERE rec_id='$key' AND session_id='" . SESS_ID . "'";
                }
            }  // 订货数量等于0
            else {
                /* 如果是基本件并且有优惠价格的配件则删除优惠价格的配件 */
                foreach ($offers_accessories_res as $offers_accessories_row) {
                    $where['session_id'] = SESS_ID;
                    $where['rec_id'] = $offers_accessories_row ['rec_id'];
                    $this->db->table('cart')->where()->delete();
                }

                $sql = "DELETE FROM {pre}cart WHERE rec_id='$key' AND session_id='" . SESS_ID . "'";
            }
            $this->db->query($sql);
            /* 删除所有赠品 */
            $sql = "DELETE FROM {pre}cart WHERE session_id = '" . SESS_ID . "' AND is_gift <> 0";
            $this->db->query($sql);

            $result ['rec_id'] = $key;
            $result ['goods_number'] = $val;
            $result ['goods_subtotal'] = '';
            $result ['total_desc'] = '';
            $result ['cart_info'] = insert_cart_info();
            /* 计算合计 */
            $cart_goods = get_cart_goods();
            foreach ($cart_goods ['goods_list'] as $goods) {
                if ($goods ['rec_id'] == $key) {
                    $result ['goods_subtotal'] = $goods ['subtotal'];
                    break;
                }
            }
            $market_price_desc = sprintf(L('than_market_price'), $cart_goods ['total'] ['market_price'], $cart_goods ['total'] ['saving'], $cart_goods ['total'] ['save_rate']);
            /* 计算折扣 */
            $discount = compute_discount();
            $favour_name = empty($discount ['name']) ? '' : join(',', $discount ['name']);
            $your_discount = sprintf('', $favour_name, price_format($discount ['discount']));
            $result ['total_desc'] = $cart_goods ['total'] ['goods_price'];
            $result ['total_number'] = $cart_goods ['total'] ['total_number'];
            $result['market_total'] = $cart_goods['total']['market_price'];//市场价格
            die(json_encode($result));
        } else {
            $result ['error'] = 100;
            $result ['message'] = '';
            die(json_encode($result));
        }
    }

    /*
     * 购物车关注
     */
    public function actionHeart()
    {
        if ($_SESSION['user_id'] > 0) {
            $id = I('id', '','');
            $status = I('status','','intval');
            $id = explode(',', substr($id, 0, str_len($id) - 1));
            foreach ($id as $key) {
                if ($key != 'undefined') {
                    $arr[] = $key;
                }
            }
            if (count($arr) > 0) {
                if ($status % 2) {
                    foreach ($arr as $key) {
                        $sql = "SELECT count(rec_id) as a FROM {pre}collect_goods WHERE user_id=" . $_SESSION['user_id'] . ' AND goods_id=' . $key;
                        $info = $this->db->getOne($sql);
                        if ($info < 1) {
                            $sql = 'INSERT INTO {pre}collect_goods (user_id,goods_id,add_time,is_attention) VALUES(' . $_SESSION['user_id'] . ',' . $key . ',' . time() . ',1)';
                            $this->db->query($sql);
                        }
                    }
                    die(json_encode(array('msg' => L('already_attention_check_shop'), 'error' => 1)));
                } else {
                    $sql = "DELETE FROM {pre}collect_goods WHERE user_id=" . $_SESSION['user_id'] . ' AND goods_id in(' . implode(',', $arr) . ')';
                    $this->db->query($sql);
                    die(json_encode(array('msg' => L('cancel_attention'), 'error' => 2)));
                }
            } else {
                die(json_encode(array('msg' => 'Attention NO', 'error' => 0)));
            }
        }
        die(json_encode(array('msg' => L('yet_login'), 'error' => 0)));
    }

    /**
     * 删除购物车中的商品
     */
    public function actionDropGoods()
    {
        if (IS_AJAX) {
            $id = I('id');
            $id = explode(',', substr($id, 0, str_len($id) - 1));
            foreach ($id as $key) {
                if ($key != 'undefined') {
                    $arr[] = $key;
                }
            }
            if (count($arr) > 0) {
                foreach ($arr as $key) {
                    flow_drop_cart_goods($key);
                }
            }
            die;
        }
    }

    public function actionDeleteCart()
    {
        if (IS_AJAX) {
            $rec_id = I('id','','intval');
            if($rec_id){
                $result = flow_drop_cart_goods($rec_id);
                if($result){
                    $arr = array('error' => 0);
                }else{
                    $arr = array('error' => 1);
                }
            }

            die(json_encode($arr));
        }

    }

    /**
     * 初始化参数
     */
    private function init_params()
    {
        #需要查询的IP start

        if (!isset($_COOKIE['province'])) {
            $area_array = get_ip_area_name();

            if ($area_array['county_level'] == 2) {
                $date = array('region_id', 'parent_id', 'region_name');
                $where = "region_name = '" . $area_array['area_name'] . "' AND region_type = 2";
                $city_info = get_table_date('region', $where, $date, 1);

                $date = array('region_id', 'region_name');
                $where = "region_id = '" . $city_info[0]['parent_id'] . "'";
                $province_info = get_table_date('region', $where, $date);

                $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
                $district_info = get_table_date('region', $where, $date, 1);

            } elseif ($area_array['county_level'] == 1) {
                $area_name = $area_array['area_name'];

                $date = array('region_id', 'region_name');
                $where = "region_name = '$area_name'";
                $province_info = get_table_date('region', $where, $date);

                $where = "parent_id = '" . $province_info['region_id'] . "' order by region_id asc limit 0, 1";
                $city_info = get_table_date('region', $where, $date, 1);

                $where = "parent_id = '" . $city_info[0]['region_id'] . "' order by region_id asc limit 0, 1";
                $district_info = get_table_date('region', $where, $date, 1);
            }
        }
        #需要查询的IP end
        $order_area = get_user_order_area($this->user_id);
        $user_area = get_user_area_reg($this->user_id); //2014-02-25

        if ($order_area['province'] && $this->user_id > 0) {
            $this->province_id = $order_area['province'];
            $this->city_id = $order_area['city'];
            $this->district_id = $order_area['district'];
        } else {
            //省
            if ($user_area['province'] > 0) {
                $this->province_id = $user_area['province'];
                cookie('province', $user_area['province']);
                $this->region_id = get_province_id_warehouse($this->province_id);
            } else {
                $sql = "select region_name from " . $this->ecs->table('region_warehouse') . " where regionId = '" . $province_info['region_id'] . "'";
                $warehouse_name = $this->db->getOne($sql);

                $this->province_id = $province_info['region_id'];
                $cangku_name = $warehouse_name;
                $this->region_id = get_warehouse_name_id(0, $cangku_name);
            }
            //市
            if ($user_area['city'] > 0) {
                $this->city_id = $user_area['city'];
                cookie('city', $user_area['city']);
            } else {
                $this->city_id = $city_info[0]['region_id'];
            }
            //区
            if ($user_area['district'] > 0) {
                $this->district_id = $user_area['district'];
                cookie('district', $user_area['district']);
            } else {
                $this->district_id = $district_info[0]['region_id'];
            }
        }

        $this->province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : $this->province_id;

        $child_num = get_region_child_num($this->province_id);
        if ($child_num > 0) {
            $this->city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : $this->city_id;
        } else {
            $this->city_id = '';
        }

        $child_num = get_region_child_num($this->city_id);
        if ($child_num > 0) {
            $this->district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : $this->district_id;
        } else {
            $this->district_id = '';
        }

        $this->region_id = !isset($_COOKIE['region_id']) ? $this->region_id : $_COOKIE['region_id'];
        $goods_warehouse = get_warehouse_goods_region($this->province_id); //查询用户选择的配送地址所属仓库
        if ($goods_warehouse) {
            $this->regionId = $goods_warehouse['region_id'];
            if ($_COOKIE['region_id'] && $_COOKIE['regionid']) {
                $gw = 0;
            } else {
                $gw = 1;

            }
        }
        if ($gw) {
            $this->region_id = $this->regionId;
            cookie('area_region', $this->region_id);
        }

        cookie('goodsId', $this->goods_id);

        $sellerInfo = get_seller_info_area();
        if (empty($this->province_id)) {
            $this->province_id = $sellerInfo['province'];
            $this->city_id = $sellerInfo['city'];
            $this->district_id = 0;

            cookie('province', $this->province_id);
            cookie('city', $this->city_id);
            cookie('district', $this->district_id);

            $this->region_id = get_warehouse_goods_region($this->province_id);
        }
        //ecmoban模板堂 --zhuo end 仓库
        $this->area_info = get_area_info($this->province_id);
    }


}
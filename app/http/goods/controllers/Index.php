<?php
namespace app\http\goods\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend {
    public $user_id = 0;
    public $goods_id = 0;
    public $region_id = 0;
    public $area_info = array();

    public function __construct(){
        parent::__construct();
        L(require(LANG_PATH  . C('shop.lang') . '/goods.php'));
        $this->size = 10;
        $this->goods_id = I('id', 0 ,'intval');
        if($this->goods_id == 0 ){
            /* 如果没有传入id则跳回到首页 */
            ecs_header("Location: ./\n");
            exit;
        }
        $this->user_id = !empty($_SESSION['user_id'])? $_SESSION['user_id'] : 0;
        $this->assign('goods_id', $this->goods_id); //商品ID
        //初始化位置信息
        $this->init_params();
        $this->assign('custom',  C(custom)); //原分销
    }

    public function actionIndex()
    {
        //ecmoban模板堂 --zhuo start 仓库
        $pid = I('request.pid', 0, 'intval');
        $storeId = I('request.store_id', 0, 'intval');
        //添加门店ID判断
        if(!empty($storeId)){
            $_SESSION['store_id'] = $storeId;
        }else{
            unset($_SESSION['store_id']);
        }
        //添加门店ID判断
        //ecmoban模板堂 --zhuo end 仓库

        /* 清空配件购物车 */
        if(!empty($_SESSION['user_id'])){
            $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        }else{
            $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        }
        $goods = get_goods_info($this->goods_id, $this->region_id, $this->area_info['region_id']);

        //分销跳转
        if(empty($goods['user_id']) && !empty($this->user_id) && isset($_GET['u']) === FALSE){
            $this->redirect('goods/index/index', array('id'=>$this->goods_id, 'u'=>$this->user_id));
        }
        if (is_dir(APP_DRP_PATH)) {
            $isdrp = $this->model->table('drp_config')->field('value')->where(array('code' => 'isdrp'))->find();
            $sql="SELECT id FROM {pre}drp_shop WHERE audit=1 AND status=1 AND user_id=".$this->user_id;
            $drp=$this->db->getOne($sql);

            $this->assign('drp', $drp);
            $this->assign('isdrp', $isdrp['value']);
        }
        if ($goods === false || !isset($goods['goods_name'])){
            /* 如果没有找到任何记录则跳回到首页 */
            ecs_header("Location: ./\n");
            exit;
        }

        if($this->area_info['region_id'] == NULL){
            $this->area_info['region_id'] = 0;
        }
        //商品服务
        $is_reality = get_goods_extends($this->goods_id);
        $this->assign('is_reality', $is_reality);
        $this->assign('id',           $this->goods_id);
        $this->assign('type',         0);
        $this->assign('cfg',          C('shop'));
        $this->assign('promotion',  get_promotion_info($this->goods_id, $goods['user_id']));//促销信息
        $this->assign('promotion_info', get_promotion_info('', $goods['user_id']));

        //ecmoban模板堂 --zhuo start 限购
        $start_date = $goods['xiangou_start_date'];
        $end_date = $goods['xiangou_end_date'];

        $nowTime = gmtime();
        if($nowTime > $start_date && $nowTime < $end_date){
            $xiangou = 1;
        }else{
            $xiangou = 0;
        }

        $order_goods = get_for_purchasing_goods($start_date, $end_date, $this->goods_id, $this->user_id);
        $this->assign('xiangou', $xiangou);
        $this->assign('orderG_number', $order_goods['goods_number']);
        //ecmoban模板堂 --zhuo end 限购

        //ecmoban模板堂 --zhuo start
        $shop_info = get_merchants_shop_info('merchants_steps_fields', $goods['user_id']);
        $adress = get_license_comp_adress($shop_info['license_comp_adress']);

        $this->assign('shop_info', $shop_info);
        $this->assign('adress', $adress);
        //ecmoban模板堂 --zhuo end

        //ecmoban模板堂 --zhuo start 仓库
        $province_list = get_warehouse_province();
        $this->assign('province_list', $province_list); //省、直辖市

        $city_list = get_region_city_county($this->province_id);
        if($city_list){
            foreach($city_list as $k=>$v){
                $city_list[$k]['district_list'] = get_region_city_county($v['region_id']);
            }
        }
        $this->assign('city_list', $city_list); //省下级市

        $district_list = get_region_city_county($this->city_id);
        $this->assign('district_list', $district_list);//市下级县

        $warehouse_list = get_warehouse_list_goods();
        $this->assign('warehouse_list', $warehouse_list); //仓库列

        $warehouse_name = get_warehouse_name_id($this->region_id);

        $this->assign('warehouse_name', $warehouse_name); //仓库名称
        $this->assign('region_id', $this->region_id); //商品仓库region_id
        $this->assign('user_id', $_SESSION['user_id']);
        $this->assign('shop_price_type', $goods['model_price']); //商品价格运营模式 0代表统一价格（默认） 1、代表仓库价格 2、代表地区价格
        $this->assign('area_id', $this->area_info['region_id']); //地区ID
        //ecmoban模板堂 --zhuo start 仓库

        if ($goods['brand_id'] > 0){
            $brand_act = '';
            $brand = get_goods_brand($goods['brand_id']);
            if($brand){
                $goods['brand_id'] = $brand['brand_id'];
                $goods['goods_brand'] = $brand['goods_brand'];
                $brand_act = "merchants_brands";
            }
            $goods['goods_brand_url'] = build_uri('brand', array('bid'=>$goods['brand_id']), $goods['goods_brand']);
        }
        $shop_price   = $goods['shop_price'] ? $goods['shop_price'] : 0;
        $linked_goods = get_linked_goods($this->goods_id, $this->region_id, $this->area_info['region_id']);
        $history_goods = get_history_goods($this->goods_id, $this->region_id, $this->area_info['region_id']);
        $goods['goods_style_name'] = add_style($goods['goods_name'], $goods['goods_name_style']);
        /* 购买该商品可以得到多少钱的红包 */
        if ($goods['bonus_type_id'] > 0)
        {
            $time = gmtime();
            $sql = "SELECT type_money FROM {pre}bonus_type" .
                " WHERE type_id = '$goods[bonus_type_id]' " .
                " AND send_type = '" . SEND_BY_GOODS . "' " .
                " AND send_start_date <= '$time'" .
                " AND send_end_date >= '$time'";
            $goods['bonus_money'] = floatval($this->db->getOne($sql));
            if ($goods['bonus_money'] > 0)
            {
                $goods['bonus_money'] = price_format($goods['bonus_money']);
            }
        }

        /*获取可用门店数量*/
        if($storeId > 0){
            $sql = "SELECT id, stores_name, stores_user FROM {pre}offline_store  WHERE id = '$storeId'";
            $store = $this->db->getRow($sql);
            $this->assign('store', $store);
        }

        $sql = "SELECT COUNT(*) FROM {pre}offline_store AS o LEFT JOIN {pre}store_goods AS s ON o.id = s.store_id WHERE s.goods_id = '$this->goods_id'";
        $goods['store_count'] = $this->db->getOne($sql);
        $this->assign('goods',              $goods);
        $this->assign('goods_id',           $goods['goods_id']);
        $this->assign('promote_end_time',   $goods['gmt_end_time']);
        $this->assign('categories',         get_categories_tree($goods['cat_id']));  // 分类树
        $position = assign_ur_here($goods['cat_id'], $goods['goods_name']);
        $this->assign('page_title',          $position['title']);                    // 页面标题
        $this->assign('keywords',      $goods['keywords']);       // 商品关键词
        $this->assign('description',   $goods['goods_brief']);    // 商品简单描述
        $this->assign('page_img',      get_wechat_image_path($goods['goods_img']));  // 用于微信SDK分享图片
        $properties = get_goods_properties($this->goods_id, $this->region_id, $this->area_info['region_id']);  // 获得商品的规格和属性
        $this->assign('properties',          $properties['pro']);                              // 商品属性
        //默认选中的商品规格 by wanglu
        $default_spe = '';
        if($properties['spe']){
            foreach($properties['spe'] as $k=>$v){
                if($v['attr_type'] == 1){
                    if($v['is_checked'] > 0){
                        foreach($v['values'] as $key=>$val){
                            $default_spe .= $val['checked'] ? $val['label'].'、' : '';
                        }
                    }
                    else{
                        foreach($v['values'] as $key=>$val){
                            if($key == 0){
                                $default_spe .= $val['label'].'、';
                            }
                        }
                    }
                }
            }
        }

        $this->assign('default_spe',       $default_spe);                              // 商品规格
        $this->assign('specification',       $properties['spe']);                              // 商品规格
        $this->assign('attribute_linked',    get_same_attribute_goods($properties));           // 相同属性的关联商品
        $this->assign('related_goods',       $linked_goods);                                   // 关联商品
        $this->assign('rank_prices',         get_user_rank_prices($this->goods_id, $shop_price));    // 会员等级价格
        $this->assign('pictures',            get_goods_gallery($this->goods_id));                    // 商品相册
        $this->assign('bought_goods',        get_also_bought($this->goods_id));                      // 购买了该商品的用户还购买了哪些商品
        $this->assign('goods_rank',          get_goods_rank($this->goods_id));                       // 商品的销售排名
        $this->assign('cart_number',         cart_number());                                  // 商品的销售排名
        // 配件
        $fittings_list = get_goods_fittings(array($this->goods_id), $this->region_id, $this->area_info['region_id']);
        if(is_array($fittings_list)){
            foreach($fittings_list as $vo){
                $fittings_index[$vo['group_id']] = $vo['group_id'];//关联数组
            }
        }
        $this->assign('fittings',            $fittings_list);
        //获取关联礼包
        $package_goods_list = get_package_goods_list($goods['goods_id']);
        $this->assign('package_goods_list',$package_goods_list);    // 获取关联礼包

        assign_dynamic('goods');
        $volume_price_list = get_volume_price_list($goods['goods_id'], '1');
        $this->assign('volume_price_list',$volume_price_list);    // 商品优惠价格区间

        $this->assign('sales_count',get_goods_sales($this->goods_id));

        //商品运费
        $region = array(1, $this->province_id, $this->city_id, $this->district_id);
        $shippingFee = goodsShippingFee($this->goods_id, $this->region_id, $region);
        $this->assign('shippingFee',$shippingFee);

        // 检查是否已经存在于用户的收藏夹
        if ($_SESSION ['user_id']) {
            $where['user_id'] = $_SESSION ['user_id'];
            $where['goods_id'] = $this->goods_id;
            $rs = $this->db->table('collect_goods')->where($where)->count();
            if ($rs > 0) {
                $this->assign('goods_collect', 1);
            }
        }
        /* 更新点击次数 */
        $this->db->query('UPDATE ' . $this->ecs->table('goods') . " SET click_count = click_count + 1 WHERE goods_id = '$this->goods_id'");
        /* 记录浏览历史 */
        if (!empty($_COOKIE['ECS']['history_goods']))
        {
            $history = explode(',', $_COOKIE['ECS']['history_goods']);
            array_unshift($history, $this->goods_id);
            $history = array_unique($history);
            while (count($history) > C('shop.history_number'))
            {
                array_pop($history);
            }
            cookie('ECS[history_goods]', implode(',', $history));
        }
        else
        {
            cookie('ECS[history_goods]', $this->goods_id);
        }
        //ecmoban模板堂 --zhuo 仓库 start
        $this->assign('province_row',  get_region_name($this->province_id));
        $this->assign('city_row',  get_region_name($this->city_id));
        $this->assign('district_row',  get_region_name($this->district_id));

        $goods_region['country'] = 1;
        $goods_region['province'] = $this->province_id;
        $goods_region['city'] = $this->city_id;
        $goods_region['district'] = $this->district_id;
        $this->assign('goods_region', $goods_region);

        $date = array('shipping_code');
        $where = "shipping_id = '" .$goods['default_shipping']. "'";
        $shipping_code = get_table_date('shipping', $where, $date, 2);

        $cart_num = cart_number();
        $this->assign('cart_num',  $cart_num);

        $this->assign('area_htmlType',  'goods');
        //评分 start
        $mc_all = ments_count_all($this->goods_id);       //总条数
        $mc_one = ments_count_rank_num($this->goods_id, 1);     //一颗星
        $mc_two = ments_count_rank_num($this->goods_id, 2);     //两颗星
        $mc_three = ments_count_rank_num($this->goods_id, 3);       //三颗星
        $mc_four = ments_count_rank_num($this->goods_id, 4);        //四颗星
        $mc_five = ments_count_rank_num($this->goods_id, 5);        //五颗星
        $comment_all = get_conments_stars($mc_all,$mc_one,$mc_two,$mc_three,$mc_four,$mc_five);
        if($goods['user_id'] > 0){
            //商家所有商品评分类型汇总
            $merchants_goods_comment = get_merchants_goods_comment($goods['user_id']);
            $this->assign('merch_cmt',  $merchants_goods_comment);
        }
        $this->assign('comment_all',  $comment_all);
        //查询一条好评
        $good_comment = get_good_comment($this->goods_id, 4, 1, 0, 1);

        $this->assign('good_comment', $good_comment);
        //店铺关注人数 by wanglu
        $sql = "SELECT count(*) FROM ".$this->ecs->table('collect_store')." WHERE ru_id = ".$goods['user_id'];
        $collect_number = $this->db->getOne($sql);
        $this->assign('collect_number', $collect_number ? $collect_number : 0);
        //评分 end
        $sql="select b.is_IM,a.ru_id,a.province, a.city, a.kf_type, a.kf_ww, a.kf_qq, a.meiqia, a.shop_name, a.kf_appkey,kf_secretkey from {pre}seller_shopinfo as a left join {pre}merchants_shop_information as b on a.ru_id=b.user_id where a.ru_id='" .$goods['user_id']. "' ";
        $basic_info = $this->db->getRow($sql);

        $info_ww = $basic_info['kf_ww'] ? explode("\r\n", $basic_info['kf_ww']) : '';
        $info_qq = $basic_info['kf_qq'] ? explode("\r\n", $basic_info['kf_qq']) : '';
        $kf_ww = $info_ww ?  $info_ww[0] : '';
        $kf_qq = $info_qq ?  $info_qq[0] : '';
        $basic_ww = $kf_ww ? explode('|', $kf_ww) : '';
        $basic_qq = $kf_qq ? explode('|', $kf_qq) : '';
        $basic_info['kf_ww'] = $basic_ww ? $basic_ww[1] : '';
        $basic_info['kf_qq'] = $basic_qq ? $basic_qq[1] : '';

        if(($basic_info['is_im']==1 || $basic_info['ru_id']==0) &&!empty($basic_info['kf_appkey'])){
           $basic_info['kf_appkey'] = $basic_info['kf_appkey'];
        }else{
           $basic_info['kf_appkey'] = '';
        }

        $basic_date = array('region_name');
        $basic_info['province'] = get_table_date('region', "region_id = '" . $basic_info['province'] . "'", $basic_date, 2);
        $basic_info['city'] = get_table_date('region', "region_id= '" . $basic_info['city'] . "'", $basic_date, 2) . "市";

        $this->assign('basic_info',  $basic_info);
        $shipping_list = warehouse_shipping_list($goods, $this->region_id, 1, $goods_region);
        $this->assign('shipping_list',  $shipping_list);

        $_SESSION['goods_equal'] = '';
        $this->db->query('delete from ' . $this->ecs->table('cart_combo') . " WHERE (parent_id = 0 and goods_id = '$this->goods_id' or parent_id = '$this->goods_id') and " . $sess_id);
        //ecmoban模板堂 --zhuo 仓库 end
        //新品
        $new_goods = get_recommend_goods('new', '', $this->region_id, $this->area_info['region_id'], $goods['user_id']);
        $this->assign('new_goods', $new_goods);
        $link_goods=get_linked_goods($this->goods_id,$this->region_id, $this->area_info['region_id']);
        $this->assign('link_goods', $link_goods);

        //店铺优惠券 by wanglu
        $time = gmtime();
        $sql="SELECT * FROM {pre}coupons WHERE (`cou_type` = 3 OR `cou_type` = 4 ) AND `cou_end_time` >$time AND (( instr(`cou_goods`, $this->goods_id) ) or (`cou_goods`=0)) AND (( instr(`cou_ok_user`, $_SESSION[user_rank]) ) or (`cou_ok_user`=0)) and review_status = 3 and ru_id=".$goods[user_id];
        //优惠券加上缓存
        $cache_id = md5($sql);
        $coupont = cache($cache_id);
        if($coupont === false){
          $coupont = $this->db->getALl($sql);
          foreach ($coupont as $key => $value) {
              $coupont[$key]['cou_end_time']=date('Y.m.d',$value['cou_end_time']);
              $coupont[$key]['cou_start_time']=date('Y.m.d',$value['cou_start_time']);
          }
          cache($cache_id, $coupont, 600);
        }
        //缓存end
        $this->assign('bonus_list', $coupont);
        $this->display();
    }

    private function actionClearStoreId(){
        $result = array('error'=>0);

        unset($_SESSION['store_id']);

        echo json_encode($result);
    }


    /**
     * 商品详情
     */
    public function actionInfo()
    {
        $info = $this->db->table('goods')->field('goods_desc,desc_mobile')->where(array('goods_id' => $this->goods_id))->find();
        $properties = get_goods_properties($this->goods_id, $this->region_id, $this->area_info['region_id']);  // 获得商品的规格和属性
        // 查询关联商品描述
        $sql = "SELECT ld.goods_desc FROM {pre}link_desc_goodsid AS dg, {pre}link_goods_desc AS ld WHERE dg.goods_id = {$this->goods_id}  AND dg.d_id = ld.id";
        $link_desc = $this->db->getOne($sql);
        if(!empty($info['desc_mobile'])){
            $goods_desc=preg_replace('/<div[^>]*(tools)[^>]*>(.*?)<\/div>(.*?)<\/div>/is', '', $info['desc_mobile']);
        }
        if (empty($info['desc_mobile']) && !empty($info['goods_desc'])) {
           $goods_desc=str_replace('src="images/upload', 'src="' . __STATIC__ . '/images/upload', $info['goods_desc']);
        }
        if(empty($info['desc_mobile']) && empty($info['goods_desc'])){
            $goods_desc=$link_desc;
        }
        $this->assign('goods_desc', $goods_desc);
        // 商品属性
        $this->assign('properties', $properties['pro']);
        $this->assign('page_title', L('goods_detail'));
        $this->display();
    }
            //


    /**
     * 商品评论
     */
    public function actionComment($img=0){
       if(IS_AJAX){
            $rank = I('rank','');
            $page = I('page');
            $page = ($page - 1) * $this->size;
            if($rank == 'img') {
                $rank = 5;
                $img = 1;
            }
            $arr = get_good_comment_as($this->goods_id, $rank, 1,$page);
            $comments = $arr['arr'];
            if($img) {
                foreach ($comments as $key => $val) {
                    if ($val['thumb'] == 0) {
                        unset($comments[$key]);
                    }
                }
                $rank = 'img';
            }
            $show = count($comments) > 0 ? 1 : 0 ;
            $max  = $page>0 ? 0 :1;
            die(json_encode(array('comments'=>$comments,'rank'=>$rank,'show'=>$show,'reset'=>$max,'totalPage'=>$arr['max'],'top'=>1)));
        }
        $this->assign('img',$img);
        $this->assign('info',commentCol($this->goods_id));
        $this->assign('id',$this->goods_id);
        $this->assign('page_title',L('goods_comment'));
        $this->display('comment');

    }
    public function actionInfoimg(){
       $this->actionComment(1);
    }
    /**
     * 改变属性、数量时重新计算商品价格
     */
    public function actionPrice()
    {
        $res = array('err_msg' => '', 'result' => '', 'qty' => 1);
        $attr = I('attr');
        $number = I('number', 1, 'intval');
        $attr_id = !empty($attr) ? explode(',', $attr) : array();
        $warehouse_id = I('request.warehouse_id', 0, 'intval');
        $area_id = I('request.area_id', 0, 'intval'); //仓库管理的地区ID
        $onload = I('request.onload', '', 'trim');; //仓库管理的地区ID

        $goods_attr    = isset($_REQUEST['goods_attr']) ? explode(',', $_REQUEST['goods_attr']) : array();
        $attr_ajax = get_goods_attr_ajax($this->goods_id, $goods_attr, $attr_id);

        $goods = get_goods_info($this->goods_id, $warehouse_id, $area_id);

        if ($this->goods_id == 0)
        {
            $res['err_msg'] = L('err_change_attr');
            $res['err_no']  = 1;
        }
        else
        {
            if ($number == 0)
            {
                $res['qty'] = $number = 1;
            }
            else
            {
                $res['qty'] = $number;
            }
            //ecmoban模板堂 --zhuo start
            $products = get_warehouse_id_attr_number($this->goods_id, $_REQUEST['attr'], $goods['user_id'], $warehouse_id, $area_id);
            $attr_number = $products['product_number'];

            if($goods['model_attr'] == 1){
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '$warehouse_id'";
            }elseif($goods['model_attr'] == 2){
                $table_products = "products_area";
                $type_files = " and area_id = '$area_id'";
            }else{
                $table_products = "products";
                $type_files = "";
            }

            $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '$this->goods_id'" .$type_files. " LIMIT 0, 1";
            $prod = $GLOBALS['db']->getRow($sql);

            if($goods['goods_type'] == 0){

                $attr_number = $goods['goods_number'];
            }else{
                if(empty($prod)){ //当商品没有属性库存时
                    $attr_number = $goods['goods_number'];
                }

                if(!empty($prod) && $GLOBALS['_CFG']['add_shop_price'] == 0 && $onload == 'onload'){
                    if(empty($attr_number)){
                        $attr_number = $goods['goods_number'];
                    }
                }
            }

            $attr_number = !empty($attr_number) ? $attr_number : 0;
            $res['attr_number'] = $attr_number;

            //限制用户购买的数量 bywanglu
            $res['limit_number'] = $attr_number < $number ? ($attr_number ? $attr_number : 1) : $number;
            $shop_price  = get_final_price($this->goods_id, $number, true, $attr_id, $warehouse_id, $area_id);
            //ecmoban模板堂 --zhuo end

            $res['shop_price'] = price_format($shop_price);
            $res['market_price'] = $goods['market_price'];

            $res['show_goods'] = 0;

            if($goods_attr && $GLOBALS['_CFG']['add_shop_price'] == 0){
                if(count($goods_attr) == count($attr_ajax['attr_id'])){
                    $res['show_goods'] = 1;
                }
            }

            //属性价格
            $spec_price  = get_final_price($this->goods_id, $number, true, $attr_id, $warehouse_id, $area_id, 1, 0, 0, $res['show_goods']);

            if($GLOBALS['_CFG']['add_shop_price'] == 0 && empty($spec_price)){
                $spec_price = $shop_price;
            }

            $res['spec_price'] = price_format($spec_price);

            $martetprice_amount = $spec_price + $goods['marketPrice'];
            $res['marketPrice_amount'] = price_format($spec_price + $goods['marketPrice']);

            //切换属性后的价格折扣 by wanglu
            $res['discount'] = round($shop_price / $martetprice_amount, 2) * 10;

            $res['result'] = price_format($shop_price * $number);

            if($GLOBALS['_CFG']['add_shop_price'] == 0){
                $res['result_market'] = price_format($goods['marketPrice'] * $number);
            }else{
                $res['result_market'] = price_format($goods['marketPrice'] * $number + $spec_price);
            }
        }
        $goods_fittings = get_goods_fittings_info($this->goods_id, $warehouse_id, $area_id, '', 1);
        $fittings_list = get_goods_fittings(array($this->goods_id), $warehouse_id, $area_id);

        if($fittings_list){
            if(is_array($fittings_list)){
                foreach($fittings_list as $vo){
                    $fittings_index[$vo['group_id']] = $vo['group_id'];//关联数组
                }
            }
            ksort($fittings_index);//重新排序

            $merge_fittings = get_merge_fittings_array($fittings_index, $fittings_list); //配件商品重新分组
            $fitts = get_fittings_array_list($merge_fittings, $goods_fittings);

            for($i=0; $i<count($fitts); $i++){
                $fittings_interval = $fitts[$i]['fittings_interval'];

                $res['fittings_interval'][$i]['fittings_minMax'] = price_format($fittings_interval['fittings_min']) ."-". number_format($fittings_interval['fittings_max'], 2, '.', '');
                $res['fittings_interval'][$i]['market_minMax'] = price_format($fittings_interval['market_min']) ."-". number_format($fittings_interval['market_max'], 2, '.', '');

                if($fittings_interval['save_minPrice'] == $fittings_interval['save_maxPrice']){
                    $res['fittings_interval'][$i]['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']);
                }else{
                    $res['fittings_interval'][$i]['save_minMaxPrice'] = price_format($fittings_interval['save_minPrice']) ."-". number_format($fittings_interval['save_maxPrice'], 2, '.', '');
                }

                $res['fittings_interval'][$i]['groupId'] = $fittings_interval['groupId'];
            }
        }


        if($GLOBALS['_CFG']['open_area_goods'] == 1){
            $area_list = get_goods_link_area_list($this->goods_id, $goods['user_id']);
            if($area_list['goods_area']){
                if(!in_array($area_id, $area_list['goods_area'])){
                    $res['err_no']  = 2;
                }
            } else {
                $res['err_no']  = 2;
            }
        }
        $attr_info = get_attr_value($this->goods_id,$attr_id[0]);
        if(!empty($attr_info['attr_img_flie'])){
           $res['attr_img'] = get_image_path($attr_info['attr_img_flie']);
        }

        $res['onload'] = $onload;

        die(json_encode($res));
    }

    /**
     * 切换仓库
     */
    public function actionInWarehouse(){
        if(IS_AJAX){
            $res    = array('err_msg' => '', 'result' => '', 'qty' => 1, 'goods_id'=>0);
            $pid = I('get.pid', 0, 'intval');
            $goods_id = I('get.id', 0, 'intval');
            if(empty($pid) || empty($goods_id)){
                die(json_encode($res));
            }
            cookie('region_id', $pid);
            cookie('regionid', $pid);

            $area_region = 0;
            cookie('area_region', $area_region);

            $res['goods_id'] = $goods_id;

            die(json_encode($res));
        }
    }

    /**
     * 库存选择
     */
    public function actionInstock(){

        if(IS_AJAX){

            $res = array('err_msg' => '', 'result' => '', 'qty' => 1);
            clear_cache_files();
            $goods_id = $this->goods_id;
            $province = I('get.province', 1, 'intval');
            $city = I('get.city', 0, 'intval');
            $district = I('get.district', 0, 'intval');
            $street = I('get.street', 0, 'intval');
            $d_null = I('get.d_null', 0, 'intval');
            $user_id = I('get.user_id', 0, 'intval');

            $user_address = get_user_address_region($user_id);
            $user_address = explode(",",$user_address['region_address']);

            setcookie('province', $province, gmtime() + 3600 * 24 * 30);
            setcookie('city', $city, gmtime() + 3600 * 24 * 30);
            setcookie('district', $district, gmtime() + 3600 * 24 * 30);
            setcookie('street', $street, gmtime() + 3600 * 24 * 30);
            $regionId = 0;
            setcookie('regionId', $regionId, gmtime() + 3600 * 24 * 30);

            //清空
            setcookie('type_province', 0, gmtime() + 3600 * 24 * 30);
            setcookie('type_city', 0, gmtime() + 3600 * 24 * 30);
            setcookie('type_district', 0, gmtime() + 3600 * 24 * 30);
            setcookie('type_street', 0, gmtime() + 3600 * 24 * 30);
            $res['d_null'] = $d_null;

            if($d_null == 0){
               if(in_array($district,$user_address)){
                    $res['isRegion'] = 1;
                }else{
                    $res['message'] = L('write_address');
                    $res['isRegion'] = 88; //原为0
                }
            }else{
                setcookie('district', '', gmtime() + 3600 * 24 * 30);
            }
            $res['goods_id'] = $goods_id;

            die(json_encode($res));
        }
    }


    /**
     * 商品收藏
     */
    public function actionAddCollection(){
        $result = array(
            'error' => 0,
            'message' => ''
        );

        if (!isset($this->user_id) || $this->user_id == 0) {
            $result['error'] = 2;
            $result['message'] = L('login_please');
            die(json_encode($result));
        } else {
            // 检查是否已经存在于用户的收藏夹
            $where['user_id'] = $this->user_id;
            $where['goods_id'] = $this->goods_id;
            $rs = $this->db->table('collect_goods')->where($where)->count();
            if ($rs > 0) {
                $rs = $this->db->table('collect_goods')->where($where)->delete();
                if (!$rs) {
                    $result['error'] = 1;
                    $result['message'] = M()->errorMsg();
                    die(json_encode($result));
                } else {
                    $result['error'] = 0;
                    $result['message'] = L('collect_success');
                    die(json_encode($result));
                }
            } else {
                $data['user_id'] = $this->user_id;
                $data['goods_id'] = $this->goods_id;
                $data['add_time'] = gmtime();
                if ($this->db->table('collect_goods')->data($data)->add() === false) {
                    $result['error'] = 1;
                    $result['message'] = M()->errorMsg();
                    die(json_encode($result));
                } else {
                    $result['error'] = 0;
                    $result['message'] = L('collect_success');
                    die(json_encode($result));
                }
            }
        }
    }

    /**
     * 初始化参数
     */
    private function init_params(){
        #需要查询的IP start

        if(!isset($_COOKIE['province'])){
            $area_array = get_ip_area_name();

            if($area_array['county_level'] == 2){
                $date = array('region_id', 'parent_id', 'region_name');
                $where = "region_name = '" .$area_array['area_name']. "' AND region_type = 2";
                $city_info = get_table_date('region', $where, $date, 1);

                $date = array('region_id', 'region_name');
                $where = "region_id = '" .$city_info[0]['parent_id']. "'";
                $province_info = get_table_date('region', $where, $date);

                $where = "parent_id = '" .$city_info[0]['region_id']. "' order by region_id asc limit 0, 1";
                $district_info = get_table_date('region', $where, $date, 1);

            }elseif($area_array['county_level'] == 1){
                $area_name = $area_array['area_name'];

                $date = array('region_id', 'region_name');
                $where = "region_name = '$area_name'";
                $province_info = get_table_date('region', $where, $date);

                $where = "parent_id = '" .$province_info['region_id']. "' order by region_id asc limit 0, 1";
                $city_info = get_table_date('region', $where, $date, 1);

                $where = "parent_id = '" .$city_info[0]['region_id']. "' order by region_id asc limit 0, 1";
                $district_info = get_table_date('region', $where, $date, 1);
            }
        }
        #需要查询的IP end
        $order_area = get_user_order_area($this->user_id);
        $user_area = get_user_area_reg($this->user_id); //2014-02-25

        if($order_area['province'] && $this->user_id > 0){
            $this->province_id = $order_area['province'];
            $this->city_id = $order_area['city'];
            $this->district_id = $order_area['district'];
        }else{
            //省
            if($user_area['province'] > 0){
                $this->province_id = $user_area['province'];
                cookie('province', $user_area['province']);
                $this->region_id = get_province_id_warehouse($this->province_id);
            }else{
                $sql = "select region_name from " .$this->ecs->table('region_warehouse'). " where regionId = '" .$province_info['region_id']. "'";
                $warehouse_name = $this->db->getOne($sql);

                $this->province_id = $province_info['region_id'];
                $cangku_name = $warehouse_name;
                $this->region_id = get_warehouse_name_id(0, $cangku_name);
            }
            //市
            if($user_area['city'] > 0){
                $this->city_id = $user_area['city'];
                cookie('city', $user_area['city']);
            }else{
                $this->city_id = $city_info[0]['region_id'];
            }
            //区
            if($user_area['district'] > 0){
                $this->district_id = $user_area['district'];
                cookie('district', $user_area['district']);
            }else{
                $this->district_id = $district_info[0]['region_id'];
            }
        }

        $this->province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : $this->province_id;

        $child_num = get_region_child_num($this->province_id);
        if($child_num > 0){
            $this->city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : $this->city_id;
        }else{
            $this->city_id = '';
        }

        $child_num = get_region_child_num($this->city_id);
        if($child_num > 0){
            $this->district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : $this->district_id;
        }else{
            $this->district_id = '';
        }

        $this->region_id = !isset($_COOKIE['region_id']) ? $this->region_id : $_COOKIE['region_id'];
        $goods_warehouse = get_warehouse_goods_region($this->province_id); //查询用户选择的配送地址所属仓库
        if($goods_warehouse){
            $this->regionId = $goods_warehouse['region_id'];
            if($_COOKIE['region_id'] && $_COOKIE['regionid']){
                $gw = 0;
            }else{
                $gw = 1;

            }
        }
        if($gw){
            $this->region_id = $this->regionId;
            cookie('area_region', $this->region_id);
        }

        cookie('goodsId', $this->goods_id);

        $sellerInfo = get_seller_info_area();
        if(empty($this->province_id)){
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

    /**
     * 判断是否是分销商
     */
    public function actionCheckDrp(){
        if(IS_AJAX){
            $shop_num = $this->model->table('drp_shop')->where(array('user_id'=>$this->user_id))->count();
            if($shop_num == 1){
                exit(json_encode(array('code'=>1)));
            }
            else{
                exit(json_encode(array('code'=>0)));
            }
        }
    }
}

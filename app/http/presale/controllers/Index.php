<?php
namespace app\http\presale\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend
{
    private $user_id = 0;
    private $region_id = 0;
    private $preid = 0;
    private $area_info = array();

    public function __construct()
    {
        parent::__construct();

        isset($_SESSION['user_id']) && $this->user_id = $_SESSION['user_id'];
        $this->init_params();//初始化位置信息
    }

    /**
     * 预售 --> 首页
     */
    public function actionIndex()
    {
        //分类导航页
        $this->assign('pre_nav_list', get_pre_nav());
        $pre_goods = get_pre_cat();
        $this->assign('pre_cat_goods', $pre_goods);
        $this->assign('pre_list_url', url('presale/index/list'));
        $this->assign('pre_new_url', url('presale/index/new'));
        $this->assign('page_title', '预售频道');
        $this->display();
    }
    /**
     * 预售 --> 抢先订
     *
     */
    public function actionList()
    {
        $page = I('request.page', 1, 'intval');
        $size = 10;
        if(IS_AJAX){
            $default_sort_order_method = C('shop.sort_order_method') == '0' ? 'DESC' : 'ASC';
            $default_sort_order_type   = C('shop.sort_order_type') == '0' ? 'act_id' : (C('shop.sort_order_type') == '1' ? 'shop_price' : 'start_time');
            $sort   = (isset($_REQUEST['sort'])  && in_array(trim(strtolower(I('sort'))), array('shop_price', 'start_time', 'act_id'))) ? I('sort','','trim')  : $default_sort_order_type;
            $order  = (isset($_REQUEST['order']) && in_array(trim(strtoupper(I('order'))), array('ASC', 'DESC'))) ? I('order','','trim') : $default_sort_order_method;
            $cat_id= json_str_iconv(I('cat_id',0));
            $status = I('status',0,'intval');   // 状态1即将开始，2预约中，3已结束
            $keyword = compile_str(I('keyword'));
            // 商品列表
            $pre_goods = $this->get_pre_goods($cat_id, $status, $sort, $order, $page, $size,$keyword);
            die(json_encode(array('list' => $pre_goods['list'], 'totalPage' => ceil($pre_goods['total']/$size))));
        }
        // 预售分类
        $cat_id    = I('get.id',0);
        $sql = "SELECT * FROM ".$GLOBALS['ecs']->table('presale_cat')." ORDER BY sort_order ASC ";
        $cat_res = $GLOBALS['db']->getAll($sql);

        $page_title = '';
        foreach ($cat_res as $key => $row)
        {
            if(stristr($cat_id,$row['cat_id'])){
                $cat_res[$key]['selected'] = 1;
                    $page_title .= $cat_res[$key]['cat_name'];
            }
            $cat_res[$key]['goods'] = get_cat_goods($row['cat_id']);
            $cat_res[$key]['count_goods'] = count(get_cat_goods($row['cat_id']));

            $cat_res[$key]['cat_url'] = url('presale/index/list');
        }
        $page_title .= '抢先订_预售频道';
        $this->assign('pre_cat', $cat_res);
        $this->assign('cat_id', $cat_id);
        $this->assign('page_title', $page_title);
        $this->display();
    }

    /**
     * 预售 --> 新品发布
     */
    public function actionNew()
    {
        $where = '';
        // 筛选条件
        $cat_id = json_str_iconv(I('cat_id',0));
        $status = isset($_REQUEST['status']) && intval($_REQUEST['status']) > 0 ? intval($_REQUEST['status']) : 0;// 状态1即将开始，2预约中，3已结束
        $keyword = compile_str(I('keyword'));

        if ($cat_id > 0 )
        {
            $where .= " AND a.cat_id = '$cat_id' ";
        }
        //1未开始，2进行中，3结束
        $now = gmtime();
        if ($status == 1) {
            $where .= " AND a.start_time > $now ";
        } elseif ($status == 2) {
            $where .= " AND a.start_time < $now AND $now < a.end_time ";
        } elseif ($status == 3) {
            $where .= " AND $now > a.end_time ";
        }
        if ($keyword) {
            $where .= " AND g.goods_name like '%$keyword%' ";
        }
        $sql = "SELECT a.*, g.goods_thumb, g.goods_img, g.goods_name, g.shop_price, g.market_price, g.sales_volume FROM ".$GLOBALS['ecs']->table('presale_activity')." AS a"
            . " LEFT JOIN ".$GLOBALS['ecs']->table('goods')." AS g ON a.goods_id = g.goods_id "
            . " WHERE g.goods_id > 0 $where AND g.is_on_sale = 0 AND a.review_status = 3 ORDER BY a.end_time DESC,a.start_time DESC ";
        $res = $GLOBALS['db']->getAll($sql);
        foreach ($res as $key => $row)
        {
            $res[$key]['thumb'] = get_image_path($row['goods_thumb']);
            $res[$key]['goods_img'] = get_image_path($row['goods_img']);
            $res[$key]['url'] = build_uri('presale', array('r' => 'index/detail', 'id' => $row['act_id']));
            $res[$key]['end_time_day'] = local_date("Y-m-d", $row['end_time']);
            if ($row['start_time'] >= $now) {
                $res[$key]['no_start'] = 1;
                $res[$key]['short_format_date'] = short_format_date($row['start_time']);
            }else if($row['end_time'] < $now){
                $res[$key]['no_start'] = 3;
            }else{
                $res[$key]['short_format_date'] = short_format_date($row['end_time']);
            }
            // if ($row['start_time'] >= $now )
            // {
            //     $res[$key]['no_start'] = 1;
            // }
        }
        // 按日期重新排序数据分组
        $date_array = array();
        foreach ($res as $key => $row) {
            $date_array[$row['end_time_day']][] = $row;
        }
        // 把日期键值替换成数字0、1、2...,日期楼层下商品归类
        $date_result = array();
        foreach ($date_array as $key => $value) {
            $date_result[]['goods'] = $value;
        }

        foreach ($date_result as $key => $value)
        {
            $date_result[$key]['end_time_day'] = local_date("Y-m-d", gmstr2time($value['goods'][0]['end_time_day']));
            $date_result[$key]['end_time_y'] = local_date('Y', gmstr2time($value['goods'][0]['end_time_day']));
            $date_result[$key]['end_time_m'] = local_date('m', gmstr2time($value['goods'][0]['end_time_day']));
            $date_result[$key]['end_time_d'] = local_date('d', gmstr2time($value['goods'][0]['end_time_day']));
            $date_result[$key]['count_goods'] = count($value['goods']);
        }
        $this->assign('date_result', $date_result);
        // 预售分类
        $pre_cat = get_pre_cat();
        $this->assign('pre_cat', $pre_cat);

        $this->assign('page_title', '预售新品');
        $this->display();
    }

    /**
     * 预售 商品详情
     *
     */
    public function actionDetail()
    {
        $this->preid = I('id');
        if ($this->preid <= 0) ecs_header("Location: ./\n");

        //$presale = presale_info($this->preid); /* 取得预售活动信息 */
        $presale = presale_info($this->preid, 1,array(),$this->region_id, $this->area_info['region_id']);
        if (empty($presale)) {
            ecs_header("Location: ./\n");
            exit;
        }
        $now = gmtime();
        $presale['gmt_end_date'] = local_strtotime($presale['end_time']);
        $presale['gmt_start_date'] = local_strtotime($presale['start_time']);
        if ($presale['gmt_start_date'] >= $now )
        {
            $presale['no_start'] = 1;
        }
        $this->assign('presale', $presale);
        /* 取得预售商品信息 */
        $this->goods_id = $presale['goods_id'];
        $goods = get_goods_info($this->goods_id, $this->region_id, $this->area_info['region_id']);
        if (empty($goods))
        {
            ecs_header("Location: ./\n");
            exit;
        }
        // 预售销量
        $sql = "SELECT COUNT(*) as num FROM {pre}order_info WHERE extension_id = '$this->preid'";
        $res = $GLOBALS['db']->getOne($sql);
        if($res){
            $goods['sales_volume'] = $res;

        }else{
            $goods['sales_volume']  = 0;
        }
        // 检查是否已经存在于用户的收藏夹
        if ($_SESSION ['user_id']) {
            $where['user_id'] = $_SESSION ['user_id'];
            $where['goods_id'] = $this->goods_id;
            $rs = $this->db->table('collect_goods')->where($where)->count();
            if ($rs > 0) {
                $this->assign('goods_collect', 1);
            }
        }
        $this->assign('goods', $goods);
        $this->assign('type',  0);
        //评分 start
        $mc_all = ments_count_all($this->goods_id);       //总条数
        $mc_one = ments_count_rank_num($this->goods_id, 1);        //一颗星
        $mc_two = ments_count_rank_num($this->goods_id, 2);        //两颗星
        $mc_three = ments_count_rank_num($this->goods_id, 3);    //三颗星
        $mc_four = ments_count_rank_num($this->goods_id, 4);        //四颗星
        $mc_five = ments_count_rank_num($this->goods_id, 5);        //五颗星
        $comment_all = get_conments_stars($mc_all, $mc_one, $mc_two, $mc_three, $mc_four, $mc_five);
        if ($goods['user_id'] > 0) {
            //商家所有商品评分类型汇总
            $merchants_goods_comment = get_merchants_goods_comment($goods['user_id']);
            $this->assign('merch_cmt', $merchants_goods_comment);
        }
        $this->assign('comment_all', $comment_all);
        //查询一条好评
        $good_comment = get_good_comment($this->goods_id, 4, 1, 0, 1);
        $this->assign('good_comment', $good_comment);
        $this->assign('goods_id', $this->goods_id); //商品ID
        // 商家客服
        $sql="select b.is_IM, a.ru_id,a.province, a.city, a.kf_type, a.kf_ww, a.kf_qq, a.meiqia, a.shop_name, a.kf_appkey from {pre}seller_shopinfo as a left join {pre}merchants_shop_information as b on a.ru_id=b.shop_id where ru_id='" .$goods['user_id']. "' ";
        $basic_info = $this->db->getRow($sql);

        $info_ww = $basic_info['kf_ww'] ? explode("\r\n", $basic_info['kf_ww']) : '';
        $info_qq = $basic_info['kf_qq'] ? explode("\r\n", $basic_info['kf_qq']) : '';
        $kf_ww = $info_ww ?  $info_ww[0] : '';
        $kf_qq = $info_qq ?  $info_qq[0] : '';
        $basic_ww = $kf_ww ? explode('|', $kf_ww) : '';
        $basic_qq = $kf_qq ? explode('|', $kf_qq) : '';
        $basic_info['kf_ww'] = $basic_ww ? $basic_ww[1] : '';
        $basic_info['kf_qq'] = $basic_qq ? $basic_qq[1] : '';
        if(($basic_info['is_IM']==1 || $basic_info['ru_id']==0) &&!empty($basic_info['kf_appkey'])){
            $basic_info['kf_appkey'] = $basic_info['kf_appkey'];
        }else{
            $basic_info['kf_appkey'] = '';
        }
        $basic_date = array('region_name');
        $basic_info['province'] = get_table_date('region', "region_id = '" . $basic_info['province'] . "'", $basic_date, 2);
        $basic_info['city'] = get_table_date('region', "region_id= '" . $basic_info['city'] . "'", $basic_date, 2) . "市";
        $this->assign('basic_info',  $basic_info);
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
        //获取商品的相册
        $sql = "SELECT * FROM {pre}goods_gallery WHERE goods_id = " . $this->goods_id;
        $goods_img = $this->db->query($sql);
        foreach ($goods_img as $key => $val) {
            $goods_img[$key]['img_url'] = get_image_path($val['img_url']);
        }
        $this->assign('goods_img', $goods_img);
        //ecmoban模板堂 --zhuo 仓库 start
        $this->assign('province_row', get_region_name($this->province_id));
        $this->assign('city_row', get_region_name($this->city_id));
        $this->assign('district_row', get_region_name($this->district_id));

        $goods_region['country'] = 1;
        $goods_region['province'] = $this->province_id;
        $goods_region['city'] = $this->city_id;
        $goods_region['district'] = $this->district_id;
        $this->assign('goods_region', $goods_region);

        //$this->assign('guess_goods',     get_guess_goods($user_id, 1, $page=1, 7,$region_id, $area_info['region_id']));         //猜你喜欢
        $this->assign('best_goods',get_recommend_goods('best', '', $this->region_id, $this->area_info['region_id'], $goods['user_id'], 1, 'presale'));    // 推荐商品
        $this->assign('new_goods', get_recommend_goods('new', '', $this->region_id, $this->area_info['region_id'], $goods['user_id'], 1, 'presale'));     // 最新商品
        $this->assign('hot_goods', get_recommend_goods('hot', '', $this->region_id, $this->area_info['region_id'], $goods['user_id'], 1, 'presale'));     // 最新商品

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

        $this->assign('goods_id', $this->goods_id); //商品ID

        $warehouse_list = get_warehouse_list_goods();
        $this->assign('warehouse_list',	$warehouse_list); //仓库列

        $warehouse_name = get_warehouse_name_id($this->region_id);

        $this->assign('warehouse_name',	$warehouse_name); //仓库名称
        $this->assign('region_id', $this->region_id); //商品仓库region_id
        $this->assign('user_id', $_SESSION['user_id']);
        $this->assign('shop_price_type', $goods['model_price']); //商品价格运营模式 0代表统一价格（默认） 1、代表仓库价格 2、代表地区价格
        $this->assign('area_id', $this->area_info['region_id']); //地区ID
        //ecmoban模板堂 --zhuo end 仓库
        $area = array(
            'region_id' => $this->region_id,  //仓库ID
            'province_id' => $this->province_id,
            'city_id' => $this->city_id,
            'district_id' => $this->district_id,
            'goods_id' => $this->goods_id,
            'user_id' => $_SESSION['user_id'],
            'area_id' => $this->area_info['region_id'],  //地区ID
            'merchant_id' => $goods['user_id'],
        );
        $this->assign('area',  $area);

        /* 取得商品的规格 */
        $properties = get_goods_properties($this->goods_id);
        $this->assign('properties', $properties['pro']);    //商品属性
        $this->assign('specification', $properties['spe']); // 商品规格
        $this->assign('cfg',          C('shop'));
        $position = assign_ur_here(0, $goods['goods_name']);
        $this->assign('page_title', $position['title']);
        $this->display();
    }


    /**
     * 改变属性、数量时重新计算商品价格
     */
    public function actionPrice()
    {
        $res = array('err_msg' => '', 'err_no' => 0, 'result' => '', 'qty' => 1);
        $attr = I('attr');
        $number = I('number', 1, 'intval');
        $this->goods_id = (isset($_REQUEST['gid'])) ? intval($_REQUEST['gid']) : 0;
        $this->preid = (isset($_REQUEST['id'])) ? intval($_REQUEST['id']) : 0;
        $attr_id = !empty($attr) ? explode(',', $attr) : array();
        $warehouse_id = I('request.warehouse_id', 0, 'intval');
        $area_id = I('request.area_id', 0, 'intval'); //仓库管理的地区ID
        $onload = I('request.onload', '', 'trim');; //仓库管理的地区ID

        $goods = get_goods_info($this->goods_id, $warehouse_id, $area_id);
        if ($this->goods_id == 0) {
            $res['err_msg'] = L('err_change_attr');
            $res['err_no'] = 1;
        } else {
            if ($number == 0) {
                $res['qty'] = $number = 1;
            } else {
                $res['qty'] = $number;
            }
            //ecmoban模板堂 --zhuo start
            $products = get_warehouse_id_attr_number($this->goods_id, $_REQUEST['attr'], $goods['user_id'], $warehouse_id, $area_id);
            $attr_number = $products['product_number'];

            if ($goods['model_attr'] == 1) {
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '$warehouse_id'";
            } elseif ($goods['model_attr'] == 2) {
                $table_products = "products_area";
                $type_files = " and area_id = '$area_id'";
            } else {
                $table_products = "products";
                $type_files = "";
            }

            $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '$this->goods_id'" . $type_files . " LIMIT 0, 1";
            $prod = $GLOBALS['db']->getRow($sql);

            if($goods['goods_type'] == 0){
                $attr_number = $goods['goods_number'];
            }else{
                if(empty($prod)){ //当商品没有属性库存时
                    $attr_number = $goods['goods_number'];
                }
            }

            if(empty($prod)){ //当商品没有属性库存时
                $res['bar_code'] = $goods['bar_code'];
            }else{
                $res['bar_code'] = $products['bar_code'];
            }

            $attr_number = !empty($attr_number) ? $attr_number : 0;
            $res['attr_number'] = $attr_number;
            //限制用户购买的数量 bywanglu
            // $res['limit_number'] = $attr_number < $number ? ($attr_number ? $attr_number : 1) : $number;
            $shop_price  = get_final_price($this->goods_id, $number, true, $attr_id, $warehouse_id, $area_id, 0,1);
            //ecmoban模板堂 --zhuo end

            $res['shop_price'] = price_format($shop_price);
            $res['market_price'] = $goods['market_price'];

            //属性价格
            $spec_price  = get_final_price($this->goods_id, $number, true, $attr_id, $warehouse_id, $area_id, 1, 0, 0, $res['show_goods']);
            $res['marketPrice_amount'] = price_format($spec_price + $goods['marketPrice']);

            //切换属性后的价格折扣 by wanglu
            $martetprice_amount = $spec_price + $goods['marketPrice'];
            $res['discount'] = round($shop_price / $martetprice_amount, 2) * 10;
            $res['result'] = price_format($shop_price * $number);

            $presale = presale_info($this->preid, $number,$attr_id,$warehouse_id, $area_id);

            $res['formated_deposit'] = $presale['formated_deposit'];
            $res['formated_final_payment'] = $presale['formated_final_payment'];
            // 属性图片
            $attr_info = get_attr_value($this->goods_id,$attr_id[0]);
            if(!empty($attr_info['attr_img_flie'])){
                $res['attr_img'] = get_image_path($attr_info['attr_img_flie']);
            }
        }
        die(json_encode($res));
    }

    /**
     * 预售商品 --> 购买
     *
     */
    public function actionBuy()
    {
        $this->check_login();

        $warehouse_id = I('request.warehouse_id', 0, 'intval');
        $area_id = (isset($_REQUEST['area_id'])) ? intval($_REQUEST['area_id']) : 0; //仓库管理的地区ID
        /* 取得参数：预售活动id */
        $presale_id = I('request.presale_id', 0, 'intval');;
        if ($presale_id <= 0) {
            ecs_header("Location: ./\n");
            exit;
        }

        /* 查询：取得数量 */
        $number = isset($_POST['number']) ? intval($_POST['number']) : 1;
        $number = $number < 1 ? 1 : $number;
        /* 查询：取得预售活动信息 */
        $presale = presale_info($presale_id, $number);
        if (empty($presale)) {
            ecs_header("Location: ./\n");
            exit;
        }
        /* 查询：检查预售活动是否是进行中 */
        if ($presale['status'] != GBS_UNDER_WAY)
        {
            show_message(L('presale_error_status'), '', '', 'error');
        }
        /* 查询：取得预售商品信息 */
        $goods = goods_info($presale['goods_id'], $warehouse_id, $area_id);
        if (empty($goods))
        {
            ecs_header("Location: ./\n");
            exit;
        }
        /* 查询：判断数量是否足够 */
        if (($goods['goods_number'] > 0 && $number > ($goods['goods_number'] - $presale['valid_goods'])))
        {
            show_message(L('gb_error_goods_lacking'), '', '', 'error');
        }
        /* 查询：取得规格 */
        $specs = isset($_POST['goods_spec']) ? htmlspecialchars(trim($_POST['goods_spec'])) : '';
        /* 查询：如果商品有规格则取规格商品信息 配件除外 */
        if ($specs)
        {
            $_specs = explode(',', $specs);
            $product_info = get_products_info($goods['goods_id'], $_specs, $warehouse_id, $area_id);
        }
        empty($product_info) ? $product_info = array('product_number' => 0, 'product_id' => 0) : '';

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

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$goods['goods_id']. "'" .$type_files. " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);

        /* 检查：库存 */
        if ($GLOBALS['_CFG']['use_storage'] == 1)
        {
            /* 查询：判断指定规格的货品数量是否足够 */
            if ($prod && $number > $product_info['product_number'])
            {
                show_message(L('gb_error_goods_lacking'), '', '', 'error');
            }else{
                /* 查询：判断数量是否足够 */
                if ($number > $goods['goods_number'])
                {
                    show_message(L('gb_error_goods_lacking'), '', '', 'error');
                }
            }
        }

        /* 查询：查询规格名称和值，不考虑价格 */
        $attr_list = array();
        $sql = "SELECT a.attr_name, g.attr_value " .
            "FROM " . $GLOBALS['ecs']->table('goods_attr') . " AS g, " .
            $GLOBALS['ecs']->table('attribute') . " AS a " .
            "WHERE g.attr_id = a.attr_id " .
            "AND g.goods_attr_id " . db_create_in($specs);
        $res = $GLOBALS['db']->query($sql);
        foreach ($res as $row)
        {
            $attr_list[] = $row['attr_name'] . ': ' . $row['attr_value'];
        }
        $goods_attr = join(chr(13) . chr(10), $attr_list);

        /* 更新：清空购物车中所有预售商品 */
        clear_cart(CART_PRESALE_GOODS);

        //ecmoban模板堂 --zhuo start

        $area_info = get_area_info($this->province_id);
        $area_id = $area_info['region_id'];

        $where = "regionId = '$this->province_id'";
        $date = array('parent_id');
        $this->region_id = get_table_date('region_warehouse', $where, $date, 2);

        if (!empty($_SESSION['user_id'])) {
            $sess = "";
        } else {
            $sess = real_cart_mac_ip();
        }
        //ecmoban模板堂 --zhuo end
        //ecmoban模板堂 --zhuo start 限购
        $nowTime = gmtime();
        $start_date = $goods['xiangou_start_date'];
        $end_date = $goods['xiangou_end_date'];

        if ($goods['is_xiangou'] == 1 && $nowTime > $start_date && $nowTime < $end_date) {
            if ($presale['total_goods'] >= $goods['xiangou_num']) {
                $result['message'] = "您已购买过" . $goods['goods_name'] . "，超过限购数量无法再购买";
                show_message($result['message'], '', '', 'error');
            } else {
                if ($goods['xiangou_num'] > 0) {
                    if ($goods['is_xiangou'] == 1 && $presale['total_goods'] + $number > $goods['xiangou_num']) {
                        $result['message'] = "该" . $goods['goods_name'] . '商品已经累计超过限购数量';
                        $number = $goods['xiangou_num'] - $presale['total_goods'];
                    }
                }
            }
        }
        //ecmoban模板堂 --zhuo end 限购
        /* 更新：加入购物车 */
        $cart = array(
            'user_id'        => $_SESSION['user_id'],
            'session_id'     => $sess,
            'goods_id'       => $presale['goods_id'],
            'product_id'     => $product_info['product_id'],
            'goods_sn'       => addslashes($goods['goods_sn']),
            'goods_name'     => addslashes($goods['goods_name']),
            'market_price'   => $goods['market_price'],
            'goods_price'    => $goods['goods_price'],
            'goods_number'   => $number,
            'goods_attr'     => addslashes($goods_attr),
            'goods_attr_id'  => $specs,
            //ecmoban模板堂 --zhuo start
            'ru_id'			 => $goods['user_id'],
            'warehouse_id'   => $this->region_id,
            'area_id'  		 => $area_id,
            //ecmoban模板堂 --zhuo end
            'is_real'        => $goods['is_real'],
            'extension_code' => 'presale',
            'parent_id'      => 0,
            'rec_type'       => CART_PRESALE_GOODS,
            'is_gift'        => 0
        );
        $this->db->autoExecute($GLOBALS['ecs']->table('cart'), $cart, 'INSERT');

        /* 更新：记录购物流程类型：预售 */
        $_SESSION['flow_type'] = CART_PRESALE_GOODS;
        $_SESSION['extension_code'] = 'presale';
        $_SESSION['cart_value'] = '';
        $_SESSION['extension_id'] = $presale['act_id'];

        /* 进入收货人页面 */
        $_SESSION['browse_trace'] = "presale";
        $this->redirect('flow/index/index');

    }


    /**
     * 验证是否登录
     */
    private function check_login()
    {
        if (!$_SESSION['user_id']) {
            $url = urlencode(__HOST__ . $_SERVER['REQUEST_URI']);
            if (IS_POST) {
                $url = urlencode($_SERVER['HTTP_REFERER']);
            }
            ecs_header("Location: " . url('user/login/index', array('back_act' => $url)));
            exit;
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

    /**
     * 取得某页的所有预售商品
     *
     */
    private function get_pre_goods($cat_id, $status=0, $sort='cat_id', $order='DESC' ,$page = 1, $size = 10,$keyword = '')
    {
        $now = gmtime();
        $where = '';
        if ($cat_id > 0)
        {
            $where = "AND a.cat_id = '$cat_id' ";
        }
        //1未开始，2进行中，3结束
        if ($status == 1) {
            $where .= " AND a.start_time > $now ";
        } elseif ($status == 2) {
            $where .= " AND a.start_time < $now AND $now < a.end_time ";
        } elseif ($status == 3) {
            $where .= " AND $now > a.end_time ";
        }
        if ($sort == 'shop_price') {
            $sort = "g.$sort";
        } else {
            $sort = "a.$sort";
        }
        if ($keyword) {
            $where .= " AND g.goods_name like '%$keyword%' ";
        }
        $sql = "SELECT COUNT(*) as total FROM ".
            $GLOBALS['ecs']->table('presale_activity')." AS a " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON a.goods_id = g.goods_id " .
            " WHERE g.goods_id > 0 AND a.review_status = 3 and g.is_on_sale = 0 $where";
        $total =  $GLOBALS['db']->getOne($sql);
        $total ? $total : 0;

        $sql = "SELECT a.*, g.goods_thumb, g.goods_img, g.goods_name, g.shop_price, g.market_price, g.sales_volume FROM ".
                $GLOBALS['ecs']->table('presale_activity')." AS a " .
               " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON a.goods_id = g.goods_id " .
               " WHERE g.goods_id > 0 $where AND g.is_on_sale = 0 AND a.review_status = 4 ORDER BY $sort $order LIMIT ". ($page - 1) * $size . ",  $size";
        $res =  $GLOBALS['db']->getAll($sql);

        foreach ($res as $key => $row) {
            $res[$key]['thumb'] = get_image_path($row['goods_thumb']);
            $res[$key]['goods_img'] = get_image_path($row['goods_img']);
            $res[$key]['url'] = build_uri('presale', array('r' => 'index/detail', 'id' => $row['act_id']));

            if ($row['start_time'] >= $now) {
                $res[$key]['status'] = 1;
                $res[$key]['short_format_date'] = short_format_date($row['start_time']);
            }else if($row['end_time'] < $now){
                $res[$key]['status'] = 3;
            }else{
                $res[$key]['short_format_date'] = short_format_date($row['end_time']);
            }
        }
        return array('total' => $total,'list' => $res );

    }
}
<?php
namespace app\http\exchange\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend
{
    private $user_id = 0;
    private $goods_id = 0;
    private $region_id = 0;
    private $cat_id = 0;
    private $children = 0;
    private $area_info = array();
    private $sort = 'goods_id';
    private $order = '';

    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH  . C('shop.lang') . '/exchange.php'));
        //初始化位置信息
        $this->init_params();
    }

    public function actionIndex()
    {
        $default_sort_order_method = C('sort_order_method') == '0' ? 'ASC' : 'DESC';
        $this->order = (isset($_REQUEST['order']) && in_array(trim(strtoupper($_REQUEST['order'])), array('ASC', 'DESC'))) ? trim($_REQUEST['order']) : $default_sort_order_method;
        $this->size = 10;
        $page = I('post.page', 1, 'intval');
        $this->children = get_children($this->cat_id);
        if (IS_AJAX) {
            $this->sort = (isset($_REQUEST ['sort']) && in_array(trim(strtolower($_REQUEST ['sort'])), array(
                    'goods_id',
                    'amount',
                    'popularity',
                    'integral'
                ))) ? trim($_REQUEST ['sort']) : 'goods_id';
            if ($this->sort == 'popularity') {
                $this->sort = 'exchange_integral';
            }
            if ($this->sort == 'amount') {
                $this->sort = 'sales_volume';
            }
            $count = get_exchange_goods_count($this->children);
            $max_page = ($count > 0) ? ceil($count / $this->size) : 1;
            if ($page > $max_page) {
                $page = $max_page;
            }
            $goodslist = exchange_get_goods($this->children, 0, 0, '', $this->size, $page, $this->sort, $this->order);
            die(json_encode(array('goodslist' => $goodslist, 'totalPage' => ceil($count / $this->size))));
        }

        $this->assign('page_title', L('integral_shop'));
        $this->display();
    }

    public function actionDetail()
    {
        $this->goods_id = I('id', 0, 'intval');
        if ($this->goods_id == 0) {
            /* 如果没有传入id则跳回到首页 */
            ecs_header("Location: ./\n");
            exit;
        }
        $this->user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        $goods = get_exchange_goods_info($this->goods_id);
        $this->assign('goods', $goods);
        $this->assign('integral_scale', price_format($GLOBALS['_CFG']['integral_scale']));
        //获取商品的属性
        $properties = get_goods_properties($this->goods_id);
        $this->assign('properties', $properties['spe']);
        $default_spe = '';
        if ($properties['spe']) {
            foreach ($properties['spe'] as $k => $v) {
                if ($v['attr_type'] == 1) {
                    if ($v['is_checked'] > 0) {
                        foreach ($v['values'] as $key => $val) {
                            $default_spe .= $val['checked'] ? $val['label'] . '、' : '';
                        }
                    } else {
                        foreach ($v['values'] as $key => $val) {
                            if ($key == 0) {
                                $default_spe .= $val['label'] . '、';
                            }
                        }
                    }
                }
            }
        }
        $default_spe = $default_spe . '1个';
        $this->assign('default_spe', $default_spe);                              // 商品规格
        $this->assign('specification', $properties['spe']);
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

        $warehouse_list = get_warehouse_list_goods();
        $this->assign('warehouse_list', $warehouse_list); //仓库列

        $warehouse_name = get_warehouse_name_id($this->region_id);

        $this->assign('warehouse_name', $warehouse_name); //仓库名称
        $this->assign('region_id', $this->region_id); //商品仓库region_id
        $this->assign('user_id', $_SESSION['user_id']);
        $this->assign('shop_price_type', $goods['model_price']); //商品价格运营模式 0代表统一价格（默认） 1、代表仓库价格 2、代表地区价格
        $this->assign('area_id', $this->area_info['region_id']); //地区ID

        //店铺关注人数 by wanglu
        $sql = "SELECT count(*) FROM " . $this->ecs->table('collect_store') . " WHERE ru_id = " . $goods['user_id'];
        $collect_number = $this->db->getOne($sql);
        $this->assign('collect_number', $collect_number ? $collect_number : 0);
        //评分 end
        //新品
        $new_goods = get_recommend_goods('new', '', $this->region_id, $this->area_info['region_id'], $goods['user_id']);
        $this->assign('new_goods', $new_goods);

        //客服start
        $sql="select b.is_IM, a.ru_id,a.province, a.city, a.kf_type, a.kf_ww, a.kf_qq, a.meiqia, a.shop_name, a.kf_appkey from {pre}seller_shopinfo as a left join {pre}merchants_shop_information as b on a.ru_id=b.user_id where ru_id='" .$goods['user_id']. "' ";
        $basic_info = $this->db->getRow($sql);
        $basic_date = array('region_name');
        $basic_info['province'] = get_table_date('region', "region_id = '" . $basic_info['province'] . "'", $basic_date, 2);
        $basic_info['city'] = get_table_date('region', "region_id= '" . $basic_info['city'] . "'", $basic_date, 2) . "市";
        if(($basic_info['is_IM']==1 || $basic_info['ru_id']==0) &&!empty($basic_info['kf_appkey'])){
           $basic_info['kf_appkey'] = $basic_info['kf_appkey'];
        }else{
           $basic_info['kf_appkey'] = '';
        }
        $this->assign('basic_info', $basic_info);
        //客服end
        //商品详情start
        $info = $this->db->table('goods')->field('goods_desc')->where(array('goods_id' => $this->goods_id))->find();
        $properties = get_goods_properties($this->goods_id, $this->region_id, $this->area_info['region_id']);  // 获得商品的规格和属性
        $info['goods_desc'] = str_replace('src="images/upload', 'src="' . __STATIC__ . '/images/upload', $info['goods_desc']);
        $this->assign('goods_desc', $info['goods_desc']);
        //商品详情end
        // 商品属性
        $this->assign('properties', $properties['pro']);
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
        //商品配件
        $group = get_goods_fittings($this->goods_id);
        $this->assign('group', $group);
        $this->assign("user", get_user_info($_SESSION['user_id']));
        $this->assign('page_title', L('integral_goods'));
        $this->display();
    }

    /**
     * 数量属性切换
     */
    public function actionExprice()
    {
        $res = array('err_msg' => '', 'err_no' => 0, 'result' => '', 'qty' => 1);
        $goods_id = (isset($_REQUEST['id'])) ? intval($_REQUEST['id']) : 0; //仓库管理的地区ID
        $attr_id = isset($_REQUEST['attr']) ? explode(',', $_REQUEST['attr']) : array();
        $number = (isset($_REQUEST['number'])) ? intval($_REQUEST['number']) : 1;
        $warehouse_id = (isset($_REQUEST['warehouse_id'])) ? intval($_REQUEST['warehouse_id']) : 0;
        $area_id = (isset($_REQUEST['area_id'])) ? intval($_REQUEST['area_id']) : 0; //仓库管理的地区ID
        $onload = (isset($_REQUEST['onload'])) ? trim($_REQUEST['onload']) : ''; //仓库管理的地区ID
        $goods = get_goods_info($goods_id, $warehouse_id, $area_id);
        if ($goods_id == 0) {
            $res['err_msg'] = L('err_change_attr');
            $res['err_no'] = 1;
        } else {
            if ($number == 0) {
                $res['qty'] = $number = 1;
            } else {
                $res['qty'] = $number;
            }
            //ecmoban模板堂 --zhuo start
            $products = get_warehouse_id_attr_number($goods_id, $_REQUEST['attr'], $goods['user_id'], $warehouse_id, $area_id);
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
            $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '$goods_id'" . $type_files . " LIMIT 0, 1";
            $prod = $GLOBALS['db']->getRow($sql);

            if (empty($prod)) { //当商品没有属性库存时
                $attr_number = $goods['goods_number'];
            }

            $attr_number = !empty($attr_number) ? $attr_number : 0;

            $res['attr_number'] = $attr_number;
        }

        if ($GLOBALS['_CFG']['open_area_goods'] == 1) {

            $area_list = get_goods_link_area_list($goods_id, $goods['user_id']);
            if ($area_list['goods_area']) {
                if (!in_array($area_id, $area_list['goods_area'])) {
                    $res['err_no'] = 2;
                }
            } else {
                $res['err_no'] = 2;
            }
        }
        $attr_info = get_attr_value($this->goods_id, $attr_id[0]);
        if (!empty($attr_info['attr_img_flie'])) {
            $res['attr_img'] = get_image_path($attr_info['attr_img_flie']);
        }
        $goods = get_exchange_goods_info($goods_id, $this->region_id, $this->area_info['region_id']);
        $res['t_ex_integral'] = ($goods['exchange_integral'] * $res['qty']);
        die(json_encode($res));
    }

    /**
     * 积分兑换
     */
    public function actionExbuy()
    {
        $warehouse_id = I('request.warehouse_id', 0, 'intval');
        $goods_number = isset($_POST['number']) ? intval($_POST['number']) : 0;
        $good_id = I('request.good_id', 0, 'intval');
        if ($good_id <= 0) {
            ecs_header("Location: ./\n");
            exit;
        }
        $attr = I('attr');
        $attr_id = !empty($attr) ? explode(',', $attr) : array();
        $this->check_login();
        $goods = get_exchange_goods_info($good_id,$this->region_id, $this->area_info['region_id']);
        if ($goods['is_exchange'] == 0) {
            die(json_encode(array('msg' => L('eg_error_status'))));
        }
        $user_info = get_user_info($_SESSION['user_id']);
        $user_points = $user_info['pay_points']; // 用户的积分总数
        if ($goods['exchange_integral'] > $user_points) {
            die(json_encode(array('msg' => L('eg_error_integral'))));
        }
        /* 查询：取得规格 */
        $specs = '';
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'spec_') !== false) {
                $specs .= ',' . intval($value);
            }
        }
        $specs = trim($specs, ',');
        /* 查询：如果商品有规格则取规格商品信息 配件除外 */
        if (!empty($specs)) {
            $_specs = explode(',', $specs);
            $product_info = get_products_info($good_id, $_specs, $this->region_id, $this->area_info['region_id']);
        }
        if (empty($product_info)) {
            $product_info = array('product_number' => '', 'product_id' => 0);
        }

        if ($goods['model_attr'] == 1) {
            $table_products = "products_warehouse";
            $type_files = " and warehouse_id = '$warehouse_id'";
        } elseif ($goods['model_attr'] == 2) {
            $table_products = "products_area";
            $type_files = " and area_id = " . $this->area_info['region_id'] . " ";
        } else {
            $table_products = "products";
            $type_files = "";
        }

        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '" . $goods['goods_id'] . "'" . $type_files . " LIMIT 0, 1";
        $prod = $GLOBALS['db']->getRow($sql);
        //ecmoban模板堂 --zhuo start
        //查询：商品存在规格 是货品 检查该货品库存
        if ($GLOBALS['_CFG']['use_storage'] == 1) {
            $is_product = 0;
            if (is_spec($_specs) && (!empty($prod))) {
                if (($product_info['product_number'] == 0)) {
                    die(json_encode(array('msg' => L('eg_error_number'))));
                }
            } else {
                $is_product = 1;
            }

            if ($is_product == 1) {
                /* 查询：检查兑换商品是否有库存 */
                if ($goods['goods_number'] == 0) {
                    die(json_encode(array('msg' => L('eg_error_number'))));
                }
            }
        }
        //ecmoban模板堂 --zhuo end
        /* 查询：查询规格名称和值，不考虑价格 */
        $attr_list = array();
        $sql = "SELECT a.attr_name, g.attr_value " .
            "FROM " . $GLOBALS['ecs']->table('goods_attr') . " AS g, " .
            $GLOBALS['ecs']->table('attribute') . " AS a " .
            "WHERE g.attr_id = a.attr_id " .
            "AND g.goods_attr_id " . db_create_in($specs);
        $res = $GLOBALS['db']->query($sql);
        foreach ($res as $row){
            $attr_list[] = $row['attr_name'] . ': ' . $row['attr_value'];
        }
        $goods_attr = join(chr(13) . chr(10), $attr_list);

        clear_cart(CART_EXCHANGE_GOODS);

        //积分兑换 ecmoban模板堂 --zhuo
        $goods['exchange_integral'] = $goods['exchange_integral'] * $GLOBALS['_CFG']['integral_scale'] / 100;

        /* 更新：加入购物车 */
        $cart = array(
            'user_id' => $_SESSION['user_id'],
            'session_id' => SESS_ID,
            'goods_id' => $goods['goods_id'],
            'product_id' => $product_info['product_id'],
            'goods_sn' => addslashes($goods['goods_sn']),
            'goods_name' => addslashes($goods['goods_name']),
            'market_price' => $goods['marketPrice'],
            'goods_price' => 0, //$goods['exchange_integral']
            'goods_number' => $goods_number,
            'goods_attr' => addslashes($goods_attr),
            'goods_attr_id' => $specs,
            'warehouse_id' => $this->region_id, //ecmoban模板堂 --zhuo 仓库
            'area_id' => $this->area_info['region_id'], //ecmoban模板堂 --zhuo 仓库地区
            'ru_id' => $goods['user_id'],
            'is_real' => $goods['is_real'],
            'extension_code' => addslashes($goods['extension_code']),
            'parent_id' => 0,
            'rec_type' => CART_EXCHANGE_GOODS,
            'is_gift' => 0
        );
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('cart'), $cart, 'INSERT');

        /* 记录购物流程类型：团购 */
        $_SESSION['flow_type'] = CART_EXCHANGE_GOODS;
        $_SESSION['extension_code'] = 'exchange_goods';
        $_SESSION['extension_id'] = $good_id;
        $_SESSION['cart_value'] = '';
        $_SESSION['direct_shopping'] = 4;

        /* 进入收货人页面 */
        $this->redirect('flow/index/index',array('direct_shopping' => 4 ));
        exit;

    }

    /**
     * 验证是否登录
     */
    private function check_login()
    {
        if (!($_SESSION['user_id'] > 0)) {
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


}

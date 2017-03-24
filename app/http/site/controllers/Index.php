<?php
namespace app\http\site\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend {
    private $page = 1;
    private $size = 10;
    private $user_id = 0;
    private $goods_id = 0;
    private $region_id = 0;
    private $area_info = array();


    public function __construct()
    {
        parent::__construct();
        //初始化位置信息
        //$this->init_params();
        L(require(LANG_PATH  . C('shop.lang') . '/other.php'));
    }

    /**
     * 首页信息
     */
    public function actionIndex()
    {
        $this->init_params();
        // 是否开启wap
        if(!$GLOBALS['_CFG']['wap_config']){
          die(L('wap_config'));
        }

        $this->assign('cart_number', cart_number());//购物车数量
        $this->assign('hot_click', get_hot_click());// 获取热门搜索
        $this->assign('store', get_store());// 获取店铺
        $this->assign('brand_list', get_brand());// 获取brand
        $this->assign('promotion_goods', limit_grab($this->region_id, $this->area_info['region_id']));// 限时购

        foreach(limit_grab() as $key => $val){
            $start_time = $val['promote_start_date'];
            $end_time = $val['promote_end_date'];
            break;
        }
        $logo = empty($GLOBALS['_CFG']['wap_logo']) ? elixir('img/d_logo.png') : $GLOBALS['_CFG']['wap_logo'];
        $app  =  $GLOBALS['_CFG']['wap_index_pro'] ? 1 :0 ;
        $this->assign('app',$app);
        $this->assign('logo',$logo);
        $this->assign('end_time',$end_time);
        $this->assign('best_goods', goods_list('best', $this->page, 4,$this->region_id, $this->area_info['region_id']));    // 推荐商品
        $sql = "SELECT * FROM {pre}touch_nav WHERE ifshow=1 order by vieworder asc, id asc";
        $nav = $this->db->getAll($sql);
        $position = assign_ur_here();
        $this->assign('page_title', $position['title']);
        /* meta information */
        $this->assign('keywords',        htmlspecialchars(C('shop.shop_keywords')));
        $this->assign('description',     htmlspecialchars(C('shop.shop_desc')));
        $this->assign('nav',$nav);
        // 站内快讯
        $article_condition = array(
            'is_open' => 1,
            'cat_id' => 12
        );
        $article_list = $this->model->table('article')->field('article_id, title, author, add_time, file_url, open_type')
            ->where($article_condition)->order('article_type DESC, article_id DESC')->limit(5)->select();
        foreach($article_list as $key => $vo){
            $article_list[$key]['add_time'] = date('Y-m-d', $vo['add_time']);
            $article_list[$key]['url'] = build_uri('article', array('aid' => $vo['article_id']));
        }
        $this->assign('article', $article_list);
        //get_ads('index');
        $this->display();
    }
    /**
     * 异步加载商品列表
     */

    public function actionAsync(){
        if(IS_AJAX){
            $this->size = I('size');
            $this->page = I('page');
            $cache_name = 'index_goods_list_' . $this->page . '_' . $this->size;
            $get_goods_list = cache($cache_name);
            if($get_goods_list === false){
                $get_goods_list = goods_list('hot', $this->page, $this->size, $this->region_id, $this->area_info['region_id']);
                $count = count_number('hot');
                $count = ceil($count/$this->size);
                shuffle($get_goods_list);
                $get_goods_list = array('list' => $get_goods_list, 'totalPage'=>$count);
                cache($cache_name, $get_goods_list);
            }
            die(json_encode($get_goods_list));
        }

    }

    /**
     * 更多
     */
    public function actionMore(){
        //取得店铺标题
        $page_title = $GLOBALS['_CFG']['shop_title'];
        $this->assign('page_title',$page_title);
        $this->display();
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

            $goods_warehouse = get_warehouse_goods_region($this->province_id);
            $this->region_id  = $goods_warehouse['region_id'];
        }
        //ecmoban模板堂 --zhuo end 仓库
        $this->area_info = get_area_info($this->province_id);
    }
}

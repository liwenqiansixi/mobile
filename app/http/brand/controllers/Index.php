<?php
namespace app\http\brand\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend {

    private $brand = 0;
    private $size = 1000;
    private $page = 1;
    private $sort = 'goods_id';
    private $order = 'ASC';
    private $region_id = 0;
    private $area_id = 0;
    private $is_ship = '';
    private $self = '';
    private $mbid = 0;
    private $cate = 0;
    private $type = '';
    private $price_min = '';
    private $price_max = '';

    public function __construct()
    {
        parent::__construct();
        L(require(LANG_PATH  . C('shop.lang') . '/user.php'));
        L(require(LANG_PATH  . C('shop.lang') . '/flow.php'));

        $province_id = isset($_COOKIE['province']) ? $_COOKIE['province'] : 0;
        $city_id = isset($_COOKIE['city']) ? $_COOKIE['city'] : 0;
        $district_id = isset($_COOKIE['district']) ? $_COOKIE['district'] : 0;

        $area_info = get_area_info($province_id);
        $this->area_id = $area_info['region_id'];

        $where = "regionId = '$province_id'";
        $date = array('parent_id');
        $this->region_id = get_table_date('region_warehouse', $where, $date, 2);

        if(isset($_COOKIE['region_id']) && !empty($_COOKIE['region_id'])){
            $this->region_id = $_COOKIE['region_id'];
        }
        /* 获得请求的平台品牌ID */
        $this->brand_id = I('get.id', 0, 'intval');

        $this->is_ship = I('request.ship', 0, 'intval');//by wang是否包邮
        $this->sort = I('request.sort', 'goods_id', 'trim');
        $this->order = I('request.order', 'DESC', 'trim');
        /* 获得请求的商家品牌ID */
        $this->mbid = I('request.mbid', 0, 'intval');
        $this->cate = I('request.cat', 0, 'intval'); //品牌关联分类ID

        $this->price_min = !empty($_REQUEST['price_min'])  &&floatval($_REQUEST['price_min'])  > 0 ? floatval($_REQUEST['price_min'])  : '';
        $this->price_max = !empty($_REQUEST['price_max'])  &&floatval($_REQUEST['price_max'])  > 0 ? floatval($_REQUEST['price_max'])  : '';

    }

    public function  actionIndex()
    {
        $n = getdate();
        $this->assign('mon', $n['mon']);
        $this->assign('mday', $n['mday']);
        $list = cache('brands_index');
        if(!$list){
            $list = $this->get_brands_hj();
            if($list){
                if($list['top']) {
                    $act = '';
                    foreach ($list['top'] as $key => $val) {
                        $list['top'][$key]['goods'] = brand_get_goods($val['brand_id'], $this->mbid, $this->cate, $this->size, $this->page, $this->sort, $this->order, $this->region_id, $this->area_id, $act, $this->is_ship, $this->price_min, $this->price_max);
                        $arr_tmp=array();
                        foreach($list['top'][$key]['goods'] as $k=>$v){
                            if($list['top'][$key]['goods'][$k]['firsttype'] == 'hot'){
                                $arr_tmp[]=$list['top'][$key]['goods'][$k];
                            }
                        }
                        foreach($list['top'][$key]['goods'] as $k=>$v){
                            if($list['top'][$key]['goods'][$k]['firsttype'] == 'new'){
                                $arr_tmp[]=$list['top'][$key]['goods'][$k];
                            }
                        }
                        foreach($list['top'][$key]['goods'] as $k=>$v){
                            if($list['top'][$key]['goods'][$k]['firsttype'] == 'promote'){
                                $arr_tmp[]=$list['top'][$key]['goods'][$k];
                            }
                        }
                        foreach($list['top'][$key]['goods'] as $k=>$v){
                            if($list['top'][$key]['goods'][$k]['firsttype'] == 'best'){
                                $arr_tmp[]=$list['top'][$key]['goods'][$k];
                            }
                        }
                        foreach($list['top'][$key]['goods'] as $k=>$v){
                            if($list['top'][$key]['goods'][$k]['firsttype'] == 'ordinary'){
                                $arr_tmp[]=$list['top'][$key]['goods'][$k];
                            }
                        }
                        $list['top'][$key]['goods'] = $arr_tmp;
                    }
                }
                if($list['center']){
                    $act = '';
                    foreach ($list['center'] as $key => $val) {
                        // 商品数量
                        $list['center'][$key]['count']=goods_count_by_brand($val['brand_id']);
                        if(!$list['center'][$key]['count']){
                            $list['center'][$key]['count'] = 0;
                        }
                        $list['center'][$key]['goods'] = brand_get_goods($val['brand_id'], $this->mbid, $this->cate, $this->size, $this->page, $this->sort, $this->order, $this->region_id, $this->area_id, $act, $this->is_ship, $this->price_min, $this->price_max);
                        $arr_tmp=array();
                        foreach($list['center'][$key]['goods'] as $k=>$v){
                            if($list['center'][$key]['goods'][$k]['firsttype'] == 'hot'){
                                $arr_tmp[]=$list['center'][$key]['goods'][$k];
                            }
                        }
                        foreach($list['center'][$key]['goods'] as $k=>$v){
                            if($list['center'][$key]['goods'][$k]['firsttype'] == 'promote'){
                                $arr_tmp[]=$list['center'][$key]['goods'][$k];
                            }
                        }
                        foreach($list['center'][$key]['goods'] as $k=>$v){
                            if($list['center'][$key]['goods'][$k]['firsttype'] == 'new'){
                                $arr_tmp[]=$list['center'][$key]['goods'][$k];
                            }
                        }
                        foreach($list['center'][$key]['goods'] as $k=>$v){
                            if($list['center'][$key]['goods'][$k]['firsttype'] == 'best'){
                                $arr_tmp[]=$list['center'][$key]['goods'][$k];
                            }
                        }
                        foreach($list['center'][$key]['goods'] as $k=>$v){
                            if($list['center'][$key]['goods'][$k]['firsttype'] == 'ordinary'){
                                $arr_tmp[]=$list['center'][$key]['goods'][$k];
                            }
                        }
                        $list['center'][$key]['goods'] = $arr_tmp;
                    }
                }

            }
            cache('brands_index', $list);
        }

        foreach ($list['top'] as $k=>$v){
            $list1 = $list['top'][$k]['goods'];
        }
        $this->assign('list1', $list1[0]['goods_thumb']);

        foreach ($list['top'] as $k=>$v){
            array_shift($list['top'][$k]['goods']);
        }

        $this->assign('list',$list);

        $brand_count = count($list['list2']);
        $brand15_tmp = array_rand($list['list2'],($brand_count-15));
        foreach ($brand15_tmp as $key => $value){
            unset($list['list2'][$value]);
        }

        $this->assign('brand15',$list['list2']);
        $this->assign('page_title',L('brand_street'));
        $this->display();
    }
    /**
     * 获得品牌列表
     *
     * @global type $page_libs
     * @staticvar null $static_page_libs
     * @param type $cat
     * @param type $app
     * @param type $size
     * @param type $page
     * @return type
     */
    private function get_brands_hj()
    {
        $sql = "SELECT brand_id, brand_name, brand_logo, brand_desc FROM {pre}brand WHERE is_show = 1 GROUP BY brand_id , sort_order order by sort_order ASC";

        $res = $this->db->getAll($sql);
        // $cate = 0; $act = '';
        // foreach ($res as $k=>$v){
        //   $r = brand_get_goods($v['brand_id'], $cate, $this->size, $this->page, $this->sort, $this->order, $this->region_id, $this->area_id, $act, $this->is_ship, $this->price_min, $this->price_max);
        //    if(count($r) <= 3){
        //        unset($res[$k]);
        //    }
        // }
        $res = array_values($res);
        $arr = array();
        foreach ($res as $key=>$row) {

            if($key == 0 ){
                $arr['top'][$row['brand_id']]['brand_id']   =   $row['brand_id'];
                $arr['top'][$row['brand_id']]['brand_name'] =   $row['brand_name'];
                $arr['top'][$row['brand_id']]['url']    =  build_uri('brand', array('bid' => $row['brand_id']));
                $arr['top'][$row['brand_id']]['brand_logo'] =  get_data_path($row['brand_logo'], 'brandlogo/');
                $arr['top'][$row['brand_id']]['goods_num']  =   goods_count_by_brand($row['brand_id']);
                $arr['top'][$row['brand_id']]['brand_desc'] =   htmlspecialchars($row['brand_desc'], ENT_QUOTES);
            }elseif($key > 0 && $key < 4 ){
                $arr['center'][$row['brand_id']]['brand_id']   =   $row['brand_id'];
                $arr['center'][$row['brand_id']]['brand_name'] =   $row['brand_name'];
                $arr['center'][$row['brand_id']]['url']    =  build_uri('brand', array('bid' => $row['brand_id']));
                $arr['center'][$row['brand_id']]['brand_logo'] =   get_data_path($row['brand_logo'], 'brandlogo/');
                $arr['center'][$row['brand_id']]['goods_num']  =   goods_count_by_brand($row['brand_id']);
                $arr['center'][$row['brand_id']]['brand_desc'] =   htmlspecialchars($row['brand_desc'], ENT_QUOTES);
            }elseif($key > 4 && $key < 4){
                $arr['list1'][$row['brand_id']]['brand_id']   =   $row['brand_id'];
                $arr['list1'][$row['brand_id']]['brand_name'] =   $row['brand_name'];
                $arr['list1'][$row['brand_id']]['url']    =  build_uri('brand', array('bid' => $row['brand_id']));
                $arr['list1'][$row['brand_id']]['brand_logo'] =   get_data_path($row['brand_logo'], 'brandlogo/');
                $arr['list1'][$row['brand_id']]['goods_num']  =   goods_count_by_brand($row['brand_id']);
                $arr['list1'][$row['brand_id']]['brand_desc'] =   htmlspecialchars($row['brand_desc'], ENT_QUOTES);
            }else{
                $arr['list2'][$row['brand_id']]['brand_id']   =   $row['brand_id'];
                $arr['list2'][$row['brand_id']]['brand_name'] =   $row['brand_name'];
                $arr['list2'][$row['brand_id']]['url']    =      build_uri('brand', array('bid' => $row['brand_id']));
                $arr['list2'][$row['brand_id']]['brand_logo'] =  get_data_path($row['brand_logo'], 'brandlogo/');
                $arr['list2'][$row['brand_id']]['goods_num']  =   goods_count_by_brand($row['brand_id']);
                $arr['list2'][$row['brand_id']]['brand_desc'] =   htmlspecialchars($row['brand_desc'], ENT_QUOTES);
            }

        }
        return $arr;
    }
    public function actionNav()
    {
        $list = read_static_cache('brand_list');
        if($list === false){
            $list = get_brands_letter('brand', 1000, 1);
            write_static_cache('brand_list', $list);
        }
        $this->assign('list', $list);
        $this->assign('page_title',L('all_brand'));
        $this->display();
    }
    /**
     * 品牌商品列表
     */
    public function actionDetail()
    {
        $brand_id = $this->brand_id;
        if(empty($brand_id)) {
            ecs_header("Location: " . url('index'));
            exit;
        }
        $this->assign('brand_id', $brand_id);
        $this->assign('mbid', $this->mbid);

        // 用于异步请求品牌商品列表
        $type = I('request.type','','trim');  //商品属性
        $type_arr = array('promote','new','hot','best','ordinary');
        if(in_array($type, $type_arr)){
            $this->type = $type;
        }else{
            $this->type = '';
        }
        $this->assign('type', $this->type);
        $this->assign('cate', $this->cate);

        $this->assign('brand_cat_list', brand_related_cat($brand_id)); // 品牌相关分类

        if($mbid){
            $mact = 'merchants_brands';
            $brand_info = get_brand_info($mbid, $mact);
        }else{
            $brand_info = get_brand_info($brand_id);
        }
        $brand_info['brand_logo'] = get_data_path($brand_info['brand_logo'], 'brandlogo/');
        $this->assign('brand_info',$brand_info);
        if (empty($brand_info)) {
            $this->redirect('index');
        }
        $this->assign('page', $this->page);
        $this->assign('size', $this->size);

        $count = goods_count_by_brand($brand_id, $mbid, $this->cate, $act, $this->is_ship, $this->price_min, $this->price_max, $this->region_id, $this->area_id, $this->self);
        $goods_list = brand_get_goods($brand_id, $mbid, $this->cate, $this->size, $this->page, $this->sort, $this->order, $this->region_id, $this->area_id, '', $this->is_ship, $this->price_min, $this->price_max, $this->type);

        // 新品 精品 推荐
        $new_count = goods_count_by_brand($brand_id, $mbid, $this->cate, $act, $this->is_ship, $this->price_min, $this->price_max, $this->region_id, $this->area_id,$this->self, 'new');
        $hot_count = goods_count_by_brand($brand_id, $mbid, $this->cate, $act, $this->is_ship, $this->price_min, $this->price_max, $this->region_id, $this->area_id,$this->self, 'hot');
        $promote_count = goods_count_by_brand($brand_id, $mbid, $this->cate, $act, $this->is_ship, $this->price_min, $this->price_max, $this->region_id, $this->area_id,$this->self,'promote');
        $best_count = goods_count_by_brand($brand_id, $mbid, $this->cate, $act, $this->is_ship, $this->price_min, $this->price_max, $this->region_id, $this->area_id,$this->self, 'best');

        $this->assign('brand_goods_new', $new_count);
        $this->assign('brand_goods_promote', $promote_count);
        $this->assign('brand_goods_best', $best_count);
        $this->assign('brand_goods_hot', $hot_count);

        $this->assign('show_marketprice', C('show_marketprice'));

        // 商品数量
        // $brand_goods_count = count($arr_tmp);
        $this->assign('brand_goods_count',$count);
        $this->assign('page_title', $brand_info['brand_name']);
        $this->display();
    }

    public function actionGoods()
    {
        if(IS_AJAX){
            /* 获得请求的平台品牌ID */
            $brand_id = I('get.id', 0, 'intval');
            $page = I('post.page', 1, 'intval');
            $size = 10;
            $act = '';
            $goods_list = brand_get_goods_ajax($brand_id, $this->mbid, $this->cate, $size, $page, $this->sort, $this->order,$this->region_id, $this->area_id, $act, $this->is_ship, $this->price_min,$this->price_max, $this->type);

            die(json_encode(array('list'=>$goods_list['list'],'totalPage'=>$goods_list['totalpage'])));
        }
    }

}

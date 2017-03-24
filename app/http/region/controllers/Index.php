<?php
namespace app\http\region\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend {

/*    public function actionIndex()
    {
        $type = I('type', 0);
        $parent = I('parent', 0);

        $arr['regions'] = get_regions($type, $parent);
        $arr['type'] = $type;
        $arr['target'] = I('target', '');
        exit(json_encode($arr));
    }*/

    public function __construct()
    {
        parent::__construct();
        //初始化位置信息
        //$this->init_params();
        L(require(LANG_PATH  . C('shop.lang') . '/other.php'));
    }

    /**
     * 地区筛选
     */
    public function actionIndex() {
        $type = I('get.type', 0, 'intval');
        $parent = I('get.parent', 0, 'intval');
        $user_id = I('get.user_id', 0, 'intval');

        $regions = get_regions($type, $parent);
        //查询省下级，市区
        if($type == 2 && !empty($regions)){
            //查询市区下级,县
            foreach($regions as $k=>$v){
                $regions[$k]['district'] = get_regions(3, $v['region_id']);
            }
        }
        $arr['regions'] = $regions;
        $arr['type'] = $type;
        $arr['user_id'] = $user_id;

        if($user_id){
            $user_address = get_user_address_region($user_id);
            $user_address = explode(",",$user_address['region_address']);

            if(in_array($parent, $user_address)){
                $arr['isRegion'] = 1;
            }else{
                $arr['isRegion'] = 88; //原为0
                $arr['message'] = L('input_dispatch_addr');
                $arr['province'] = $_COOKIE['province'];
                $arr['city'] = $_COOKIE['city'];
            }
        }

        if(empty($arr['regions'])){
            $arr['empty_type'] = 1;
        }
        echo json_encode($arr);
    }

    /**
     * 商品列表筛选城市切换
     */
    public function actionSelectRegionChild(){
        if(IS_AJAX){
            $result = array('error' => 0, 'message' => '', 'content' => '', 'ra_id' => '', 'region_id' => '');
            $type = I('get.type', 0, 'intval');
            $parent = I('get.parent', 0, 'intval');
            $ra_id = I('get.raId', 0, 'intval');

            $regions = get_regions(2, $parent);
            if($type == 2 && !empty($regions)){
                //查询市区下级,县
                foreach($regions as $k=>$v){
                    $regions[$k]['district'] = get_regions(3, $v['region_id']);
                }
            }

            if($type == 0){
                if(empty($regions)){
                    cookie('province', $parent);
                }
            }else if($type == 2){
                cookie('type_province', $parent);
            }

            if(empty($regions)){
                $result['regions'] = 1;

                cookie('province', $_COOKIE['type_province']);
                cookie('city', $_COOKIE['type_city']);
            }
            cookie('ra_id', $ra_id);

            $result['type'] = $type;
            $result['ra_id'] = $ra_id;
            $result['regions'] = $regions;

            die(json_encode($result));
        }
    }

    /**
     * 商品列表筛选城市切换
     */
    public function actionSelectDistrictList(){
        if(IS_AJAX){
            $result = array('error' => 0, 'message' => '', 'content' => '', 'ra_id' => '', 'region_id' => '');
            $region_id = I('get.region_id', 0, 'intval');
            $type = I('get.type', 0, 'intval');

            $where = "region_id = '" .$region_id. "'";
            $date = array('parent_id');
            $parent_id = get_table_date('region', $where, $date, 2);
            if($type == 0){
                //市区筛选

                cookie('province', $parent_id);
                cookie('city', $region_id);

                $where = "parent_id = '" .$region_id. "' order by region_id asc limit 0, 1";
                $date = array('region_id', 'region_name');
                $district_list = get_table_date('region', $where, $date, 1);
                if(count($district_list) > 0){
                    cookie('district', $district_list[0]['region_id']);
                }else{
                    cookie('district', 0);
                }

                //清空
                cookie('type_province', 0);
                cookie('type_city', 0);
                cookie('type_district', 0);
            }else{
                $where = "region_id = '" .$parent_id. "'";
                $date = array('parent_id');
                $province = get_table_date('region', $where, $date, 2);
                cookie('type_province', $province);
                cookie('type_city', $parent_id);
                cookie('type_district', $region_id);
            }

            die(json_encode($result));
        }
    }

    public function actionAddress() {
        $pid = input('parent_id', 1, 'intval');
        $list=$this->model->table('region')->field('region_id,region_name')->where(array('parent_id'=>$pid))->cache(true,12*3600)->select();
        $res = array();
        foreach ($list as $key => $v) {
          $res[$key]['name']=$v['region_name'];
          $res[$key]['id']=$v['region_id'];
        }
        $addresslist = array("addressList" => $res);
        exit(json_encode($addresslist));
    }
}
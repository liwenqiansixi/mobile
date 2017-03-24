<?php

namespace app\http\offline_store\controllers;

use app\http\base\controllers\Frontend;
use ectouch\QRcode;

class Index extends Frontend{

    /**
     * 商品详情页===选择门店地址
     */
    public function actionStoreList(){
        $goods_id = isset($_REQUEST['id']) ? $_REQUEST['id'] : '';//商品ID
        $spec_arr = isset($_REQUEST['spec_arr']) ? $_REQUEST['spec_arr'] : '';//商品属性
        $userId = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

        $storeId = getStoreIdByGoodsId($goods_id);
        $store = getStore($storeId);
        $province_id = empty($store['province']) ? $this->province_id : $store['province'];
        $city_id = empty($store['city']) ? $this->province_id : $store['city'];


        /* 取得国家列表、商店所在国家、商店所在国家的省列表 */
        $this->assign('provinces', get_regions(1, 1));
        $this->assign('goods_id', $goods_id);
        $this->assign('request_url', $_SERVER['HTTP_REFERER']);

        //地区
        $province_list = get_warehouse_province();
        $this->assign('province_list', $province_list); //省、直辖市

        $city_list = get_region_city_county($province_id);
        if($city_list){
            foreach($city_list as $k=>$v){
                $city_list[$k]['district_list'] = get_region_city_county($v['region_id']);
            }
        }
        $this->assign('city_list', $city_list); //省下级市
        $cur_province = get_region_name($province_id);
        $this->assign('cur_province', $cur_province);

        $district_list = get_region_city_county($city_id);
        $this->assign('district_list', $district_list);//市下级县
        //地区end

        //    /*获取全部门店信息*/
        $sql = "SELECT o.id,o.stores_name,s.goods_id,o.stores_address,o.stores_traffic_line,o.ru_id ,p.region_name as province ,s.goods_number ,"
            . "c.region_name as city ,d.region_name as district FROM {pre}offline_store AS o "
            . "LEFT JOIN {pre}store_goods AS s ON o.id = s.store_id "
            . "LEFT JOIN {pre}region AS p ON p.region_id = o.province "
            . "LEFT JOIN {pre}region AS c ON c.region_id = o.city "
            . "LEFT JOIN {pre}region AS d ON d.region_id = o.district "
            . "WHERE o.is_confirm=1 AND s.goods_id ='$goods_id'  GROUP BY o.id";

        $store_list = $this->db->getAll($sql);

        $is_spec = explode(',', $spec_arr);
        if (!empty($store_list)) {
            foreach ($store_list as $k => $v) {
                if (is_spec($is_spec) == true) {
                    $products = get_warehouse_id_attr_number($v['goods_id'], $spec_arr, $v['ru_id'], 0, 0, '', $v['id']);//获取属性库存
                    $v['goods_number'] = $products['product_number'];
                    if ($products['product_number'] == 0) {
                        unset($store_list[$k]);
                    }
                }
            }
        }
        $this->assign('store_list', $store_list);
        $this->assign('user_id', $userId);
        $this->assign('page_title', '门店列表');
        $this->display();
    }
    /**
     * 订单详情页====门店详情页
     */
    public function  actionOfflineStoreDetail(){
        $storeId = !empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0;
        $this->assign('page_title', '订单提取码');

        /*获取订单门店信息start*/
        $sql = "SELECT store_id,pick_code  FROM".$this->ecs->table("store_order")." WHERE id = '$storeId'";
        $stores = $this->db->getRow($sql);
        $this->assign('store', $stores);
        if($stores['store_id'] > 0){
            $sql = "SELECT o.*,p.region_name as province,c.region_name as city,d.region_name as district FROM".$this->ecs->table('offline_store')." AS o "
                . "LEFT JOIN ".$this->ecs->table('region')." AS p ON p.region_id = o.province "
                . "LEFT JOIN ".$this->ecs->table('region')." AS c ON c.region_id = o.city "
                . "LEFT JOIN ".$this->ecs->table('region')." AS d ON d.region_id = o.district WHERE o.id = '".$stores['store_id']."'";
            $offline_store = $this->db->getRow($sql);
            if($offline_store){
                $offline_store['stores_img'] = get_image_path($offline_store['stores_img']);
            }
            $this->assign('offline_store',$offline_store);
        }
        /*获取订单门店信息 end*/

        $this->display();
    }
    public function actionCreateQrcode(){
        $value = I('get.value', '');

        // 纠错级别：L、M、Q、H
        $errorCorrectionLevel = 'M';
        // 点的大小：1到10
        $matrixPointSize = 8;
        @QRcode::png($value, false, $errorCorrectionLevel, $matrixPointSize, 2);
    }

    //地图
    public function actionMap() {
        $address = I('get.address', '');
        if(empty($address)){
            $province = get_region_name(C('shop.shop_province'));
            $city = get_region_name(C('shop.shop_city'));
            $address = C('shop.shop_address');
        }
        $this->assign('city', $city);
        $this->assign('address', $city . $address);
        $this->display();
    }
}




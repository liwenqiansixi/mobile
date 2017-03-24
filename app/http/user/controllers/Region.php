<?php
namespace app\http\user\controllers;

use app\http\base\controllers\Frontend;

class Region extends Frontend
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
      
    }

    /**
     * 根据微信返回获取地址ID
     */
    public function actionIndex()
    {
     if(IS_AJAX){
         $province=I('province');
         $city=I('city');
         $area=I('area');
         $result=array(); 
         //匹配参数
        if(strpos($province,'市') == true){     
          $province=str_replace('市', '', $province);
        }
        if(strpos($province,'省') == true){   
          $province=str_replace('省', '', $province);
        }
        $province_condition = array(
            'region_type' => 1,
            'region_name' => $province
        );
        $province_id= $this->model->table('region')->field('region_id')->where($province_condition)->find();
        $result['province_id']=$province_id['region_id'];//省id
        //取得市的ID
        if(strpos($city,'市') == true){   
          $city=str_replace('市', '', $city);
        }
        $city_condition = array(
            'region_type' => 2,
            'region_name' => $city
        );
        $city_id= $this->model->table('region')->field('region_id')->where($city_condition)->find();
        $result['city_id']=$city_id['region_id'];//市id
        //取得地区ID
        $area_condition = array(
            'region_type' => 3,
            'region_name' => $area
        );
        $area_id= $this->model->table('region')->field('region_id')->where($area_condition)->find();
        $result['area_id']=$area_id['region_id'];//市id
        die(json_encode($result));
     }
     
    }

}

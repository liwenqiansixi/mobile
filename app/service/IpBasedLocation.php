<?php
namespace app\service;

use ectouch\Http;

/**
 * 根据IP地址获取城市名称
 * Class IpBasedLocationService
 * @package app\service
 */
class IpBasedLocation
{
    private $config;

    public function __construct(&$data = array())
    {
        $this->config = config('shop');
        $ip_type = $this->config['ip_type'] + 1;
        switch ($ip_type) {
            case '1':
                $area_name = $this->sian($data);
                break;
            case '2':
                $area_name = $this->taobao($data);
                break;
            case '3':
                $area_name = $this->tencent($data);
                break;
        }

        // 自治区（州）、特别行政区兼容处理
        $area_name = str_replace(array('省', '市', "'"), '', $area_name);
        if (strstr($area_name, '香港')) {
            $area_name = "香港";
        } elseif (strstr($area_name, '澳门')) {
            $area_name = "澳门";
        } elseif (strstr($area_name, '内蒙古')) {
            $area_name = "内蒙古";
        } elseif (strstr($area_name, '宁夏')) {
            $area_name = "宁夏";
        } elseif (strstr($area_name, '新疆')) {
            $area_name = "新疆";
        } elseif (strstr($area_name, '西藏')) {
            $area_name = "西藏";
        } elseif (strstr($area_name, '广西')) {
            $area_name = "广西";
        }

        $data['city'] = $area_name;
    }

    /**
     * 淘宝API接口
     */
    private function taobao($data)
    {
        $url = "http://ip.taobao.com/service/getIpInfo.php?ip=" . $data['ip'];
        $data = Http::doGet($url); //调用淘宝API接口获取信息
        $str = json_decode($data, true);

        if (!is_array($str) || $data['ip'] == '127.0.0.1') {
            if (!empty($this->config['shop_city'])) {
                $ip_city = get_shop_address($this->config['shop_city']);
                $str = array('data' => array('city' => $ip_city, 'county' => ''));
            } elseif (!empty($this->config['shop_province'])) {
                $ip_province = get_shop_address($this->config['shop_province']);
                $str = array('data' => array('city' => '', 'county' => '', 'region' => $ip_province));
            } else {
                $str = array('data' => array('region' => "上海", 'city' => '', 'county' => ''));
            }
        }

        if (!empty($str['data']['county'])) { //市级
            $region = $str['data']['county'];
        } else {
            if (!empty($str['data']['city'])) { //市级
                $region = $str['data']['city'];
            } else { //省级或特别行政区
                $region = $str['data']['region'];
            }
        }
        return $region;
    }

    /**
     * 新浪API接口
     */
    private function sian($data)
    {
        $url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=" . $data['ip'];
        $data = Http::doGet($url); //调用新浪API接口获取信息
        $str = json_decode($data, true);

        if (!is_array($str) || $data['ip'] == '127.0.0.1') {
            if (!empty($this->config['shop_city'])) {
                $ip_city = get_shop_address($this->config['shop_city']);
                $str = array('city' => $ip_city, 'province' => '');
            } elseif (!empty($this->config['shop_province'])) {
                $ip_province = get_shop_address($this->config['shop_province']);
                $str = array('city' => '', 'province' => $ip_province);
            } else {
                $str = array('city' => "上海");
            }
        }

        if (!empty($str['city'])) { //市级
            $region = $str['city'];
        } else { //省级或特别行政区
            $region = $str['province'];
        }

        return $region;
    }

    /**
     * 腾讯API接口
     */
    private function tencent($data)
    {
        $url = "http://apis.map.qq.com/ws/location/v1/ip?ip=" . $data['ip'] . "&key=" . $this->config['tengxun_key'];
        $data = Http::doGet($url); //调用新浪API接口获取信息
        $str = json_decode($data, true);

        if (!is_array($str) || $data['ip'] == '127.0.0.1') {
            if (empty($str['result']['ad_info']['city']) && empty($str['result']['ad_info']['province'])) {
                if (!empty($this->config['shop_city'])) {
                    $ip_city = get_shop_address($this->config['shop_city']);
                    $str['result']['ad_info'] = array('city' => $ip_city, 'province' => '');
                } elseif (!empty($this->config['shop_province'])) {
                    $ip_province = get_shop_address($this->config['shop_province']);
                    $str['result']['ad_info'] = array('city' => '', 'province' => $ip_province);
                } else {
                    $str['result']['ad_info'] = array('city' => "上海");
                }
            }
        }

        if (!empty($str['result']['ad_info']['city'])) { //市级
            $region = $str['result']['ad_info']['city'];
        } else { //省级或特别行政区
            $region = $str['result']['ad_info']['province'];
        }
        return $region;
    }
}
<?php
namespace app\http\topic\controllers;

use app\http\base\controllers\Frontend;

class Index extends Frontend
{
    private $region_id = 0;
    private $area_info = array();

    public function __construct()
    {
        parent::__construct();
        $this->init_params();
        $this->area_id = $this->area_info['region_id'];
        L(require(LANG_PATH  . C('shop.lang') . '/other.php'));
    }

    public function actionIndex()
    {
        $size = 10;
        $page = I('page', 1);
        if (IS_AJAX) {
            $sql = "SELECT COUNT(*) as num FROM " . $GLOBALS['ecs']->table('topic') .
                " WHERE " . gmtime() . " >= start_time and " . gmtime() . "<= end_time ";
            $num = $GLOBALS['db']->getOne($sql);
        
            $num ? $num : 0;

            $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('topic') .
                " WHERE " . gmtime() . " >= start_time and " . gmtime() . "<= end_time " . " ORDER BY start_time DESC ".
                " LIMIT " . ($page - 1) * $size . ",  $size";
            $topic = $GLOBALS['db']->getAll($sql);

            foreach ($topic as $k => $v) {
                $topic[$k]['topic_img'] = get_image_path($v['topic_img']);
                $topic[$k]['start_time'] = local_date('Y-m-d H:i',$v['start_time']);
                $topic[$k]['end_time'] = local_date('Y-m-d H:i',$v['end_time']);
                $topic[$k]['url'] = build_uri('topic', array('r' => 'index/detail', 'topic_id' => $v['topic_id']));
            }
            exit(json_encode(array('list' => $topic, 'totalPage' => ceil($num / $size))));
        }
        $this->assign('page_title', L('special'));
        $this->display();
    }

    public function actionDetail()
    {
        $topic_id = I("request.topic_id", 0, "intval");
        $sql = "SELECT topic_id FROM " . $GLOBALS['ecs']->table('topic') .
            "WHERE topic_id = '$topic_id' and  " . gmtime() . " >= start_time and " . gmtime() . "<= end_time";

        $topic = $GLOBALS['db']->getRow($sql);
        if (empty($topic)) {
            $this->redirect('/');
        }
        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('topic') . " WHERE topic_id = '$topic_id'";

        $topic = $GLOBALS['db']->getRow($sql);

        $topic['topic_img'] = get_image_path($topic['topic_img']);

        $topic['data'] = addcslashes($topic['data'], "'");
        $tmp = @unserialize($topic["data"]);
        $arr = (array)$tmp;

        $goods_id = array();
        foreach ($arr as $key => $value) {
            foreach ($value as $k => $val) {
                $opt = explode('|', $val);
                $arr[$key][$k] = $opt[1];
                $goods_id[] = $opt[1];
            }
        }

        //关联仓库地区价格 by wu
        $leftJoin = '';
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$this->region_id' ";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$this->area_id' ";

        $sql = 'SELECT g.goods_id, g.goods_name, g.goods_name_style, g.market_price, g.is_new, g.is_best, g.is_hot, g.shop_price AS org_price, ' .
            "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
            "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, " .
            'g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb , g.goods_img ' .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
            $leftJoin .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            "WHERE " . db_create_in($goods_id, 'g.goods_id');

        $res = $GLOBALS['db']->query($sql);

        $goods_list = array();
        foreach ($res as $key=>$row) {
            if ($row['promote_price'] > 0) {
                $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
                $row['promote_price'] = $promote_price > 0 ? price_format($promote_price) : '';
            } else {
                $row['promote_price'] = '';
            }

            if ($row['shop_price'] > 0) {
                $row['shop_price'] = price_format($row['shop_price']);
            } else {
                $row['shop_price'] = price_format(0);
            }

            $row['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
            $row['goods_style_name'] = add_style($row['goods_name'], $row['goods_name_style']);
            $row['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            $row['goods_thumb'] = get_image_path($row['goods_thumb']);
            $row['short_style_name'] = add_style($row['short_name'], $row['goods_name_style']);
            $goods_list[] = $row;
        }

        $sort_goods_arr = array();
        foreach ($arr as $key => $value) {
            foreach ($goods_list as $goods) {
                if (in_array($goods['goods_id'], $value)) {
                    $key = $key == 'default' ? L('all_goods') : $key;
                    $sort_goods_arr[$key][] = $goods;
                }
            }
        }

        $this->assign('show_marketprice', $GLOBALS['_CFG']['show_marketprice']);
        $this->assign('sort_goods_arr', $sort_goods_arr);          // 商品列表
        $this->assign('topic', $topic);                   // 专题信息
        $position = assign_ur_here($topic['topic_id'], $topic['title']);
        $this->assign('page_title',          $position['title']);
        $this->assign('keywords', $topic['keywords']);       // 专题信息
        $this->assign('description', $topic['description']);    // 专题信息
        $this->assign('title_pic', $topic['title_pic']);      // 分类标题图片地址
        $this->display();
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

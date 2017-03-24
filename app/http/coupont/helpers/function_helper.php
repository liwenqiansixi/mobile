<?php

/**
 * Created by PhpStorm.
 * User: wangbin
 * Date: 2016/8/24
 * Time: 13:41
 */
/* * 格式化优惠券数据(注册送、购物送除外)
 * @param $cou_data
 * @return mixed
 */
function fromat_coupons($cou_data) {

    //当前时间;
    $time = gmtime();

    //优化数据;
    foreach ($cou_data as $k => $v) {

        //优惠券剩余量
        if (!isset($v['cou_surplus'])) {
            $cou_data[$k]['cou_surplus'] = 100;
        }

        //可使用优惠券的商品; bylu
        if (!empty($v['cou_goods'])) {
            $cou_data[$k]['cou_goods_name'] = $GLOBALS['db']->getAll("SELECT goods_id,goods_name,goods_thumb FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id IN(" . $v['cou_goods'] . ")");
        }

        //可领券的会员等级;
        if (!empty($v['cou_ok_user'])) {
            $cou_data[$k]['cou_ok_user_name'] = $GLOBALS['db']->getOne("SELECT group_concat(rank_name)  FROM " . $GLOBALS['ecs']->table('user_rank') . " WHERE rank_id IN(" . $v['cou_ok_user'] . ")");
        }

        //可使用的店铺;
        if ($v['ru_id']) {
            $store_info = get_shop_name($v['ru_id']);
            $cou_data[$k]['store_name'] = '限' . $store_info['shop_name'] . '可用';
        } else
            $cou_data[$k]['store_name'] = '全平台可用';

        //时间戳转时间;
        $cou_data[$k]['cou_start_time_format'] = local_date('Y/m/d', $v['cou_start_time']);
        $cou_data[$k]['cou_end_time_format'] = local_date('Y/m/d', $v['cou_end_time']);

        //判断是否已过期;
        if ($v['cou_end_time'] < $time) {
            $cou_data[$k]['is_overdue'] = 1;
        } else {
            $cou_data[$k]['is_overdue'] = 0;
        }

        //优惠券种类;
        $cou_data[$k]['cou_type_name'] = $v['cou_type'] == 3 ? '全场券' : ($v['cou_type'] == 4 ? '会员券' : '未知');

        //是否已经领取过了
        if ($_SESSION['user_id']) {
            $r = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('coupons_user') . " WHERE cou_id='" . $v['cou_id'] . "' AND user_id ='" . $_SESSION['user_id'] . "'");
            $cou_data[$k]['cou_is_receive'] = $r ? 1 : 0;
        }
    }

    return $cou_data;
}

/**
 * 优惠券列表（分页）
 * @param int $num
 * @param int $page
 * @param int $status
 * @return type array
 */
function get_coupons_list($num = 2, $page = 1, $status = 0) {
    $time = gmtime();                     //当前时间
    if ($status == 0) {                    //秒杀
        $condition = 3;
    } elseif ($status == 2) {             //好券集市
        $condition = 4;
    }
    $res = $GLOBALS['db']->getAll("select * from " . $GLOBALS['ecs']->table('coupons') . "where  cou_type =  '$condition' and  $time<cou_end_time and $time>cou_start_time and review_status = 3");
    //总条数
    $total = is_array($res) ? count($res) : 0;
    $start = ($page - 1) * $num;
    $sql = "select * from " . $GLOBALS['ecs']->table('coupons') . "where  cou_type = '$condition'  and  $time< cou_end_time and review_status = 3 and  $time > cou_start_time" . " limit $start,$num";
    $tab = $GLOBALS['db']->getAll($sql);

    if (status == 0) {
        foreach ($tab as &$v) {
            $v['begintime'] = date("Y-m-d H:i:s", $v['cou_start_time']);
            $v['endtime'] = date("Y-m-d H:i:s", $v['cou_end_time']);
            $v['img'] = "images/coupons_default.png";
            $cou_goods = explode(",", $v['cou_goods']);
            foreach ($cou_goods as $k => $i) {
                $sql2 = "select * from " . $GLOBALS['ecs']->table('goods') . "where goods_id = '$i'";
                $tab2 = $GLOBALS['db']->getAll($sql2);
                $cou_goods[$k] = $tab2;
            }
            $v['goodsInfro'] = $cou_goods;
        }
    } elseif (status == 2) {
        foreach ($tab as &$vs) {
            $vs['begintime'] = date("Y-m-d H:i:s", $v['cou_start_time']);
            $vs['endtime'] = date("Y-m-d H:i:s", $v['cou_end_time']);
            $vs['img'] = "images/coupons_default.png";
        }
    }

    $tab_list = array('tab' => $tab, 'totalpage' => ceil($total / $num));
    return $tab_list;
}

<?php
/**
 * 取得收货人地址列表
 * @param   int     $user_id    用户编号
 * @param   int     $id         收货地址id
 * @return  array
 */
function get_consignee_list_p($user_id, $id = 0, $num = 10, $start = 0) {

    if ($id) {
        $where['address_id'] = $id;
        $GLOBALS['db']->table = 'user_address';
        return $GLOBALS['db']->find($where);
    } else {
        $sql = 'select * from {pre}user_address where user_id = ' . $user_id . ' order by address_id limit ' . $start . ', ' . $num;
        return $GLOBALS['db']->query($sql);
    }
}

/**
 * 获得用户的可用积分
 *
 * @access private
 * @return integral
 */
function flow_available_points($cart_value) {
    //ecmoban模板堂 --zhuo start
    if(!empty($_SESSION['user_id'])){
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end
    $where="";
    if(!empty($cart_value)){
        $where=" AND c.rec_id ".db_create_in($cart_value);
    }

    $sql = "SELECT SUM(g.integral * c.goods_number) ".
        "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, " . $GLOBALS['ecs']->table('goods') . " AS g " .
        "WHERE " .$c_sess. " AND c.goods_id = g.goods_id AND c.is_gift = 0 AND g.integral > 0 $where" .
        "AND c.rec_type = '" . CART_GENERAL_GOODS . "'";

    $val = intval($GLOBALS['db']->getOne($sql));

    return integral_of_value($val);
}

/**
 * 获取购物车商品rec_id
 * @param int $flow_type
 * @return string
 */
function get_cart_value($flow_type = 0){
    $where = '';
    if(!empty($_SESSION['user_id'])){
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }

    $sql = "SELECT c.rec_id FROM " . $GLOBALS['ecs']->table('cart') .
        " AS c LEFT JOIN ".$GLOBALS['ecs']->table('goods').
        " AS g ON c.goods_id = g.goods_id WHERE $where " . $c_sess .
        "AND rec_type = '$flow_type' order by c.rec_id asc";

    $goods_list = $GLOBALS['db']->getAll($sql);

    $rec_id = '';
    if($goods_list){
        foreach($goods_list as $key=>$row){
            $rec_id .= $row['rec_id'] . ',';
        }

        $rec_id = substr($rec_id, 0, -1);
    }

    return $rec_id;
}

// 重组商家购物车数组  按照优惠活动对购物车商品进行分类 -qin
function cart_by_favourable($merchant_goods)
{
    foreach ($merchant_goods as $key => $row) // 第一层 遍历商家
    {
        $goods_num = 0;
        $package_goods_num = 0;
        $user_cart_goods = $row['goods_list'];
        // 商家发布的优惠活动
        $favourable_list = favourable_list($_SESSION['user_rank'], $row['ru_id']);
        // 对优惠活动进行归类
        $sort_favourable = sort_favourable($favourable_list);

        foreach ($user_cart_goods as $key1 => $row1) // 第二层 遍历购物车中商家的商品
        {
            $row1['original_price']=$row1['goods_price']*$row1['goods_number'];

            if($row1['extension_code'] == 'package_buy'){
                $package_goods_num++;
            }else{
                $goods_num++;
            }
            // 活动-全部商品
            if (isset($sort_favourable['by_all']) && $row1['extension_code'] != 'package_buy')
            {
                foreach ($sort_favourable['by_all'] as $key2 => $row2)
                {
                    if ($row1['is_gift'] == 0) // 活动商品
                    {
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['act_id'] = $row2['act_id'];
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['act_name'] = $row2['act_name'];
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type'] = $row2['act_type'];
                        // 活动类型
                        switch ($row2['act_type'])
                        {
                            case 0:
                                $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '满赠';
                                $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = intval($row2['act_type_ext']);// 可领取总件数
                                break;
                            case 1:
                                $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '满减';
                                $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = number_format($row2['act_type_ext'], 2);// 满减金额
                                break;
                            case 2:
                                $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '折扣';
                                $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = floatval($row2['act_type_ext']/10);// 折扣百分比
                                break;

                            default:
                                break;
                        }
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['min_amount'] = $row2['min_amount'];
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext'] = intval($row2['act_type_ext']);// 可领取总件数
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['cart_fav_amount'] = cart_favourable_amount($row2);
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['available'] = favourable_available($row2);// 购物车满足活动最低金额
                        // 购物车中已选活动赠品数量
                        $cart_favourable = cart_favourable();
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['cart_favourable_gift_num'] = empty($cart_favourable[$row2['act_id']])? 0: intval($cart_favourable[$row2['act_id']]);
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['favourable_used'] = favourable_used($row2, $cart_favourable);
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['left_gift_num'] = intval($row2['act_type_ext']) - (empty($cart_favourable[$row2['act_id']])? 0: intval($cart_favourable[$row2['act_id']]));

                        /* 检查购物车中是否已有该优惠 */

                        // 活动赠品
                        if ($row2['gift'])
                        {
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_gift_list'] = $row2['gift'];
                        }

                        // new_list->活动id->act_goods_list
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['act_goods_list'][$row1['rec_id']] = $row1;
                    }
                    else // 赠品
                    {
                        $merchant_goods[$key]['new_list'][$row2['act_id']]['act_cart_gift'][$row1['rec_id']] = $row1;
                    }
                    break; // 如果有多个优惠活动包含全部商品，只取一个
                }
                continue;// 如果活动包含全部商品，跳出循环体
            }

            // 活动-分类

            if (isset($sort_favourable['by_category']) && $row1['extension_code'] != 'package_buy')
            {
                // 优惠活动关联的 分类集合
                $get_act_range_ext = get_act_range_ext($_SESSION['user_rank'], $row['ru_id'],1); // 1表示优惠范围 按分类

                $id_list = array();
                foreach ($get_act_range_ext as $id)
                {
                    $id_list = array_merge($id_list, array_keys(cat_list(intval($id), 0)));
                }
                // 当前商品所属分类
                $cat_id = $GLOBALS['db']->getOne("SELECT cat_id FROM ".$GLOBALS['ecs']->table('goods')." WHERE goods_id = '$row1[goods_id]' ");
                // 优惠活动ID
                $favourable_id_list = get_favourable_id($sort_favourable['by_category']);

                // 判断商品或赠品 是否属于本优惠活动
                if((in_array(trim($cat_id), $id_list) && $row1['is_gift'] == 0) || in_array($row1['is_gift'], $favourable_id_list) )
                {
                    foreach ($sort_favourable['by_category'] as $key2 => $row2)
                    {
                        // 该活动关联的所有分类
                        $fav_act_range_ext = array();

                        // 此 优惠活动所有分类
                        foreach (explode(',', $row2['act_range_ext']) as $id)
                        {
                            $fav_act_range_ext = array_merge($fav_act_range_ext, array_keys(cat_list(intval($id), 0)));
                        }

                        if ($row1['is_gift'] == 0 && in_array($cat_id, $fav_act_range_ext)) // 活动商品
                        {
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_id'] = $row2['act_id'];
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_name'] = $row2['act_name'];
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type'] = $row2['act_type'];
                            // 活动类型
                            switch ($row2['act_type'])
                            {
                                case 0:
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '满赠';
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = intval($row2['act_type_ext']);// 可领取总件数
                                    break;
                                case 1:
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '满减';
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = number_format($row2['act_type_ext'], 2);// 满减金额
                                    break;
                                case 2:
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '折扣';
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = floatval($row2['act_type_ext']/10);// 折扣百分比
                                    break;

                                default:
                                    break;
                            }
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['min_amount'] = $row2['min_amount'];
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext'] = intval($row2['act_type_ext']);// 可领取总件数
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['cart_fav_amount'] = cart_favourable_amount($row2);
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['available'] = favourable_available($row2);// 购物车满足活动最低金额
                            // 购物车中已选活动赠品数量
                            $cart_favourable = cart_favourable();
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['cart_favourable_gift_num'] = empty($cart_favourable[$row2['act_id']])? 0: intval($cart_favourable[$row2['act_id']]);
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['favourable_used'] = favourable_used($row2, $cart_favourable);
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['left_gift_num'] = intval($row2['act_type_ext']) - (empty($cart_favourable[$row2['act_id']])? 0: intval($cart_favourable[$row2['act_id']]));

                            /* 检查购物车中是否已有该优惠 */

                            // 活动赠品
                            if ($row2['gift'])
                            {
                                $merchant_goods[$key]['new_list'][$row2['act_id']]['act_gift_list'] = $row2['gift'];
                            }

                            // new_list->活动id->act_goods_list
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_goods_list'][$row1['rec_id']] = $row1;
                        }
                        if ($row1['is_gift'] == $row2['act_id']) // 赠品
                        {
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_cart_gift'][$row1['rec_id']] = $row1;
                        }
                    }
                    continue;
                }
            }

            // 活动-品牌
            if (isset($sort_favourable['by_brand']) && $row1['extension_code'] != 'package_buy')
            {
                // 优惠活动 品牌集合
                $get_act_range_ext = get_act_range_ext($_SESSION['user_rank'], $row['ru_id'],2); // 2表示优惠范围 按品牌
//                print_arr($get_act_range_ext);
                $brand_id = $GLOBALS['db']->getOne("SELECT brand_id FROM ".$GLOBALS['ecs']->table('goods')." WHERE goods_id = '$row1[goods_id]' ");

                // 优惠活动ID集合
                $favourable_id_list = get_favourable_id($sort_favourable['by_brand']);

                // 是品牌活动的商品或者赠品
                if((in_array(trim($brand_id), $get_act_range_ext) && $row1['is_gift'] == 0) || in_array($row1['is_gift'], $favourable_id_list))
                {
                    foreach ($sort_favourable['by_brand'] as $key2 => $row2)
                    {
                        $act_range_ext_str = ','.$row2['act_range_ext'].',';
                        $brand_id_str = ','.$brand_id.',';
                        if ($row1['is_gift'] == 0 && strstr($act_range_ext_str, trim($brand_id_str))) // 活动商品
                        {
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_id'] = $row2['act_id'];
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_name'] = $row2['act_name'];
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type'] = $row2['act_type'];
                            // 活动类型
                            switch ($row2['act_type'])
                            {
                                case 0:
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '满赠';
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = intval($row2['act_type_ext']);// 可领取总件数
                                    break;
                                case 1:
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '满减';
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = number_format($row2['act_type_ext'], 2);// 满减金额
                                    break;
                                case 2:
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '折扣';
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = floatval($row2['act_type_ext']/10);// 折扣百分比
                                    break;

                                default:
                                    break;
                            }
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['min_amount'] = $row2['min_amount'];
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext'] = intval($row2['act_type_ext']);// 可领取总件数
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['cart_fav_amount'] = cart_favourable_amount($row2);
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['available'] = favourable_available($row2);// 购物车满足活动最低金额
                            // 购物车中已选活动赠品数量
                            $cart_favourable = cart_favourable();
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['cart_favourable_gift_num'] = empty($cart_favourable[$row2['act_id']])? 0: intval($cart_favourable[$row2['act_id']]);
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['favourable_used'] = favourable_used($row2, $cart_favourable);
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['left_gift_num'] = intval($row2['act_type_ext']) - (empty($cart_favourable[$row2['act_id']])? 0: intval($cart_favourable[$row2['act_id']]));

                            /* 检查购物车中是否已有该优惠 */

                            // 活动赠品
                            if ($row2['gift'])
                            {
                                $merchant_goods[$key]['new_list'][$row2['act_id']]['act_gift_list'] = $row2['gift'];
                            }

                            // new_list->活动id->act_goods_list
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_goods_list'][$row1['rec_id']] = $row1;
                        }
                        if ($row1['is_gift'] == $row2['act_id']) // 赠品
                        {
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_cart_gift'][$row1['rec_id']] = $row1;
                        }
                    }
                    continue;
                }
            }

            // 活动-部分商品
            if (isset($sort_favourable['by_goods']) && $row1['extension_code'] != 'package_buy')
            {
                $get_act_range_ext = get_act_range_ext($_SESSION['user_rank'], $row['ru_id'],3); // 3表示优惠范围 按商品

                // 优惠活动ID集合
                $favourable_id_list = get_favourable_id($sort_favourable['by_goods']);

                // 判断购物商品是否参加了活动  或者  该商品是赠品
                if(in_array($row1['goods_id'], $get_act_range_ext) || in_array($row1['is_gift'], $favourable_id_list))
                {
                    foreach ($sort_favourable['by_goods'] as $key2 => $row2) // 第三层 遍历活动
                    {
                        $act_range_ext_str = ','.$row2['act_range_ext'].','; // 优惠活动中的优惠商品
                        $goods_id_str = ','.$row1['goods_id'].',';
                        // 如果是活动商品
                        if (strstr($act_range_ext_str, $goods_id_str) && ($row1['is_gift'] == 0))
                        {
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_id'] = $row2['act_id'];
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_name'] = $row2['act_name'];
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type'] = $row2['act_type'];
                            // 活动类型
                            switch ($row2['act_type'])
                            {
                                case 0:
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '满赠';
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = intval($row2['act_type_ext']);// 可领取总件数
                                    break;
                                case 1:
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '满减';
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = number_format($row2['act_type_ext'], 2);// 满减金额
                                    break;
                                case 2:
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_txt'] = '折扣';
                                    $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext_format'] = floatval($row2['act_type_ext']/10);// 折扣百分比
                                    break;

                                default:
                                    break;
                            }
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['min_amount'] = $row2['min_amount'];
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_type_ext'] = intval($row2['act_type_ext']);// 可领取总件数
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['cart_fav_amount'] = cart_favourable_amount($row2);
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['available'] = favourable_available($row2);// 购物车满足活动最低金额
                            // 购物车中已选活动赠品数量
                            $cart_favourable = cart_favourable();
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['cart_favourable_gift_num'] = empty($cart_favourable[$row2['act_id']])? 0: intval($cart_favourable[$row2['act_id']]);
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['favourable_used'] = favourable_used($row2, $cart_favourable);
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['left_gift_num'] = intval($row2['act_type_ext']) - (empty($cart_favourable[$row2['act_id']])? 0: intval($cart_favourable[$row2['act_id']]));

                            /* 检查购物车中是否已有该优惠 */

                            // 活动赠品
                            if ($row2['gift'])
                            {
                                $merchant_goods[$key]['new_list'][$row2['act_id']]['act_gift_list'] = $row2['gift'];
                            }

                            // new_list->活动id->act_goods_list
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_goods_list'][$row1['rec_id']] = $row1;
                            break;
                        }
                        // 如果是赠品
                        if ($row1['is_gift'] == $row2['act_id'])
                        {
                            $merchant_goods[$key]['new_list'][$row2['act_id']]['act_cart_gift'][$row1['rec_id']] = $row1;
                        }
                    }
                }
                else
                {
                    // new_list->活动id->act_goods_list | 活动id的数组位置为0，表示次数组下面为没有参加活动的商品
                    $merchant_goods[$key]['new_list'][0]['act_goods_list'][$row1['rec_id']] = $row1;
                }
            }
            else
            {
                // new_list->活动id->act_goods_list | 活动id的数组位置为0，表示次数组下面为没有参加活动的商品
                $merchant_goods[$key]['new_list'][0]['act_goods_list'][$row1['rec_id']] = $row1;
            }
        }
        //商品数量 by wanglu
        $merchant_goods[$key]['goods_count'] = $goods_num;
        $merchant_goods[$key]['package_goods_num'] = $package_goods_num;
    }
    //print_arr($merchant_goods);
    return $merchant_goods;
}

/**
 * 取得某用户等级当前时间可以享受的优惠活动
 * @param   int     $user_rank      用户等级id，0表示非会员
 * @param int $user_id 商家id
 * @param int $fav_id 优惠活动ID
 * @return  array
 */
function favourable_list($user_rank, $user_id = -1, $fav_id = 0)
{
    $where = '';
    if ($user_id >= 0)
    {
        $where .= " AND user_id = '$user_id'";
    }
    if ($fav_id > 0)
    {
        $where .= " AND act_id = '$fav_id' ";
    }
    /* 购物车中已有的优惠活动及数量 */
    $used_list = cart_favourable();

    /* 当前用户可享受的优惠活动 */
    $favourable_list = array();
    $user_rank = ',' . $user_rank . ',';
    $now = gmtime();
    $sql = "SELECT * " .
        "FROM " . $GLOBALS['ecs']->table('favourable_activity') .
        " WHERE CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'" .
        " AND start_time <= '$now' AND end_time >= '$now' AND review_status = 3 " . $where .
        " ORDER BY sort_order";
    $res = $GLOBALS['db']->query($sql);
    foreach($res as $favourable)
    {
        $favourable['start_time'] = local_date($GLOBALS['_CFG']['time_format'], $favourable['start_time']);
        $favourable['end_time']   = local_date($GLOBALS['_CFG']['time_format'], $favourable['end_time']);
        $favourable['formated_min_amount'] = price_format($favourable['min_amount'], false);
        $favourable['formated_max_amount'] = price_format($favourable['max_amount'], false);
        $favourable['gift']       = unserialize($favourable['gift']);

        foreach ((array)$favourable['gift'] as $key => $value)
        {
            $favourable['gift'][$key]['formated_price'] = price_format($value['price'], false);
            // 赠品缩略图
            $favourable['gift'][$key]['thumb_img'] = $GLOBALS['db']->getOne("SELECT goods_thumb FROM ".$GLOBALS['ecs']->table('goods')." WHERE goods_id = '$value[id]'");
            $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('goods') . " WHERE is_on_sale = 1 AND goods_id = ".$value['id'];
            $is_sale = $GLOBALS['db']->getOne($sql);
            if(!$is_sale)
            {
                unset($favourable['gift'][$key]);
            }
        }

        $favourable['act_range_desc'] = act_range_desc($favourable);
        $favourable['act_type_desc'] = sprintf($GLOBALS['_LANG']['fat_ext'][$favourable['act_type']], $favourable['act_type_ext']);

        /* 是否能享受 */
        $favourable['available'] = favourable_available($favourable);
        if ($favourable['available'])
        {
            /* 是否尚未享受 */
            $favourable['available'] = !favourable_used($favourable, $used_list);
        }

        $favourable_list[] = $favourable;
    }

    return $favourable_list;
}

/**
 * 取得购物车中已有的优惠活动及数量
 * @return  array
 */
function cart_favourable()
{
    //ecmoban模板堂 --zhuo start
    if(!empty($_SESSION['user_id'])){
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    $list = array();
    $sql = "SELECT is_gift, COUNT(*) AS num " .
        "FROM " . $GLOBALS['ecs']->table('cart') .
        " WHERE ". $sess_id .
        " AND rec_type = '" . CART_GENERAL_GOODS . "'" .
        " AND is_gift > 0" .
        " GROUP BY is_gift";
    $res = $GLOBALS['db']->query($sql);
    foreach($res as $row)
    {
        $list[$row['is_gift']] = $row['num'];
    }

    return $list;
}

// 对优惠商品进行归类
function sort_favourable($favourable_list)
{
    $arr = array();
    foreach ($favourable_list as $key => $value)
    {
        switch ($value['act_range'])
        {
            case FAR_ALL:
                $arr['by_all'][$key] = $value;
                break;
            case FAR_CATEGORY:
                $arr['by_category'][$key] = $value;
                break;
            case FAR_BRAND:
                $arr['by_brand'][$key] = $value;
                break;
            case FAR_GOODS:
                $arr['by_goods'][$key] = $value;
                break;
            default:
                break;
        }
    }
    return $arr;
}

/**
 * 取得优惠范围描述
 * @param   array   $favourable     优惠活动
 * @return  string
 */
function act_range_desc($favourable)
{
    if ($favourable['act_range'] == FAR_BRAND)
    {
        $sql = "SELECT brand_name FROM " . $GLOBALS['ecs']->table('brand') .
            " WHERE brand_id " . db_create_in($favourable['act_range_ext']);
        return join(',', $GLOBALS['db']->getCol($sql));
    }
    elseif ($favourable['act_range'] == FAR_CATEGORY)
    {
        $sql = "SELECT cat_name FROM " . $GLOBALS['ecs']->table('category') .
            " WHERE cat_id " . db_create_in($favourable['act_range_ext']);
        return join(',', $GLOBALS['db']->getCol($sql));
    }
    elseif ($favourable['act_range'] == FAR_GOODS)
    {
        $sql = "SELECT goods_name FROM " . $GLOBALS['ecs']->table('goods') .
            " WHERE goods_id " . db_create_in($favourable['act_range_ext']);
        return join(',', $GLOBALS['db']->getCol($sql));
    }
    else
    {
        return '';
    }
}

/**
 * 根据购物车判断是否可以享受某优惠活动
 * @param   array   $favourable     优惠活动信息
 * @return  bool
 */
function favourable_available($favourable)
{
    /* 会员等级是否符合 */
    $user_rank = $_SESSION['user_rank'];
    if (strpos(',' . $favourable['user_rank'] . ',', ',' . $user_rank . ',') === false)
    {
        return false;
    }

    /* 优惠范围内的商品总额 */
    $amount = cart_favourable_amount($favourable);

    /* 金额上限为0表示没有上限 */
    return $amount >= $favourable['min_amount'] &&
    ($amount <= $favourable['max_amount'] || $favourable['max_amount'] == 0);
}

/**
 * 取得购物车中某优惠活动范围内的总金额
 * @param   array   $favourable     优惠活动
 * @return  float
 */
function cart_favourable_amount($favourable)
{
    //ecmoban模板堂 --zhuo start
    if(!empty($_SESSION['user_id'])){
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }

    $fav_where = "";
    if($favourable['userFav_type'] == 0){
        $fav_where = " AND g.user_id = '" .$favourable['user_id']. "' ";
    }
    //ecmoban模板堂 --zhuo end

    /* 查询优惠范围内商品总额的sql */
    $sql = "SELECT SUM(c.goods_price * c.goods_number) " .
        "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, " . $GLOBALS['ecs']->table('goods') . " AS g " .
        "WHERE c.goods_id = g.goods_id " .
        "AND " .$c_sess. " AND c.rec_type = '" . CART_GENERAL_GOODS . "' " .
        "AND c.is_gift = 0 " .
        "AND c.goods_id > 0 " . $fav_where; //ecmoban模板堂 --zhuo

    /* 根据优惠范围修正sql */
    if ($favourable['act_range'] == FAR_ALL)
    {
        // sql do not change
    }
    elseif ($favourable['act_range'] == FAR_CATEGORY)
    {
        /* 取得优惠范围分类的所有下级分类 */
        $id_list = array();
        $cat_list = explode(',', $favourable['act_range_ext']);
        foreach ($cat_list as $id)
        {
            $id_list = array_merge($id_list, array_keys(cat_list(intval($id), 0)));
        }

        $sql .= "AND g.cat_id " . db_create_in($id_list);
    }
    elseif ($favourable['act_range'] == FAR_BRAND)
    {
        $id_list = explode(',', $favourable['act_range_ext']);

        $sql .= "AND g.brand_id " . db_create_in($id_list);
    }
    else
    {
        $id_list = explode(',', $favourable['act_range_ext']);

        $sql .= "AND g.goods_id " . db_create_in($id_list);
    }

    /* 优惠范围内的商品总额 */
    return $GLOBALS['db']->getOne($sql);
}

// 同一商家所有优惠活动包含的所有优惠范围 -qin
function get_act_range_ext($user_rank, $user_id = -1,$act_range)
{
    if ($user_id >= 0)
    {
        $u_id = " AND user_id = '$user_id'";
    }
    if ($act_range > 0)
    {
        $a_range = " AND act_range = '$act_range' ";
    }
    /* 当前用户可享受的优惠活动 */
    $res = array();
    $user_rank = ',' . $user_rank . ',';
    $now = gmtime();
    $sql = "SELECT act_range_ext " .
        "FROM " . $GLOBALS['ecs']->table('favourable_activity') .
        " WHERE CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'" .
        " AND start_time <= '$now' AND end_time >= '$now' AND review_status =3  " . $u_id . $a_range .
        " ORDER BY sort_order";
    $res = $GLOBALS['db']->getAll($sql);
    $arr=array();
    foreach ($res as $key => $row)
    {
        $arr = array_merge($arr, explode(',', $row['act_range_ext']));
    }

//    print_arr($arr);
    return array_unique($arr);
}

// 获取活动id数组
function get_favourable_id($favourable)
{
    $arr = array();
    foreach ($favourable as $key => $value)
    {
        $arr[$key] = $value['act_id'];
    }
//    print_arr($arr);
    return $arr;
}

/**
 * 购物车中是否已经有某优惠
 * @param   array   $favourable     优惠活动
 * @param   array   $cart_favourable购物车中已有的优惠活动及数量
 */
function favourable_used($favourable, $cart_favourable)
{
    if ($favourable['act_type'] == FAT_GOODS)
    {
        return isset($cart_favourable[$favourable['act_id']]) &&
        $cart_favourable[$favourable['act_id']] >= $favourable['act_type_ext'] &&
        $favourable['act_type_ext'] > 0;
    }
    else
    {
        return isset($cart_favourable[$favourable['act_id']]);
    }
}

/**
 * 检查订单中商品库存
 *
 * @access  public
 * @param   array   $arr
 *
 * @return  void
 */
function flow_cart_stock($arr,$store_id=0)
{
    //ecmoban模板堂 --zhuo start
    if(!empty($_SESSION['user_id'])){
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    foreach ($arr AS $key => $val)
    {
        $val = intval(make_semiangle($val));
        if ($val <= 0 || !is_numeric($key))
        {
            continue;
        }

        $sql = "SELECT `goods_id`, `goods_attr_id`, `extension_code`, `warehouse_id` FROM" .$GLOBALS['ecs']->table('cart').
            " WHERE rec_id='$key' AND ". $sess_id;
        $goods = $GLOBALS['db']->getRow($sql);

        $sql = "SELECT g.goods_name, g.goods_number, g.goods_id, c.product_id, g.model_attr ".
            "FROM " .$GLOBALS['ecs']->table('goods'). " AS g, ".
            $GLOBALS['ecs']->table('cart'). " AS c ".
            "WHERE g.goods_id = c.goods_id AND c.rec_id = '$key'";
        $row = $GLOBALS['db']->getRow($sql);

        //ecmoban模板堂 --zhuo start
        $sql = "select IF(g.model_inventory < 1, g.goods_number, IF(g.model_inventory < 2, wg.region_number, wag.region_number)) AS goods_number " .
            " from " . $GLOBALS['ecs']->table('goods') ." as g ".
            " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id" .
            " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id" .
            " where g.goods_id = '" .$row['goods_id']. "'";
        $goods_number = $GLOBALS['db']->getOne($sql);

        $row['goods_number'] = $goods_number;
        //ecmoban模板堂 --zhuo end

        //系统启用了库存，检查输入的商品数量是否有效
        if (intval($GLOBALS['_CFG']['use_storage']) > 0 && $goods['extension_code'] != 'package_buy' && $store_id==0)
        {
            //ecmoban模板堂 --zhuo start
            /* 是货品 */
            $row['product_id'] = trim($row['product_id']);
            if (!empty($row['product_id']))
            {
                //ecmoban模板堂 --zhuo start
                if($row['model_attr'] == 1){
                    $table_products = "products_warehouse";
                }elseif($row['model_attr'] == 2){
                    $table_products = "products_area";
                }else{
                    $table_products = "products";
                }
                //ecmoban模板堂 --zhuo end

                $sql = "SELECT product_number FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$row['goods_id']. "' and product_id = '" . $row['product_id'] . "'";
                $product_number = $GLOBALS['db']->getOne($sql);
                if ($product_number < $val)
                {
                    show_message(sprintf(L('stock_insufficiency'), $row['goods_name'],
                        $product_number, $product_number));
                    exit;
                }
            }else{
                if ($row['goods_number'] < $val)
                {
                    show_message(sprintf(L('stock_insufficiency'), $row['goods_name'],
                        $row['goods_number'], $row['goods_number']));
                    exit;
                }
            }
            //ecmoban模板堂 --zhuo end
        }elseif(intval($GLOBALS['_CFG']['use_storage']) >0 && $store_id > 0){
            $sql = "SELECT goods_number,ru_id FROM".$GLOBALS['ecs']->table("store_goods")." WHERE store_id = '$store_id' AND goods_id = '".$row['goods_id']."' ";
            $goodsInfo = $GLOBALS['db']->getRow($sql);

            $products = get_warehouse_id_attr_number($row['goods_id'], $goods['goods_attr_id'], $goodsInfo['ru_id'], 0, 0,'',$store_id);//获取属性库存

            $attr_number = $products['product_number'];
            if($goods['goods_attr_id']){ //当商品没有属性库存时
                $row['goods_number'] = $attr_number;
            }else{
                $row['goods_number'] = $goodsInfo['goods_number'];
            }
            if ($row['goods_number'] < $val)
            {
                show_message(sprintf(L('stock_store_shortage'), $row['goods_name'],
                    $row['goods_number'], $row['goods_number']));
                exit;
            }
        }
        elseif (intval($GLOBALS['_CFG']['use_storage']) > 0 && $goods['extension_code'] == 'package_buy')
        {
            if (judge_package_stock($goods['goods_id'], $val))
            {
                show_message(L('package_stock_insufficiency'));
                exit;
            }
        }
    }

}
/***
 * 获取用户拥有的优惠券 默认返回所有用户所拥有的优惠券; bylu
 * @param string $user_id 用户ID;
 * @param bool|false $is_use 找出当前用户可以使用的
 * @param bool|false $total 订单总价
 * @param bool|false $cart_goods 商品信息
 * @param bool|false $user 用于区分是否会员中心里取数据(会员中心里的优惠券不能分组)
 * @return mixed 优惠券数组
 */
function get_user_coupons_list($user_id = '', $is_use = false, $total = false, $cart_goods = false, $user = true, $num=10) {

    $time = gmtime();

    //可使用的(平台用平台发的,商家用商家发的,当订单中混合了平台与商家的商品时,各自计算各自的商品总价是否达到各自发放的优惠券门槛,达到的话当前整个订单即可使用该优惠券)
    if ($is_use && $total && $cart_goods) {
            //获取到订单中不同商家或平台的所有商品总价
            foreach($cart_goods as $k=>$v){
                    $res[$v['ru_id']][]=$v;
            }
            foreach($res as $k=>$v){
                foreach($v as $m=>$n){
                    $store_total[$k]+=$n['goods_price'] * $n['goods_number'];
                }
            }

            //获取当前订单可使用的优惠券

            foreach ($cart_goods as $k => $v) {
                foreach($store_total as $m=>$n){

                    $where = " WHERE cu.is_use=0 AND c.cou_end_time > $time AND $time>c.cou_start_time AND " . $n . " >= c.cou_man AND cu.user_id ='$user_id'
                        AND (c.cou_goods =0 OR FIND_IN_SET('".$v['goods_id']."',c.cou_goods)) AND c.ru_id='" . $v['ru_id'] . "'";
                    $sql = " SELECT c.*,cu.*,o.order_sn,o.add_time FROM " . $GLOBALS['ecs']->table('coupons_user') . " cu LEFT JOIN " . $GLOBALS['ecs']->table('coupons') . " c ON c.cou_id=cu.cou_id LEFT JOIN " . $GLOBALS['ecs']->table('order_info') . " o ON cu.order_id=o.order_id $where ";
                    $arrr[] = $GLOBALS['db']->getAll($sql);
                }
            }

            $number = 0;

            if(!empty($arrr)){
                foreach ($arrr as $k => $v) {
                    foreach ($v as $m => $n) {
                        $number ++;
                        $arr[$n['uc_id']] = $n;
                    }
                }
            }
            if($num == 0){
                return $number;
            }

            return $arr;

    } else {

        if (!empty($user_id) && $user) {
            $where = " WHERE cu.user_id IN(" . $user_id . ")";
        } else if (!empty($user_id)) {
            $where = " WHERE cu.user_id IN(" . $user_id . ") GROUP BY c.cou_id";
        }
        $res = $GLOBALS['db']->getAll(" SELECT c.*,cu.*,o.order_sn,o.add_time FROM " .
                $GLOBALS['ecs']->table('coupons_user') . " cu LEFT JOIN " . $GLOBALS['ecs']->table('coupons') .
                " c ON c.cou_id=cu.cou_id LEFT JOIN " . $GLOBALS['ecs']->table('order_info') .
                " o ON cu.order_id=o.order_id $where ");
        return $res;
    }
}
/**
 * 获取优惠券信息
 */
function getCoupons($id){
    $sql = "SELECT cou_id, cou_money FROM " . $GLOBALS['ecs']->table('coupons') . " WHERE cou_id = " . $id;
    return $GLOBALS['db']->getRow($sql);
}
/**
 * 获取优惠券信息
 * 根据用户的优惠券记录
 */
function getCouponsByUcId($id){
    $sql = "SELECT cou_id FROM " . $GLOBALS['ecs']->table('coupons_user') . " WHERE uc_id = " . $id;
    $couId = $GLOBALS['db']->getOne($sql);
    $sql = "SELECT cou_id, cou_money FROM " . $GLOBALS['ecs']->table('coupons') . " WHERE cou_id = " . $couId;
    return $GLOBALS['db']->getRow($sql);
}
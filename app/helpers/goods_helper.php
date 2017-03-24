<?php
defined('IN_ECTOUCH') or die('Deny Access');
function get_xiaoliang($goods_id = 0)
{
    $sql = 'SELECT sum(goods_number) FROM ' . $GLOBALS['ecs']->table('order_goods') . ' WHERE goods_id =' . $goods_id;
    $xl = $GLOBALS['db']->getOne($sql);
    if (empty($xl)) {
        $xl = 0;
    }

    return $xl;
}

/**
 * 商品推荐usort用自定义排序行数
 */
function goods_sort($goods_a, $goods_b)
{
    if ($goods_a['sort_order'] == $goods_b['sort_order']) {
        return 0;
    }
    return ($goods_a['sort_order'] < $goods_b['sort_order']) ? -1 : 1;

}

/**
 * 获得指定分类同级的所有分类以及该分类下的子分类
 *
 * @access  public
 * @param   integer $cat_id 分类编号
 * @return  array
 */
function get_categories_tree($cat_id = 0)
{
    if ($cat_id > 0) {
        $sql = 'SELECT parent_id FROM ' . $GLOBALS['ecs']->table('category') . " WHERE cat_id = '$cat_id'";
        $parent_id = $GLOBALS['db']->getOne($sql);
    } else {
        $parent_id = 0;
    }

    /*
     判断当前分类中全是是否是底级分类，
     如果是取出底级分类上级分类，
     如果不是取当前分类及其下的子分类
    */

    //$sql = 'SELECT count(*) FROM ' . $GLOBALS['ecs']->table('category') . " WHERE parent_id = '$parent_id' AND is_show = 1";
    $sql = 'SELECT cat_id FROM ' . $GLOBALS['ecs']->table('category') . " WHERE parent_id = '$parent_id' AND is_show = 1 LIMIT 1";
    if ($GLOBALS['db']->getOne($sql) || $parent_id == 0) {
        /* 获取当前分类及其子分类 */
        $sql = 'SELECT cat_id,cat_name ,parent_id,is_show, category_links ' .
            'FROM ' . $GLOBALS['ecs']->table('category') .
            "WHERE parent_id = '$parent_id' AND is_show = 1 ORDER BY sort_order ASC, cat_id ASC";

        $res = $GLOBALS['db']->getAll($sql);

        foreach ($res AS $row) {
            if ($row['is_show']) {
                $cat_arr[$row['cat_id']]['id'] = $row['cat_id'];
                $cat_arr[$row['cat_id']]['name'] = $row['cat_name'];
                $cat_arr[$row['cat_id']]['url'] = build_uri('category', array('cid' => $row['cat_id']), $row['cat_name']);

                if (isset($row['cat_id']) != NULL) {
                    $cat_arr[$row['cat_id']]['cat_id'] = get_child_tree($row['cat_id']);
                }
            }
        }
    }
    if (isset($cat_arr)) {
        return $cat_arr;
    }
    return false;
}

function get_child_tree($tree_id = 0, $top = 0)
{
    $three_arr = array();
    $where = "";
    $sql = 'SELECT count(*) FROM ' . $GLOBALS['ecs']->table('category') . " WHERE parent_id = '$tree_id' AND is_show = 1" . $where;
    if ($GLOBALS['db']->getOne($sql) || $tree_id == 0) {
        $child_sql = 'SELECT c.cat_id, c.cat_name, c.touch_icon,c.parent_id, c.cat_alias_name, c.is_show, (SELECT goods_thumb FROM ' . $GLOBALS['ecs']->table('goods') . ' WHERE cat_id = c.cat_id AND is_on_sale = 1 AND is_delete = 0 ORDER BY sort_order ASC, goods_id DESC limit 1 ) as goods_thumb ' .
            ' FROM ' . $GLOBALS['ecs']->table('category') . ' c' .
            " WHERE c.parent_id = '$tree_id' AND c.is_show = 1 " . $where . " ORDER BY c.sort_order ASC, c.cat_id ASC";

        $res = $GLOBALS['db']->getAll($child_sql);
        foreach ($res AS $k => $row) {
            if ($row['is_show']) {
                $three_arr[$k]['id'] = $row['cat_id'];
                $three_arr[$k]['name'] = $row['cat_alias_name'] ? $row['cat_alias_name'] : $row['cat_name'];
                $three_arr[$k]['url'] = url('category/index/products', array('id' => $row['cat_id']));
                $three_arr[$k]['cat_img'] = !empty($row['touch_icon']) ? get_image_path($row['touch_icon']) : get_image_path($row['goods_thumb']);
                $three_arr[$k]['haschild'] = 0;
            }
            if (isset($row['cat_id'])) {
                $child_tree = get_child_tree($row['cat_id']);
                if ($child_tree) {
                    $three_arr[$k]['cat_id'] = $child_tree;
                    $three_arr[$k]['haschild'] = 1;
                }
            }
        }
    }
    return $three_arr;
}

/**
 * 调用当前分类的销售排行榜
 *
 * @access  public
 * @param   string $cats 查询的分类
 * @return  array
 */
function get_top10($cats = '', $presale)
{
    //ecmoban模板堂 --zhuo start
    $cats = get_category_parentChild_tree1($cats, 1);
    $cats = arr_foreach($cats);

    if ($cats) {
        $cats = implode(",", $cats) . "," . $cats;
        $cats = get_children($cats, 0, 1);
    } else {
        $cats = "g.cat_id IN ($cats)";
    }
    //ecmoban模板堂 --zhuo end


    $where = !empty($cats) ? "AND ($cats OR " . get_extension_goods($cats) . ") " : '';
    if ($presale == 'presale') {
        $where .= " AND ( SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('presale_activity') . "AS pa WHERE pa.goods_id = g.goods_id) > 0 AND pa.review_status = 3 ";
    }

    /* 排行统计的时间 */
    switch ($GLOBALS['_CFG']['top10_time']) {
        case 1: // 一年
            $top10_time = "AND o.order_sn >= '" . date('Ymd', gmtime() - 365 * 86400) . "'";
            break;
        case 2: // 半年
            $top10_time = "AND o.order_sn >= '" . date('Ymd', gmtime() - 180 * 86400) . "'";
            break;
        case 3: // 三个月
            $top10_time = "AND o.order_sn >= '" . date('Ymd', gmtime() - 90 * 86400) . "'";
            break;
        case 4: // 一个月
            $top10_time = "AND o.order_sn >= '" . date('Ymd', gmtime() - 30 * 86400) . "'";
            break;
        default:
            $top10_time = '';
    }

    $sql = 'SELECT g.goods_id, g.goods_name, g.goods_thumb, SUM(og.goods_number) as goods_number,g.comments_number, g.market_price, g.shop_price , g.promote_price, g.market_price ' .
        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g, ' .
        $GLOBALS['ecs']->table('order_info') . ' AS o, ' .
        $GLOBALS['ecs']->table('order_goods') . ' AS og ' .
        "WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 $where $top10_time ";
    //判断是否启用库存，库存数量是否大于0
    if ($GLOBALS['_CFG']['use_storage'] == 1) {
        $sql .= " AND g.goods_number > 0 ";
    }
    $sql .= ' AND og.order_id = o.order_id AND og.goods_id = g.goods_id ' .
        "AND (o.order_status = '" . OS_CONFIRMED . "' OR o.order_status = '" . OS_SPLITED . "') " .
        "AND (o.pay_status = '" . PS_PAYED . "' OR o.pay_status = '" . PS_PAYING . "') " .
        "AND (o.shipping_status = '" . SS_SHIPPED . "' OR o.shipping_status = '" . SS_RECEIVED . "') " .
        'GROUP BY g.goods_id ORDER BY goods_number DESC, g.goods_id DESC LIMIT ' . $GLOBALS['_CFG']['top_number'];

    $arr = $GLOBALS['db']->getAll($sql);

    for ($i = 0, $count = count($arr); $i < $count; $i++) {
        $arr[$i]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
            sub_str($arr[$i]['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $arr[$i]['goods_name'];
        $arr[$i]['url'] = build_uri('goods', array('gid' => $arr[$i]['goods_id']), $arr[$i]['goods_name']);
        $arr[$i]['thumb'] = get_image_path($arr[$i]['goods_thumb']);
        $arr[$i]['price'] = price_format($arr[$i]['shop_price']);
        /* 折扣节省计算 by ecmoban start */
        if ($arr[$i]['market_price'] > 0) {
            $discount_arr = get_discount($arr[$i]['goods_id']); //函数get_discount参数goods_id
        }
        $arr[$i]['zhekou'] = $discount_arr['discount'];  //zhekou
        $arr[$i]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
    }

    return $arr;
}

/**
 * 获得推荐商品
 *
 * @access  public
 * @param   string $type 推荐类型，可以是 best, new, hot
 * @return  array
 */
function get_recommend_goods($type = '', $cats = '', $warehouse_id = 0, $area_id = 0, $ru_id = 0, $rec_type = 0, $presale = '')
{
    if (!in_array($type, array('best', 'new', 'hot'))) {
        return array();
    }

    //ecmoban模板堂 --zhuo start
    $leftJoin = '';
    $tag_where = '';
    if ($presale == 'presale') {
        $tag_where .= " AND ( SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('presale_activity') . "AS pa WHERE pa.goods_id = g.goods_id) > 0 AND pa.review_status = 3 ";
    }
    if ($GLOBALS['_CFG']['open_area_goods'] == 1) { //关联地区显示商品
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
        $tag_where .= " and lag.region_id = '$area_id' ";
    }

    if ($ru_id > 0) {
        $tag_where .= " and g.user_id = '$ru_id' ";
        $goods_hot_new_best = 'g.store_hot = 1 OR g.store_new = 1 OR g.store_best = 1';
        $goods_hnb_files = "g.store_new as is_new, g.store_hot as is_hot, g.store_best as is_best,";
    } else {
        $goods_hot_new_best = 'g.is_best = 1 OR g.is_new =1 OR g.is_hot = 1';
        $goods_hnb_files = "g.is_best, g.is_new, g.is_hot,";
    }

    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $tag_where .= ' AND g.review_status > 2 ';
    }
    //ecmoban模板堂 --zhuo end

    //取不同推荐对应的商品
    static $type_goods = array();
    if (empty($type_goods[$type])) {
        //初始化数据
        $type_goods['best'] = array();
        $type_goods['new'] = array();
        $type_goods['hot'] = array();
        $data = read_static_cache('recommend_goods');
        if ($data === false) {
            $sql = 'SELECT g.goods_id, ' . $goods_hnb_files . ' g.is_promote, b.brand_name,g.sort_order ' .
                ' FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
                ' LEFT JOIN ' . $GLOBALS['ecs']->table('brand') . ' AS b ON b.brand_id = g.brand_id ' .
                $leftJoin .
                ' WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND (' . $goods_hot_new_best . ')' . $tag_where .
                ' ORDER BY g.sort_order, g.last_update DESC';
            $goods_res = $GLOBALS['db']->getAll($sql);
            //定义推荐,最新，热门，促销商品
            $goods_data['best'] = array();
            $goods_data['new'] = array();
            $goods_data['hot'] = array();
            $goods_data['brand'] = array();
            if (!empty($goods_res)) {
                foreach ($goods_res as $data) {
                    if ($data['is_best'] == 1) {
                        $goods_data['best'][] = array('goods_id' => $data['goods_id'], 'sort_order' => $data['sort_order']);
                    }
                    if ($data['is_new'] == 1) {
                        $goods_data['new'][] = array('goods_id' => $data['goods_id'], 'sort_order' => $data['sort_order']);
                    }
                    if ($data['is_hot'] == 1) {
                        $goods_data['hot'][] = array('goods_id' => $data['goods_id'], 'sort_order' => $data['sort_order']);
                    }
                    if ($data['brand_name'] != '') {
                        $goods_data['brand'][$data['goods_id']] = $data['brand_name'];
                    }
                }
            }
            write_static_cache('recommend_goods', $goods_data);
        } else {
            $goods_data = $data;
        }

        $time = gmtime();
        $order_type = $GLOBALS['_CFG']['recommend_order'];

        //按推荐数量及排序取每一项推荐显示的商品 order_type可以根据后台设定进行各种条件显示
        static $type_array = array();

        if ($rec_type == 0) {
            $type2lib = array('best' => 'recommend_best', 'new' => 'recommend_new', 'hot' => 'recommend_hot');
        } elseif ($rec_type == 1) {
            $type2lib = array('best' => 'recommend_best_goods', 'new' => 'recommend_new_goods', 'hot' => 'recommend_hot_goods');
        }

        if (empty($type_array)) {
            foreach ($type2lib as $key => $data) {
                if (!empty($goods_data[$key])) {
                    $num = get_library_number($data);
                    $data_count = count($goods_data[$key]);
                    $num = $data_count > $num ? $num : $data_count;
                    if ($order_type == 0) {
                        //usort($goods_data[$key], 'goods_sort');
                        $rand_key = array_slice($goods_data[$key], 0, $num);
                        foreach ($rand_key as $key_data) {
                            $type_array[$key][] = $key_data['goods_id'];
                        }
                    } else {
                        $rand_key = array_rand($goods_data[$key], $num);
                        if ($num == 1) {
                            $type_array[$key][] = $goods_data[$key][$rand_key]['goods_id'];
                        } else {
                            foreach ($rand_key as $key_data) {
                                $type_array[$key][] = $goods_data[$key][$key_data]['goods_id'];
                            }
                        }
                    }
                } else {
                    $type_array[$key] = array();
                }
            }
        }

        //取出所有符合条件的商品数据，并将结果存入对应的推荐类型数组中
        $sql = 'SELECT g.goods_id, g.goods_name, g.goods_name_style, g.comments_number, g.sales_volume, g.market_price, ' .
            'g.is_best, g.is_new, g.is_hot, g.user_id, ' .
            ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
            "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, " .
            "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
            "promote_start_date, promote_end_date, g.is_promote, g.goods_brief, g.goods_thumb, g.goods_img, RAND() AS rnd " .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
            $leftJoin .
            "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ";
        $type_merge = array_merge($type_array['new'], $type_array['best'], $type_array['hot']);
        $type_merge = array_unique($type_merge);
        $sql .= ' WHERE g.goods_id ' . db_create_in($type_merge);
        $sql .= $tag_where;
        $sql .= ' ORDER BY g.sort_order, g.last_update DESC';

        $result = $GLOBALS['db']->getAll($sql);
        foreach ($result AS $idx => $row) {
            if ($row['promote_price'] > 0) {
                $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            } else {
                $promote_price = 0;
            }
            /**
             * 重定义商品价格
             * 商品价格 + 属性价格
             * start
             */
            $price_other = array(
                'market_price' => $row['market_price'],
                'org_price' => $row['org_price'],
                'shop_price' => $row['shop_price'],
                'promote_price' => $promote_price,
            );

            $price_info = get_goods_one_attr_price($row['goods_id'], $warehouse_id, $area_id, $price_other);
            $row = !empty($row) ? array_merge($row, $price_info) : $row;
            $promote_price = $row['promote_price'];
            /**
             * 重定义商品价格
             * end
             */
            /* 折扣节省计算 by ecmoban start */
            if ($row['market_price'] > 0) {
                $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
            }
            $goods[$idx]['zhekou'] = $discount_arr['discount'];  //zhekou
            $goods[$idx]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
            /* 折扣节省计算 by ecmoban end */
            $goods[$idx]['id'] = $row['goods_id'];
            $goods[$idx]['name'] = $row['goods_name'];
            $goods[$idx]['is_promote'] = $row['is_promote'];
            $goods[$idx]['brief'] = $row['goods_brief'];
            $goods[$idx]['comments_number'] = $row['comments_number'];
            $goods[$idx]['sales_volume'] = $row['sales_volume'];
            $goods[$idx]['brand_name'] = isset($goods_data['brand'][$row['goods_id']]) ? $goods_data['brand'][$row['goods_id']] : '';
            $goods[$idx]['goods_style_name'] = add_style($row['goods_name'], $row['goods_name_style']);
            $goods[$idx]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
            $goods[$idx]['short_style_name'] = add_style($goods[$idx]['short_name'], $row['goods_name_style']);
            $goods[$idx]['market_price'] = price_format($row['market_price']);
            $goods[$idx]['shop_price'] = price_format($row['shop_price']);
            $goods[$idx]['promote_price'] = $promote_price > 0 ? price_format($promote_price) : '';
            $goods[$idx]['thumb'] = get_image_path($row['goods_thumb']);
            $goods[$idx]['goods_img'] = get_image_path($row['goods_img']);
            $goods[$idx]['shop_name'] = get_shop_name($row['user_id'], 1);
            $goods[$idx]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
            $goods[$idx]['shopUrl'] = url('store/index/index', array('id' => $row['user_id']));
            if (in_array($row['goods_id'], $type_array['best'])) {
                $type_goods['best'][] = $goods[$idx];
            }
            if (in_array($row['goods_id'], $type_array['new'])) {
                $type_goods['new'][] = $goods[$idx];
            }
            if (in_array($row['goods_id'], $type_array['hot'])) {
                $type_goods['hot'][] = $goods[$idx];
            }
        }
    }
    return $type_goods[$type];
}

/**
 * 获得促销商品
 *
 * @access  public
 * @return  array
 */
function get_promote_goods($cats = '', $warehouse_id = 0, $area_id = 0)
{
    $time = gmtime();
    $order_type = $GLOBALS['_CFG']['recommend_order'];

    $leftJoin = "";
    //ecmoban模板堂 --zhuo start
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    $where = '';
    if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
        $where .= " and lag.region_id = '$area_id' ";
    }

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND g.review_status > 2 ';
    }
    //ecmoban模板堂 --zhuo end

    /* 取得促销lbi的数量限制 */
    $num = get_library_number("recommend_promotion");
    $sql = 'SELECT g.goods_id, g.goods_name, g.goods_name_style, g.comments_number, g.sales_volume,g.market_price, ' .
        ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
        "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
        "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, " .
        "promote_start_date, promote_end_date, g.goods_brief, g.goods_thumb, goods_img, b.brand_name, " .
        "g.is_best, g.is_new, g.is_hot, g.is_promote, RAND() AS rnd " .
        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
        $leftJoin .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('brand') . ' AS b ON b.brand_id = g.brand_id ' .
        "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        'WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ' .
        " AND g.is_promote = 1 AND promote_start_date <= '$time' AND promote_end_date >= '$time' " . $where;
    $sql .= $order_type == 0 ? ' ORDER BY g.sort_order, g.last_update DESC' : ' ORDER BY rnd';
    $sql .= " LIMIT $num ";
    $result = $GLOBALS['db']->getAll($sql);

    $goods = array();
    foreach ($result AS $idx => $row) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            $goods[$idx]['promote_price'] = $promote_price > 0 ? price_format($promote_price) : '';
        } else {
            $goods[$idx]['promote_price'] = '';
        }
        /* 折扣节省计算 by ecmoban start */
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id'], $warehouse_id, $area_id); //函数get_discount参数goods_id
        }

        $goods[$idx]['zhekou'] = $discount_arr['discount'];  //zhekou
        $goods[$idx]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
        $goods[$idx]['id'] = $row['goods_id'];
        $goods[$idx]['s_time'] = $row['promote_start_date'];
        $goods[$idx]['e_time'] = $row['promote_end_date'];
        $goods[$idx]['t_now'] = $time;
        $goods[$idx]['id'] = $row['goods_id'];
        $goods[$idx]['name'] = $row['goods_name'];
        $goods[$idx]['brief'] = $row['goods_brief'];
        $goods[$idx]['brand_name'] = $row['brand_name'];
        $goods[$idx]['comments_number'] = $row['comments_number'];
        $goods[$idx]['sales_volume'] = $row['sales_volume'];
        $goods[$idx]['goods_style_name'] = add_style($row['goods_name'], $row['goods_name_style']);
        $goods[$idx]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        $goods[$idx]['short_style_name'] = add_style($goods[$idx]['short_name'], $row['goods_name_style']);
        $goods[$idx]['market_price'] = price_format($row['market_price']);
        $goods[$idx]['shop_price'] = price_format($row['shop_price']);
        $goods[$idx]['thumb'] = get_image_path($row['goods_thumb']);
        $goods[$idx]['goods_img'] = get_image_path($row['goods_img']);
        $goods[$idx]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
    }

    return $goods;
}

/**
 * 获得指定分类下的推荐商品
 *
 * @access  public
 * @param   string $type 推荐类型，可以是 best, new, hot, promote
 * @param   string $cats 分类的ID
 * @param   integer $brand 品牌的ID
 * @param   integer $min 商品价格下限
 * @param   integer $max 商品价格上限
 * @param   string $ext 商品扩展查询
 * @return  array
 */
function get_category_recommend_goods($type = '', $cats = '', $brand = 0, $min = 0, $max = 0, $ext = '', $warehouse_id = 0, $area_id = 0, $num = 0)
{
    $brand_where = ($brand > 0) ? " AND g.brand_id = '$brand'" : '';

    $price_where = ($min > 0) ? " AND g.shop_price >= $min " : '';
    $price_where .= ($max > 0) ? " AND g.shop_price <= $max " : '';

    //ecmoban模板堂 --zhuo start
    $where = '';
    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND g.review_status > 2 ';
    }

    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    if ($GLOBALS['_CFG']['open_area_goods'] == 1) {
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
        $where .= " and lag.region_id = '$area_id' ";
    }
    //ecmoban模板堂 --zhuo end

    $sql = 'SELECT g.goods_id, g.goods_name, g.goods_name_style, g.market_price, g.comments_number ,g.sales_volume,' .
        ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
        "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
        "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price," .
        'promote_start_date, promote_end_date, g.goods_brief, g.goods_thumb, goods_img, b.brand_name ' .
        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
        $leftJoin .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('brand') . ' AS b ON b.brand_id = g.brand_id ' .
        "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        'WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ' . $where . $brand_where . $price_where . $ext;
    $type2lib = array('best' => 'recommend_best', 'new' => 'recommend_new', 'hot' => 'recommend_hot', 'promote' => 'recommend_promotion');

    if ($num == 0) {
        $num = 0;
        $num = get_library_number($type2lib[$type]);
    }

    switch ($type) {
        case 'best':
            $sql .= ' AND is_best = 1';
            break;
        case 'new':
            $sql .= ' AND is_new = 1';
            break;
        case 'hot':
            $sql .= ' AND is_hot = 1';
            break;
        case 'promote':
            $time = gmtime();
            $sql .= " AND is_promote = 1 AND promote_start_date <= '$time' AND promote_end_date >= '$time'";
            break;
    }

    if (!empty($cats)) {
        $sql .= " AND (" . $cats . " OR " . get_extension_goods($cats) . ")";
    }

    $order_type = $GLOBALS['_CFG']['recommend_order'];
    $sql .= ($order_type == 0) ? ' ORDER BY g.sort_order, g.last_update DESC' : ' ORDER BY RAND()';
    $res = $GLOBALS['db']->selectLimit($sql, $num);

    $idx = 0;
    $goods = array();
    foreach ($res as $row) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            $goods[$idx]['promote_price'] = $promote_price > 0 ? price_format($promote_price) : '';
        } else {
            $goods[$idx]['promote_price'] = '';
        }

        $goods[$idx]['id'] = $row['goods_id'];
        /* 折扣节省计算 by ecmoban start */
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $goods[$idx]['zhekou'] = $discount_arr['discount'];  //zhekou
        $goods[$idx]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
        $goods[$idx]['comments_number'] = $row['comments_number'];
        $goods[$idx]['sales_volume'] = $row['sales_volume'];
        $goods[$idx]['name'] = $row['goods_name'];
        $goods[$idx]['brief'] = $row['goods_brief'];
        $goods[$idx]['brand_name'] = $row['brand_name'];
        $goods[$idx]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
            sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        $goods[$idx]['market_price'] = price_format($row['market_price']);
        $goods[$idx]['shop_price'] = price_format($row['shop_price']);
        $goods[$idx]['thumb'] = get_image_path($row['goods_thumb']);
        $goods[$idx]['goods_img'] = get_image_path($row['goods_img']);
        $goods[$idx]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);

        $goods[$idx]['short_style_name'] = add_style($goods[$idx]['short_name'], $row['goods_name_style']);
        $idx++;
    }

    return $goods;
}

/**
 * 获得商品的详细信息
 * @param $goods_id
 * @param int $warehouse_id
 * @param int $area_id
 * @return bool
 */
function get_goods_info($goods_id, $warehouse_id = 0, $area_id = 0)
{
    $time = gmtime();
    $tag = array();
    //ecmoban模板堂 --zhuo start
    $leftJoin = '';

    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end

    $sql = 'SELECT g.*, ' . $shop_price .
        " IF(g.model_inventory < 1, g.goods_number, IF(g.model_inventory < 2, wg.region_number, wag.region_number)) as goods_number," .
        " IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price," .
        " IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price," .
        ' c.measure_unit, g.brand_id as brand_id, b.brand_logo, g.comments_number, g.sales_volume,b.brand_name AS goods_brand, m.type_money AS bonus_money, ' .
        'IFNULL(AVG(r.comment_rank), 0) AS comment_rank, ' .
        "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS rank_price " .
        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('category') . ' AS c ON g.cat_id = c.cat_id ' .
        $leftJoin .

        'LEFT JOIN ' . $GLOBALS['ecs']->table('comment') . ' AS r ' .
        'ON r.id_value = g.goods_id AND comment_type = 0 AND r.parent_id = 0 AND r.status = 1 ' .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('bonus_type') . ' AS m ' .
        "ON g.bonus_type_id = m.type_id AND m.send_start_date <= '$time' AND m.send_end_date >= '$time'" .
        " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('brand') . ' AS b ON b.brand_id = g.brand_id ' .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('link_brand') . 'AS lb ON lb.brand_id = g.brand_id ' .
        "WHERE g.goods_id = '$goods_id' AND g.is_delete = 0 " .
        "GROUP BY g.goods_id";
    $row = $GLOBALS['db']->getRow($sql);

    if ($row !== false) {
        /* 用户评论级别取整 */
        $row['comment_rank'] = ceil($row['comment_rank']) == 0 ? 5 : ceil($row['comment_rank']);

        /* 折扣节省计算 by ecmoban start */
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $row['zhekou'] = $discount_arr['discount'];  //zhekou
        $row['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */

        /* 修正促销价格 */
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
            $promote_price = 0;
        }

        /* 处理商品水印图片 */
        $watermark_img = '';

        if ($promote_price != 0) {
            $watermark_img = "watermark_promote";
        } elseif ($row['is_new'] != 0) {
            $watermark_img = "watermark_new";
        } elseif ($row['is_best'] != 0) {
            $watermark_img = "watermark_best";
        } elseif ($row['is_hot'] != 0) {
            $watermark_img = 'watermark_hot';
        }

        if ($watermark_img != '') {
            $row['watermark_img'] = $watermark_img;
        }

        $row['promote_price_org'] = $promote_price;
        $row['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';

        /* 促销时间倒计时 */
        $time = gmtime();
        if ($time >= $row['promote_start_date'] && $time <= $row['promote_end_date']) {
            $row['gmt_end_time'] = $row['promote_end_date'];
        } else {
            $row['gmt_end_time'] = 0;
        }

        $row['promote_end_time'] = !empty($row['gmt_end_time']) ? local_date($GLOBALS['_CFG']['time_format'], $row['gmt_end_time']) : 0;

        /* 是否显示商品库存数量 */
        $row['goods_number'] = ($GLOBALS['_CFG']['use_storage'] == 1) ? $row['goods_number'] : '1'; // 同步pc by zhuo

        $row['attr_number'] = $row['goods_number'];

        /* 修正积分：转换为可使用多少积分（原来是可以使用多少钱的积分） */
        $row['integral'] = $GLOBALS['_CFG']['integral_scale'] ? round($row['integral'] * 100 / $GLOBALS['_CFG']['integral_scale']) : 0;

        /* 修正优惠券 */
        $row['bonus_money'] = ($row['bonus_money'] == 0) ? 0 : price_format($row['bonus_money'], false);

        //OSS文件存储ecmoban模板堂 --zhuo start
        if ($GLOBALS['_CFG']['open_oss'] == 1) {
            $bucket_info = get_bucket_info();
            if ($row['goods_desc']) {
                $desc_preg = get_goods_desc_images_preg($bucket_info['endpoint'], $row['goods_desc']);
                $row['goods_desc'] = $desc_preg['goods_desc'];
            }
        }
        //OSS文件存储ecmoban模板堂 --zhuo end

        /* 修正商品图片 */
        $row['goods_img'] = get_image_path($row['goods_img']);
        $row['goods_thumb'] = get_image_path($row['goods_thumb']);
        /* 获得商品的销售价格 */
        $row['marketPrice'] = $row['market_price'];
        $row['market_price'] = price_format($row['market_price']);
        if ($promote_price > 0) {
            $row['shop_price_formated'] = $row['promote_price'];
            $row['goods_price'] = $promote_price;
        } else {
            $row['shop_price_formated'] = price_format($row['shop_price']);
            $row['goods_price'] = $row['shop_price'];
        }

        $row['promote_price'] = $promote_price > 0 ? price_format($promote_price) : '';

        $row['goodsWeight'] = $row['goods_weight'];
        $row['isHas_attr'] = count($GLOBALS['db']->getAll("select goods_attr_id from " . $GLOBALS['ecs']->table('goods_attr') . " where goods_id = '$goods_id'"));

        $row['rz_shopName'] = get_shop_name($row['user_id'], 1); //店铺名称
        $row['store_url'] = url('store/index/shop_info', array('id' => $row['user_id']));

        $row['shopinfo'] = get_shop_name($row['user_id'], 2);
        $row['shopinfo']['logo_thumb'] = get_image_path(str_replace('../', '', $row['shopinfo']['logo_thumb']));
        $row['shopinfo']['brand_thumb'] = get_image_path($row['shopinfo']['brand_thumb']);

        $row['goods_url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        $consumption = get_goods_con_list($goods_id, 'goods_consumption'); //满减订单金额
        $conshipping = get_goods_con_list($goods_id, 'goods_conshipping', 1); //满减运费

        //查询关联商品描述 ecmoban模板堂 --zhuo
        $sql = "SELECT ld.goods_desc FROM " . $GLOBALS['ecs']->table('link_desc_goodsid') . " AS dg, " . $GLOBALS['ecs']->table('link_goods_desc') . " AS ld WHERE dg.goods_id = '" . $row['goods_id'] . "' AND dg.d_id = ld.id";
        $link_desc = $GLOBALS['db']->getOne($sql);

        if ($row['goods_desc'] == '<p><br/></p>' || empty($row['goods_desc'])) {
            $row['goods_desc'] = $link_desc;
        }

        $row['consumption'] = $consumption;
        $row['conshipping'] = $conshipping;
        //ecmoban模板堂 --zhuo end

        /* 修正重量显示 */
        $row['goods_weight'] = (intval($row['goods_weight']) > 0) ?
            $row['goods_weight'] . $GLOBALS['_LANG']['kilogram'] :
            ($row['goods_weight'] * 1000) . $GLOBALS['_LANG']['gram'];


        $suppliers = get_suppliers_name($row['suppliers_id']);
        $row['suppliers_name'] = $suppliers['suppliers_name'];
        //买家印象
        if ($row['goods_product_tag']) {
            $impression_list = !empty($row['goods_product_tag']) ? explode(',', $row['goods_product_tag']) : '';
            foreach ($impression_list as $kk => $vv) {
                $tag[$kk]['txt'] = $vv;
                //印象数量
                $tag[$kk]['num'] = comment_goodstag_num($row['goods_id'], $vv);
            }
            $row['impression_list'] = $tag;
        }
        //上架下架时间
        $manage_info = get_auto_manage_info($row['goods_id'], 'goods');
        if (!empty($manage_info['starttime'])) {
            $row['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $manage_info['starttime']);
        } else {
            /* 修正上架时间显示 */
            $row['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
        }

        $row['end_time'] = !empty($manage_info['endtime']) ? local_date($GLOBALS['_CFG']['time_format'], $manage_info['endtime']) : '';

        $row['collect_count'] = get_collect_goods_user_count($row['goods_id']);
        $row['is_collect'] = get_collect_user_goods($row['goods_id']);

        return $row;
    } else {
        return false;
    }
}

//查找品牌
function get_goods_brand($brand_id = 0, $ru_id = 0)
{
    $sql = "select bid as brand_id, brandName as goods_brand from " . $GLOBALS['ecs']->table('merchants_shop_brand') . " where bid = '$brand_id' AND user_id = '$ru_id' AND audit_status = 1";
    $res = $GLOBALS['db']->getRow($sql);

    return $res;
}

//by wang获得商品扩展信息
function get_goods_extends($goods_id = 0)
{
    $sql = "select * from " . $GLOBALS['ecs']->table('goods_extend') . " where goods_id='$goods_id'";
    $goods_extend = $GLOBALS['db']->getRow($sql);
    if (count($goods_extend) > 0) {
        return $goods_extend;
    } else {
        return '';
    }
}

/**
 * 获得商品的属性和规格
 * @param $goods_id
 * @param int $warehouse_id
 * @param int $area_id
 * @param string $goods_attr_id
 * @param int $attr_type
 * @return mixed
 */
function get_goods_properties($goods_id, $warehouse_id = 0, $area_id = 0, $goods_attr_id = '', $attr_type = 0)
{
    $attr_array = array();
    if (!empty($goods_attr_id)) {
        $attr_array = explode(',', $goods_attr_id);
    }

    /* 对属性进行重新排序和分组 */
    $sql = "SELECT attr_group " .
        "FROM " . $GLOBALS['ecs']->table('goods_type') . " AS gt, " . $GLOBALS['ecs']->table('goods') . " AS g " .
        "WHERE g.goods_id='$goods_id' AND gt.cat_id=g.goods_type";
    $grp = $GLOBALS['db']->getOne($sql);

    if (!empty($grp)) {
        $groups = explode("\n", strtr($grp, "\r", ''));
    }

    //ecmoban模板堂 --zhuo satrt
    $model_attr = get_table_date("goods", "goods_id = '$goods_id'", array('model_attr'), 2);
    $leftJoin = '';
    $select = '';
    if ($model_attr == 1) {
        $select = " wap.attr_price as warehouse_attr_price, ";
        $leftJoin = 'LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_attr') . " AS wap ON g.goods_attr_id = wap.goods_attr_id AND wap.warehouse_id = '$warehouse_id' ";
    } elseif ($model_attr == 2) {
        $select = " waa.attr_price as area_attr_price, ";
        $leftJoin = 'LEFT JOIN ' . $GLOBALS['ecs']->table('warehouse_area_attr') . " AS waa ON g.goods_attr_id = waa.goods_attr_id AND area_id = '$area_id' ";
    }
    //ecmoban模板堂 --zhuo end

    $goodsAttr = '';
    if ($attr_type == 1 && !empty($goods_attr_id)) {
        $goodsAttr = " and g.goods_attr_id in($goods_attr_id) ";
    }

    /* 获得商品的规格 */
    $sql = "SELECT a.attr_id, a.attr_name, a.attr_group, a.is_linked, a.attr_type, " .
        $select .
        "g.goods_attr_id, g.attr_value, g.attr_price, g.attr_img_flie, g.attr_img_site, g.attr_checked, g.attr_sort " .
        'FROM ' . $GLOBALS['ecs']->table('goods_attr') . ' AS g ' .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('attribute') . ' AS a ON a.attr_id = g.attr_id ' .
        $leftJoin .
        "WHERE g.goods_id = '$goods_id' " . $goodsAttr .
        'ORDER BY a.sort_order, g.attr_price, g.goods_attr_id';

    $res = $GLOBALS['db']->getAll($sql);

    $arr['pro'] = array();     // 属性
    $arr['spe'] = array();     // 规格
    $arr['lnk'] = array();     // 关联的属性

    foreach ($res AS $row) {

        $row['attr_value'] = str_replace("\n", '<br />', $row['attr_value']);

        if ($row['attr_type'] == 0) {
            $group = (isset($groups[$row['attr_group']])) ? $groups[$row['attr_group']] : $GLOBALS['_LANG']['goods_attr'];

            $arr['pro'][$group][$row['attr_id']]['name'] = $row['attr_name'];
            $arr['pro'][$group][$row['attr_id']]['value'] = $row['attr_value'];
        } else {
            //ecmoban模板堂 --zhuo satrt
            if ($model_attr == 1) {
                $attr_price = $row['warehouse_attr_price'];
            } elseif ($model_attr == 2) {
                $attr_price = $row['area_attr_price'];
            } else {
                $attr_price = $row['attr_price'];
            }
            //ecmoban模板堂 --zhuo end

            $arr['spe'][$row['attr_id']]['attr_type'] = $row['attr_type'];
            $arr['spe'][$row['attr_id']]['name'] = $row['attr_name'];
            $arr['spe'][$row['attr_id']]['values'][] = array(
                'label' => $row['attr_value'],
                //ecmoban模板堂 --zhuo start
                'img_flie' => get_has_attr_info($row['attr_id'], $row['attr_value'], $row['attr_img_flie'], 0),
                'img_site' => get_has_attr_info($row['attr_id'], $row['attr_value'], $row['attr_img_site'], 1),
                'checked' => $row['attr_checked'],
                'attr_sort' => $row['attr_sort'],
                'combo_checked' => get_combo_godos_attr($attr_array, $row['goods_attr_id']),
                //ecmoban模板堂 --zhuo end
                'price' => $attr_price,
                'format_price' => price_format(abs($attr_price), false),
                'id' => $row['goods_attr_id']
            );
        }

        if ($row['is_linked'] == 1) {
            /* 如果该属性需要关联，先保存下来 */
            $arr['lnk'][$row['attr_id']]['name'] = $row['attr_name'];
            $arr['lnk'][$row['attr_id']]['value'] = $row['attr_value'];
        }

    }

    return $arr;
}

/**
 * 组合购买商品属性
 * @param $attr_array
 * @param $goods_attr_id
 * @return int
 */
function get_combo_godos_attr($attr_array, $goods_attr_id)
{
    if ($attr_array) {
        for ($i = 0; $i < count($attr_array); $i++) {
            if ($attr_array[$i] == $goods_attr_id) {
                $checked = 1;
                break;
            } else {
                $checked = 0;
            }
        }
    } else {
        $checked = 0;
    }

    return $checked;
}

/**
 * 取得属性图片以及外链 替换 商品属性 图片以及外链
 * @param int $attr_id
 * @param string $attr_value
 * @param string $centent
 * @param int $type
 * @return string
 */
function get_has_attr_info($attr_id = 0, $attr_value = '', $centent = '', $type = 0)
{
    $sql = "select attr_img, attr_site from " . $GLOBALS['ecs']->table('attribute_img') . " where attr_values = '$attr_value' and attr_id = '$attr_id'";
    $res = $GLOBALS['db']->getRow($sql);

    if (empty($centent)) {
        if ($type == 0) {
            $centent = $res['attr_img'];
        } elseif ($type == 1) {
            $centent = $res['attr_site'];
        }
    }

    return $centent;
}

/**
 * 获取属性设置默认值是否大于0
 * @param array $values
 * @return int|string
 */
function get_attr_values($values = array())
{
    if (count($values) > 0) {
        $is_checked = '';
        for ($i = 0; $i < count($values); $i++) {
            $is_checked += $values[$i]['checked'];
        }

        return $is_checked;
    } else {
        return 0;
    }
}

/**
 * 获得属性相同的商品
 *
 * @access  public
 * @param   array $attr // 包含了属性名称,ID的数组
 * @return  array
 */
function get_same_attribute_goods($attr)
{
    $lnk = array();

    if (!empty($attr)) {
        foreach ($attr['lnk'] AS $key => $val) {
            $lnk[$key]['title'] = sprintf($GLOBALS['_LANG']['same_attrbiute_goods'], $val['name'], $val['value']);

            /* 查找符合条件的商品 */
            $sql = 'SELECT g.goods_id, g.goods_name, g.goods_thumb, g.sales_volume,g.comments_number,g.goods_img, g.shop_price AS org_price, ' .
                "IFNULL(mp.user_price, g.shop_price * '$_SESSION[discount]') AS shop_price, " .
                'g.market_price, g.promote_price, g.promote_start_date, g.promote_end_date ' .
                'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('goods_attr') . ' as a ON g.goods_id = a.goods_id ' .
                "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                "WHERE a.attr_id = '$key' AND g.is_on_sale=1 AND a.attr_value = '$val[value]' AND g.goods_id <> '$_REQUEST[id]' " .
                'LIMIT ' . $GLOBALS['_CFG']['attr_related_number'];
            $res = $GLOBALS['db']->getAll($sql);

            foreach ($res AS $row) {
                $lnk[$key]['goods'][$row['goods_id']]['goods_id'] = $row['goods_id'];
                $lnk[$key]['goods'][$row['goods_id']]['goods_name'] = $row['goods_name'];
                /* 折扣节省计算 by ecmoban start */
                if ($row['market_price'] > 0) {
                    $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
                }
                $lnk[$key]['goods'][$row['goods_id']]['zhekou'] = $discount_arr['discount'];  //zhekou
                $lnk[$key]['goods'][$row['goods_id']]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
                /* 折扣节省计算 by ecmoban end */
                $lnk[$key]['goods'][$row['goods_id']]['sales_volume'] = $row['sales_volume'];
                $lnk[$key]['goods'][$row['goods_id']]['comments_number'] = $row['comments_number'];
                $lnk[$key]['goods'][$row['goods_id']]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
                    sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
                $lnk[$key]['goods'][$row['goods_id']]['goods_thumb'] = (empty($row['goods_thumb'])) ? $GLOBALS['_CFG']['no_picture'] : $row['goods_thumb'];
                $lnk[$key]['goods'][$row['goods_id']]['market_price'] = price_format($row['market_price']);
                $lnk[$key]['goods'][$row['goods_id']]['shop_price'] = price_format($row['shop_price']);
                $lnk[$key]['goods'][$row['goods_id']]['promote_price'] = bargain_price($row['promote_price'],
                    $row['promote_start_date'], $row['promote_end_date']);
                $lnk[$key]['goods'][$row['goods_id']]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
            }
        }
    }

    return $lnk;
}

/**
 * 获得指定商品的相册
 *
 * @access  public
 * @param   integer $goods_id
 * @return  array
 */
function get_goods_gallery($goods_id)
{
    $sql = 'SELECT img_id, img_url, thumb_url, img_desc' .
        ' FROM ' . $GLOBALS['ecs']->table('goods_gallery') .
        " WHERE goods_id = '$goods_id'  ORDER BY img_desc ASC LIMIT " . $GLOBALS['_CFG']['goods_gallery_number'];
    $row = $GLOBALS['db']->getAll($sql);
    /* 格式化相册图片路径 */
    foreach ($row as $key => $gallery_img) {
        $row[$key]['img_url'] = get_image_path($gallery_img['img_url']);
        $row[$key]['thumb_url'] = get_image_path($gallery_img['thumb_url']);
    }
    return $row;
}

/**
 * 获得指定分类下的商品
 *
 * @access  public
 * @param   integer $cat_id 分类ID
 * @param   integer $num 数量
 * @param   string $from 来自web/wap的调用
 * @param   string $order_rule 指定商品排序规则
 * @return  array
 */
function assign_cat_goods($cat_id, $num = 0, $from = 'web', $order_rule = '', $return = 'cat', $warehouse_id = 0, $area_id = 0, $floor_sort_order = 0) //这里增加了一个参数  $return， 下面有用到这个参数， zhangyh_100322
{

    //ecmoban模板堂 --zhuo start
    //$children = get_children($cat_id);
    $children = get_category_parentChild_tree1($cat_id, 1);
    $children = arr_foreach($children);

    if ($children) {
        $children = implode(",", $children) . "," . $cat_id;
        $children = get_children($children, 0, 1);
    } else {
        $children = "g.cat_id IN ($cat_id)";
    }
    //ecmoban模板堂 --zhuo end

    //ecmoban模板堂 --zhuo start
    $leftJoin = '';
    $tag_where = '';

    if ($GLOBALS['_CFG']['open_area_goods'] == 1) { //关联地区显示商品
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
        $tag_where = " and lag.region_id = '$area_id' ";
    }

    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $tag_where .= ' AND g.review_status > 2 ';
    }
    //ecmoban模板堂 --zhuo end

    $sql = 'SELECT g.goods_id, g.goods_name, g.market_price, g.comments_number ,g.sales_volume, ' .
        ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
        "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, " .
        "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
        ' g.is_promote, g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb, g.goods_img ' .
        "FROM " . $GLOBALS['ecs']->table('goods') . ' AS g ' .
        $leftJoin .
        "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        'WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND ' .
        'g.is_delete = 0 AND (' . $children . 'OR ' . get_extension_goods($children) . ') ' . $tag_where;

    $order_rule = empty($order_rule) ? 'ORDER BY g.sort_order, g.goods_id DESC' : $order_rule;
    $sql .= $order_rule;
    if ($num > 0) {
        $sql .= ' LIMIT ' . $num;
    }
    $res = $GLOBALS['db']->getAll($sql);

    $goods = array();
    foreach ($res AS $idx => $row) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            $goods[$idx]['promote_price'] = $promote_price > 0 ? price_format($promote_price) : '';
        } else {
            $goods[$idx]['promote_price'] = '';
        }

        $goods_res[$idx]['is_promote'] = $row['is_promote'];

        $goods[$idx]['id'] = $row['goods_id'];
        $goods[$idx]['name'] = $row['goods_name'];
        $goods[$idx]['brief'] = $row['goods_brief'];
        // 折扣节省计算 by ecmoban start
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $goods[$idx]['zhekou'] = $discount_arr['discount'];  //zhekou
        $goods[$idx]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        // 折扣节省计算 by ecmoban end
        $goods[$idx]['comments_number'] = $row['comments_number'];
        $goods[$idx]['sales_volume'] = $row['sales_volume'];
        $goods[$idx]['market_price'] = price_format($row['market_price']);
        $goods[$idx]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
            sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        $goods[$idx]['shop_price'] = price_format($row['shop_price']);
        $goods[$idx]['thumb'] = get_image_path($row['goods_thumb']);
        $goods[$idx]['goods_img'] = get_image_path($row['goods_img']);
        $goods[$idx]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
    }

    if ($from == 'web') {
        $goods['id'] = $cat_id;
        $GLOBALS['smarty']->assign('cat_goods_' . $cat_id, $goods);
    } elseif ($from == 'wap') {
        $cat['goods'] = $goods;
    }

    /* 分类信息 */
    $sql = 'SELECT cat_name FROM ' . $GLOBALS['ecs']->table('category') . " WHERE cat_id = '$cat_id'";
    $cat['name'] = $GLOBALS['db']->getOne($sql);
    $cat['url'] = build_uri('category', array('cid' => $cat_id), $cat['name']);
    $cat['id'] = $cat_id;


    //获取二级分类下的商品
    $cat_list_arr = cat_list($cat_id, 0);
    $goods_index_cat1 = get_cat_goods_index_cat1($cat_list_arr);
    $goods_index_cat2 = get_cat_goods_index_cat2($goods_index_cat1);

    foreach ($goods_index_cat2 as $key => $value) {
        if ($value['level'] == 1) {
            $sql = 'SELECT g.goods_id,g.cat_id, g.goods_name, g.market_price, ' .
                ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
                "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, " .
                "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
                ' g.is_promote, g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb, g.goods_img ' .
                'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
                $leftJoin .
                'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
                "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
                'WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND is_delete = 0 AND ' . get_children($value['cat_id']) . $tag_where . ' ORDER BY g.sort_order, g.goods_id DESC';

            if ($num > 0) {
                $sql .= ' LIMIT ' . $num;
            }

            $goods_res = $GLOBALS['db']->getAll($sql);
            foreach ($goods_res as $idx => $row) {
                if ($row['promote_price'] > 0) {
                    $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
                    $goods_res[$idx]['promote_price'] = $promote_price > 0 ? price_format($promote_price) : '';
                } else {
                    $goods_res[$idx]['promote_price'] = '';
                }
                $goods_res[$idx]['is_promote'] = $row['is_promote'];
                $goods_res[$idx]['market_price'] = price_format($row['market_price']);
                $goods_res[$idx]['shop_price'] = price_format($row['shop_price']);
                $goods_res[$idx]['promote_price'] = $goods_res[$idx]['promote_price'];
                $goods_res[$idx]['shop_price'] = price_format($row['shop_price']);
                $goods_res[$idx]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
                $goods_res[$idx]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
            }
            $goods_index_cat2[$key]['goods'] = $goods_res;
        } else {
            unset($goods_index_cat2[$key]);
        }
    }

    $cat['goods_level2'] = $goods_index_cat1;
    $cat['goods_level3'] = $goods_index_cat2;

    $brand_tag_where = '';
    if ($GLOBALS['_CFG']['open_area_goods'] == 1) { //关联地区显示商品
        $brand_leftJoin .= ", " . $GLOBALS['ecs']->table('link_area_goods') . " as lag ";
        $brand_tag_where = " AND g.goods_id = lag.goods_id AND lag.region_id = '$area_id' ";
    }

    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $brand_tag_where .= ' AND g.review_status > 2 ';
    }

    $cat['floor_banner'] = 'floor_banner' . $cat_id;
    $cat['floor_sort_order'] = $floor_sort_order + 1;
    $cat['brands_theme2'] = get_brands_theme2($brands);

    /* zhangyh_100322 end */

    return $cat;
}

function get_cat_goods_index_cat1($cat_list_arr)
{
    foreach ($cat_list_arr as $key => $value) {
        if ($value['level'] != 1) {
            unset($cat_list_arr[$key]);
        } else {
            $cat_list_arr[$key] = $value;
            $cat_list_arr[$key]['child_tree'] = get_child_tree($value['cat_id']);
        }
    }

    $cat_list_arr = array_values($cat_list_arr);
    return $cat_list_arr;
}

function get_cat_goods_index_cat2($cat_list_arr)
{
    foreach ($cat_list_arr as $key => $value) {
        if ($key <= 10) {
            $cat_list_arr[$key] = $value;
        } else {
            unset($cat_list_arr[$key]);
        }
    }

    return $cat_list_arr;
}

function get_brands_theme2($brands)
{

    $arr = array();
    if ($brands) {
        foreach ($brands as $key => $row) {
            if ($key < 8) {
                $arr['one_brands'][$key] = $row;
            } elseif ($key >= 8 && $key <= 14) {
                $arr['two_brands'][$key] = $row;
            } elseif ($key >= 15 && $key <= 21) {
                $arr['three_brands'][$key] = $row;
            } elseif ($key >= 22 && $key <= 28) {
                $arr['foure_brands'][$key] = $row;
            } elseif ($key >= 29 && $key <= 35) {
                $arr['five_brands'][$key] = $row;
            }
        }

        $arr = array_values($arr);
    }

    return $arr;
}


/**
 * 获得指定的品牌下的商品
 *
 * @access  public
 * @param   integer $brand_id 品牌的ID
 * @param   integer $num 数量
 * @param   integer $cat_id 分类编号
 * @param   string $order_rule 指定商品排序规则
 * @return  void
 */
function assign_brand_goods($brand_id, $num = 0, $cat_id = 0, $order_rule = '', $warehouse_id, $area_id)
{
    //ecmoban模板堂 --zhuo start
    $leftJoin = '';
    $tag_where = '';

    if ($GLOBALS['_CFG']['open_area_goods'] == 1) { //关联地区显示商品
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('link_area_goods') . " as lag on g.goods_id = lag.goods_id ";
        $tag_where = " and lag.region_id = '$area_id' ";
    }

    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end

    $sql = 'SELECT g.goods_id, g.goods_name, g.market_price, g.sales_volume,g.comments_number, ' .
        ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
        "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
        "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, " .
        ' g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb, g.goods_img ' .
        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
        $leftJoin .
        "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        "WHERE g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND g.brand_id = '$brand_id'" . $tag_where;

    if ($cat_id > 0) {
        $sql .= get_children($cat_id);
    }

    $order_rule = empty($order_rule) ? ' ORDER BY g.sort_order, g.goods_id DESC' : $order_rule;
    $sql .= $order_rule;
    if ($num > 0) {
        $res = $GLOBALS['db']->selectLimit($sql, $num);
    } else {
        $res = $GLOBALS['db']->query($sql);
    }

    $idx = 0;
    $goods = array();
    foreach ($res as $row) {
        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
            $promote_price = 0;
        }

        $goods[$idx]['id'] = $row['goods_id'];
        $goods[$idx]['name'] = $row['goods_name'];
        /* 折扣节省计算 by ecmoban start */
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $goods[$idx]['zhekou'] = $discount_arr['discount'];  //zhekou
        $goods[$idx]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
        $goods[$idx]['comments_number'] = $row['comments_number'];
        $goods[$idx]['sales_volume'] = $row['sales_volume'];
        $goods[$idx]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
            sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        $goods[$idx]['market_price'] = price_format($row['market_price']);
        $goods[$idx]['shop_price'] = price_format($row['shop_price']);
        $goods[$idx]['promote_price'] = $promote_price > 0 ? price_format($promote_price) : '';
        $goods[$idx]['brief'] = $row['goods_brief'];
        $goods[$idx]['thumb'] = get_image_path($row['goods_thumb']);
        $goods[$idx]['goods_img'] = get_image_path($row['goods_img']);
        $goods[$idx]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);

        $idx++;
    }

    /* 分类信息 */
    $sql = 'SELECT brand_name FROM ' . $GLOBALS['ecs']->table('brand') . " WHERE brand_id = '$brand_id'";

    $brand['id'] = $brand_id;
    $brand['name'] = $GLOBALS['db']->getOne($sql);
    $brand['url'] = build_uri('brand', array('bid' => $brand_id), $brand['name']);

    $brand_goods = array('brand' => $brand, 'goods' => $goods);

    return $brand_goods;
}

/**
 * 获得所有扩展分类属于指定分类的所有商品ID
 *
 * @access  public
 * @param   string $cat_id 分类查询字符串
 * @return  string
 */
function get_extension_goods($cats)
{
    $extension_goods_array = '';
    $sql = 'SELECT goods_id FROM ' . $GLOBALS['ecs']->table('goods_cat') . " AS g WHERE $cats";
    $extension_goods_array = $GLOBALS['db']->getCol($sql);
    return db_create_in($extension_goods_array, 'g.goods_id');
}

/**
 * 判断某个商品是否正在特价促销期
 *
 * @access  public
 * @param   float $price 促销价格
 * @param   string $start 促销开始日期
 * @param   string $end 促销结束日期
 * @return  float   如果还在促销期则返回促销价，否则返回0
 */
function bargain_price($price, $start, $end)
{
    if ($price == 0) {
        return 0;
    } else {
        $time = gmtime();
        if ($time >= $start && $time <= $end) {
            return $price;
        } else {
            return 0;
        }
    }
}

/**
 * 获得指定的规格的价格
 *
 * @access  public
 * @param   mix $spec 规格ID的数组或者逗号分隔的字符串
 * @return  void
 */
function spec_price($spec, $goods_id = 0, $warehouse_area = array())
{
    if (!empty($spec)) {

        if (is_array($spec)) {
            foreach ($spec as $key => $val) {
                $spec[$key] = addslashes($val);
            }
        } else {
            $spec = addslashes($spec);
        }

        $warehouse_id = $warehouse_area['warehouse_id'];
        $area_id = $warehouse_area['area_id'];
        $model_attr = get_table_date("goods", "goods_id = '$goods_id'", array('model_attr'), 2);
        $attr['price'] = 0;

        if ($GLOBALS['_CFG']['goods_attr_price'] == 1) {
            $spec = implode("|", $spec);
            $where = "goods_id = '$goods_id'";
            if ($model_attr == 1) { //仓库属性
                $table = "products_warehouse";
                $where .= " AND warehouse_id = '$warehouse_id' AND goods_attr = '$spec'";
            } elseif ($model_attr == 2) { //地区属性
                $table = "products_area";
                $area_id = $warehouse_area['area_id'];
                $where .= " AND area_id = '$area_id' AND goods_attr = '$spec'";
            } else {
                $table = "products";
                $where .= " AND goods_attr = '$spec'";
            }

            $sql = 'SELECT product_price FROM ' . $GLOBALS['ecs']->table($table) . " WHERE $where";
            $price = $GLOBALS['db']->getOne($sql);
        } else {
            $where = db_create_in($spec, 'goods_attr_id');

            if ($model_attr == 1) { //仓库属性
                $sql = "select SUM(attr_price) from " . $GLOBALS['ecs']->table('warehouse_attr') . " where goods_id = '$goods_id' and warehouse_id = '$warehouse_id' and " . $where;
            } elseif ($model_attr == 2) { //地区属性
                $sql = "select SUM(attr_price)from " . $GLOBALS['ecs']->table('warehouse_area_attr') . " where goods_id = '$goods_id' and area_id = '$area_id' and " . $where;
            } else {
                $sql = 'SELECT SUM(attr_price) AS attr_price FROM ' . $GLOBALS['ecs']->table('goods_attr') . " WHERE $where";
            }

            $price = $GLOBALS['db']->getOne($sql, true);
        }
    } else {
        $price = 0;
    }


    return floatval($price);
}

/**
 * 取得团购活动信息
 * @param   int $group_buy_id 团购活动id
 * @param   int $current_num 本次购买数量（计算当前价时要加上的数量）
 * @return  array
 *                  status          状态：
 */
function group_buy_info($group_buy_id, $current_num = 0)
{
    /* 取得团购活动信息 */
    $group_buy_id = intval($group_buy_id);
    $sql = "SELECT b.*,g.*, b.act_id AS group_buy_id, b.act_desc AS group_buy_desc, b.start_time AS start_date, b.end_time AS end_date " .
        "FROM " . $GLOBALS['ecs']->table('goods_activity') . " AS b " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON b.goods_id = g.goods_id " .
        "WHERE act_id = '$group_buy_id' " .
        "AND act_type = '" . GAT_GROUP_BUY . "'" .
        "AND b.review_status = 3";

    //dqy add end 2011-8-24
    $group_buy = $GLOBALS['db']->getRow($sql);

    /* 如果为空，返回空数组 */
    if (empty($group_buy)) {
        return array();
    }

    $ext_info = unserialize($group_buy['ext_info']);
    $group_buy = array_merge($group_buy, $ext_info);

    /* 格式化时间 */
    $group_buy['formated_start_date'] = local_date('Y-m-d H:i', $group_buy['start_time']);
    $group_buy['formated_end_date'] = local_date('Y-m-d H:i', $group_buy['end_time']);

    $now = gmtime();
    $group_buy['is_end'] = $now > $group_buy['end_time'] ? 1 : 0;

    $group_buy['xiangou_start_date'] = $group_buy['start_time'];
    $group_buy['xiangou_end_date'] = $group_buy['end_time'];
    /* 格式化保证金 */
    $group_buy['formated_deposit'] = price_format($group_buy['deposit'], false);

    /* 处理价格阶梯 */
    $price_ladder = $group_buy['price_ladder'];
    if (!is_array($price_ladder) || empty($price_ladder)) {
        $price_ladder = array(array('amount' => 0, 'price' => 0));
    } else {
        foreach ($price_ladder as $key => $amount_price) {
            $price_ladder[$key]['formated_price'] = price_format($amount_price['price'], false);
        }
    }
    $group_buy['price_ladder'] = $price_ladder;

    /* 统计信息 */
    $stat = group_buy_stat($group_buy_id, $group_buy['deposit']);
    $group_buy = array_merge($group_buy, $stat);

    /* 计算当前价 */
    $cur_price = $price_ladder[0]['price']; // 初始化
    $cur_amount = $stat['valid_goods'] + $current_num; // 当前数量
    foreach ($price_ladder as $amount_price) {
        if ($cur_amount >= $amount_price['amount']) {
            $cur_price = $amount_price['price'];
        } else {
            break;
        }
    }

    //yyy start
    $group_buy['goods_desc'] = $GLOBALS['db']->getOne("select goods_desc from " . $GLOBALS['ecs']->table('goods') . " where goods_id = '" . $group_buy['goods_id'] . "'");
    //yyy end

    $group_buy['cur_price'] = $cur_price;
    $group_buy['formated_cur_price'] = price_format($cur_price, false);

    /*团购节省和折扣计算 by ecmoban start*/
    $price = $group_buy['market_price']; //原价
    $nowprice = $group_buy['cur_price']; //现价
    $group_buy['jiesheng'] = $price - $nowprice; //节省金额
    if ($nowprice > 0 && $price > 0) {
        $group_buy['zhekou'] = round(10 / ($price / $nowprice), 1);
    } else {
        $group_buy['zhekou'] = 0;
    }
    /*团购节省和折扣计算 by ecmoban end*/

    /* 最终价 */
    $group_buy['trans_price'] = $group_buy['cur_price'];
    $group_buy['formated_trans_price'] = $group_buy['formated_cur_price'];
    $group_buy['trans_amount'] = $group_buy['valid_goods'];

    /* 状态 */
    $group_buy['status'] = group_buy_status($group_buy);
    if (isset($GLOBALS['_LANG']['gbs'][$group_buy['status']])) {
        $group_buy['status_desc'] = $GLOBALS['_LANG']['gbs'][$group_buy['status']];
    }

    $group_buy['start_time'] = $group_buy['formated_start_date'];
    $group_buy['end_time'] = $group_buy['formated_end_date'];

    $group_buy['rz_shopName'] = get_shop_name($group_buy['user_id'], 1); //店铺名称
    $group_buy['store_url'] = build_uri('merchants_store', array('urid' => $group_buy['user_id']), $group_buy['rz_shopName']);

    $group_buy['shopinfo'] = get_shop_name($group_buy['user_id'], 2);
    $group_buy['shopinfo']['brand_thumb'] = str_replace(array('../'), '', $group_buy['shopinfo']['brand_thumb']);

    //买家印象
    if ($group_buy['goods_product_tag']) {
        $impression_list = !empty($group_buy['goods_product_tag']) ? explode(',', $group_buy['goods_product_tag']) : '';
        foreach ($impression_list as $kk => $vv) {
            $tag[$kk]['txt'] = $vv;
            //印象数量
            $tag[$kk]['num'] = comment_goodstag_num($group_buy['goods_id'], $vv);
        }
        $group_buy['impression_list'] = $tag;
    }
    $group_buy['collect_count'] = get_collect_goods_user_count($group_buy['goods_id']);


    return $group_buy;
}

/*
 * 取得某团购活动统计信息
 * @param   int     $group_buy_id   团购活动id
 * @param   float   $deposit        保证金
 * @return  array   统计信息
 *                  total_order     总订单数
 *                  total_goods     总商品数
 *                  valid_order     有效订单数
 *                  valid_goods     有效商品数
 */
function group_buy_stat($group_buy_id, $deposit)
{
    $group_buy_id = intval($group_buy_id);

    /* 取得团购活动商品ID */
    $sql = "SELECT goods_id " .
        "FROM " . $GLOBALS['ecs']->table('goods_activity') .
        "WHERE act_id = '$group_buy_id' " .
        "AND act_type = '" . GAT_GROUP_BUY . "'" .
        "AND review_status = 3 ";
    $group_buy_goods_id = $GLOBALS['db']->getOne($sql);

    /* 取得总订单数和总商品数 */
    $sql = "SELECT COUNT(*) AS total_order, SUM(g.goods_number) AS total_goods " .
        "FROM " . $GLOBALS['ecs']->table('order_info') . " AS o, " .
        $GLOBALS['ecs']->table('order_goods') . " AS g " .
        " WHERE o.order_id = g.order_id " .
        "AND o.extension_code = 'group_buy' " .
        "AND o.extension_id = '$group_buy_id' " .
        "AND g.goods_id = '$group_buy_goods_id' " .
        "AND (order_status = '" . OS_CONFIRMED . "' OR order_status = '" . OS_UNCONFIRMED . "')";
    $stat = $GLOBALS['db']->getRow($sql);
    if ($stat['total_order'] == 0) {
        $stat['total_goods'] = 0;
    }

    /* 取得有效订单数和有效商品数 */
    $deposit = floatval($deposit);
    if ($deposit > 0 && $stat['total_order'] > 0) {
        $sql .= " AND (o.money_paid + o.surplus) >= '$deposit'";
        $row = $GLOBALS['db']->getRow($sql);
        $stat['valid_order'] = $row['total_order'];
        if ($stat['valid_order'] == 0) {
            $stat['valid_goods'] = 0;
        } else {
            $stat['valid_goods'] = $row['total_goods'];
        }
    } else {
        $stat['valid_order'] = $stat['total_order'];
        $stat['valid_goods'] = $stat['total_goods'];
    }

    return $stat;
}

/**
 * 获得团购的状态
 *
 * @access  public
 * @param   array
 * @return  integer
 */
function group_buy_status($group_buy)
{
    $now = gmtime();
    if ($group_buy['is_finished'] == 0) {
        /* 未处理 */
        if ($now < $group_buy['start_time']) {
            $status = GBS_PRE_START;
        } elseif ($now > $group_buy['end_time']) {
            $status = GBS_FINISHED;
        } else {
            if ($group_buy['restrict_amount'] == 0 || $group_buy['valid_goods'] < $group_buy['restrict_amount']) {
                $status = GBS_UNDER_WAY;
            } else {
                $status = GBS_FINISHED;
            }
        }
    } elseif ($group_buy['is_finished'] == GBS_SUCCEED) {
        /* 已处理，团购成功 */
        $status = GBS_SUCCEED;
    } elseif ($group_buy['is_finished'] == GBS_FAIL) {
        /* 已处理，团购失败 */
        $status = GBS_FAIL;
    }

    return $status;
}

/**
 * 取得拍卖活动信息
 * @param   int $act_id 活动id
 * @return  array
 */
function auction_info($act_id, $config = false)
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('goods_activity') . " WHERE act_id = '$act_id' AND review_status = 3";
    $auction = $GLOBALS['db']->getRow($sql);

    $auction['endTime'] = $auction['end_time'];
    if ($auction['act_type'] != GAT_AUCTION) {
        return array();
    }
    $auction['status_no'] = auction_status($auction);
    if ($config == true) {

        $auction['start_time'] = local_date('Y-m-d H:i', $auction['start_time']);
        $auction['end_time'] = local_date('Y-m-d H:i', $auction['end_time']);
    } else {
        $auction['start_time'] = local_date($GLOBALS['_CFG']['time_format'], $auction['start_time']);
        $auction['end_time'] = local_date($GLOBALS['_CFG']['time_format'], $auction['end_time']);
    }
    $ext_info = unserialize($auction['ext_info']);
    $auction = array_merge($auction, $ext_info);
    $auction['formated_start_price'] = price_format($auction['start_price']);
    $auction['formated_end_price'] = price_format($auction['end_price']);
    $auction['formated_amplitude'] = price_format($auction['amplitude']);
    $auction['formated_deposit'] = price_format($auction['deposit']);

    /* 查询出价用户数和最后出价 */
    $sql = "SELECT COUNT(DISTINCT bid_user) FROM " . $GLOBALS['ecs']->table('auction_log') .
        " WHERE act_id = '$act_id'";
    $auction['bid_user_count'] = $GLOBALS['db']->getOne($sql);
    if ($auction['bid_user_count'] > 0) {
        $sql = "SELECT a.*, u.user_name " .
            "FROM " . $GLOBALS['ecs']->table('auction_log') . " AS a, " .
            $GLOBALS['ecs']->table('users') . " AS u " .
            "WHERE a.bid_user = u.user_id " .
            "AND act_id = '$act_id' " .
            "ORDER BY a.log_id DESC";
        $row = $GLOBALS['db']->getRow($sql);
        $row['formated_bid_price'] = price_format($row['bid_price'], false);
        $row['bid_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['bid_time']);
        $auction['last_bid'] = $row;
    } else {
        $auction['bid_user_count'] = 0;
    }

    /* 查询已确认订单数 */
    if ($auction['status_no'] > 1) {
        $sql = "SELECT COUNT(*)" .
            " FROM " . $GLOBALS['ecs']->table('order_info') .
            " WHERE extension_code = 'auction'" .
            " AND extension_id = '$act_id'" .
            " AND order_status " . db_create_in(array(OS_CONFIRMED, OS_UNCONFIRMED));
        $auction['order_count'] = $GLOBALS['db']->getOne($sql);
    } else {
        $auction['order_count'] = 0;
    }

    /* 当前价 */
    $auction['current_price'] = isset($auction['last_bid']) ? $auction['last_bid']['bid_price'] : $auction['start_price'];
    $auction['current_price_int'] = intval($auction['current_price']);
    $auction['formated_current_price'] = price_format($auction['current_price'], false);

    return $auction;
}

/**
 * 取得拍卖活动出价记录
 * @param   int $act_id 活动id
 * @return  array
 */
function auction_log($act_id, $type = 0)
{
    if ($type == 1) {
        $sql = "SELECT count(*) ,u.user_id " .
            "FROM " . $GLOBALS['ecs']->table('auction_log') . " AS a," .
            $GLOBALS['ecs']->table('users') . " AS u " .
            "WHERE a.bid_user = u.user_id " .
            "AND act_id = '$act_id' ";
        $log = $GLOBALS['db']->getOne($sql);
    } else {
        $log = array();
        $sql = "SELECT a.*, u.user_name ,u.user_id " .
            "FROM " . $GLOBALS['ecs']->table('auction_log') . " AS a," .
            $GLOBALS['ecs']->table('users') . " AS u " .
            "WHERE a.bid_user = u.user_id " .
            "AND act_id = '$act_id' " .
            "ORDER BY a.log_id DESC";
        $res = $GLOBALS['db']->query($sql);
        foreach ($res as $row) {
            $row['bid_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['bid_time']);
            $row['formated_bid_price'] = price_format($row['bid_price'], false);
            $log[] = $row;
        }
    }

    return $log;
}

/**
 * 计算拍卖活动状态（注意参数一定是原始信息）
 * @param   array $auction 拍卖活动原始信息
 * @return  int
 */
function auction_status($auction)
{
    $now = gmtime();
    if ($auction['is_finished'] == 0) {
        if ($now < $auction['start_time']) {
            return PRE_START; // 未开始
        } elseif ($now > $auction['end_time']) {
            return FINISHED; // 已结束，未处理
        } else {
            return UNDER_WAY; // 进行中
        }
    } elseif ($auction['is_finished'] == 1) {
        return FINISHED; // 已结束，未处理
    } else {
        return SETTLED; // 已结束，已处理
    }
}

/**
 * 取得商品信息
 * @param   int $goods_id 商品id
 * @return  array
 */
function goods_info($goods_id, $warehouse_id, $area_id, $select = array(), $attr_id = '')
{
    $leftJoin = '';
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    $sql = "SELECT g.*, b.brand_name, " .
        "IF(g.model_inventory < 1, g.goods_number, IF(g.model_inventory < 2, wg.region_number, wag.region_number)) as goods_number " .
        "FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('brand') . " AS b ON g.brand_id = b.brand_id " .
        $leftJoin .
        "WHERE g.goods_id = '$goods_id'";
    $row = $GLOBALS['db']->getRow($sql);
    if (!empty($row)) {
        if($GLOBALS['_CFG']['add_shop_price'] == 1){
            $add_tocart = 1;
        }else{
            $add_tocart = 0;
        }

        $row['goods_price'] = get_final_price($row['goods_id'], $row['goods_number'], true, $attr_id, $warehouse_id, $area_id, 0, 0, $add_tocart);

        /* 修正重量显示 */
        $row['goods_weight'] = (intval($row['goods_weight']) > 0) ?
            $row['goods_weight'] . $GLOBALS['_LANG']['kilogram'] :
            ($row['goods_weight'] * 1000) . $GLOBALS['_LANG']['gram'];

        /* 修正图片 */
        $row['goods_img'] = get_image_path($row['goods_img']);
        //OSS文件存储ecmoban模板堂 --zhuo start
        if ($GLOBALS['_CFG']['open_oss'] == 1) {
            $bucket_info = get_bucket_info();
            if ($row['goods_desc']) {
                $desc_preg = get_goods_desc_images_preg($bucket_info['endpoint'], $row['goods_desc']);
                $row['goods_desc'] = $desc_preg['goods_desc'];
            }
        }

        //ecmoban模板堂 --zhuo
        $row['rz_shopName'] = get_shop_name($row['user_id'], 1); //店铺名称
//        $row['store_url'] = build_uri('merchants_store', array('urid'=>$row['user_id']), $row['rz_shopName']);
        $row['store_url'] = url('store/index/shop_info', array('id' => $row['user_id']));
        $row['shopinfo'] = get_shop_name($row['user_id'], 2);
        //处理图片
        $row['shopinfo']['brand_thumb'] = str_replace(array('../'), '', $row['shopinfo']['brand_thumb']);
        $row['shopinfo']['logo_thumb'] = get_image_path(str_replace(array('../'), '', $row['shopinfo']['logo_thumb']));
        $row['shopinfo']['shop_logo'] = get_image_path(str_replace(array('../'), '', $row['shopinfo']['shop_logo']));
        $basic_info = get_seller_shopinfo($row['user_id']);

        $row['province'] = $basic_info['province'];
        $row['city'] = $basic_info['city'];
        if ($basic_info['kf_qq']) {
            $kf_qq = array_filter(preg_split('/\s+/', $basic_info['kf_qq']));
            $kf_qq = explode("|", $kf_qq[0]);
            $row['kf_qq'] = $kf_qq[1];
        } else {
            $row['kf_qq'] = "";
        }
        /*处理客服旺旺数组 by kong*/
        if ($basic_info['kf_ww']) {
            $kf_ww = array_filter(preg_split('/\s+/', $basic_info['kf_ww']));
            $kf_ww = explode("|", $kf_ww[0]);
            $row['kf_ww'] = $kf_ww[1];
        } else {
            $row['kf_ww'] = "";
        }
        $row['kf_type'] = $basic_info['kf_type'];
        $row['shop_name'] = $basic_info['shop_name'];
        //买家印象
        if ($row['goods_product_tag']) {
            $impression_list = !empty($row['goods_product_tag']) ? explode(',', $row['goods_product_tag']) : '';
            foreach ($impression_list as $kk => $vv) {
                $tag[$kk]['txt'] = $vv;
                //印象数量
                $tag[$kk]['num'] = comment_goodstag_num($row['goods_id'], $vv);
            }
            $row['impression_list'] = $tag;
        }
    }

    return $row;
}

/**
 * 取得优惠活动信息
 * @param   int $act_id 活动id
 * @return  array
 */
function favourable_info($act_id)
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('favourable_activity') .
        " WHERE act_id = '$act_id' AND review_status = 3";
    $row = $GLOBALS['db']->getRow($sql);
    if (!empty($row)) {
        $row['start_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['start_time']);
        $row['end_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['end_time']);
        $row['formated_min_amount'] = price_format($row['min_amount']);
        $row['formated_max_amount'] = price_format($row['max_amount']);
        $row['gift'] = unserialize($row['gift']);
        if ($row['act_type'] == FAT_GOODS) {
            $row['act_type_ext'] = round($row['act_type_ext']);
        }
    }

    return $row;
}

/**
 * 批发信息
 * @param   int $act_id 活动id
 * @return  array
 */
function wholesale_info($act_id)
{
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('wholesale') .
        " WHERE act_id = '$act_id'";
    $row = $GLOBALS['db']->getRow($sql);
    if (!empty($row)) {
        $row['price_list'] = unserialize($row['prices']);
    }

    return $row;
}

/**
 * 添加商品名样式
 * @param   string $goods_name 商品名称
 * @param   string $style 样式参数
 * @return  string
 */
function add_style($goods_name, $style)
{
    $goods_style_name = $goods_name;

    $arr = explode('+', $style);

    $font_color = !empty($arr[0]) ? $arr[0] : '';
    $font_style = !empty($arr[1]) ? $arr[1] : '';

    if ($font_color != '') {
        $goods_style_name = '<font style="color:' . $font_color . '; font-size:inherit;">' . $goods_style_name . '</font>';
    }
    if ($font_style != '') {
        $goods_style_name = '<' . $font_style . '>' . $goods_style_name . '</' . $font_style . '>';
    }
    return $goods_style_name;
}

/**
 * 取得商品属性
 * @param   int $goods_id 商品id
 * @return  array
 */
function get_goods_attr($goods_id)
{
    $attr_list = array();
    $sql = "SELECT a.attr_id, a.attr_name " .
        "FROM " . $GLOBALS['ecs']->table('goods') . " AS g, " . $GLOBALS['ecs']->table('attribute') . " AS a " .
        "WHERE g.goods_id = '$goods_id' " .
        "AND g.goods_type = a.cat_id " .
        "AND a.attr_type = 1";
    $attr_id_list = $GLOBALS['db']->getCol($sql);
    $res = $GLOBALS['db']->query($sql);
    foreach ($res as $attr) {
        if (defined('ECS_ADMIN')) {
            $attr['goods_attr_list'] = array(0 => $GLOBALS['_LANG']['select_please']);
        } else {
            $attr['goods_attr_list'] = array();
        }
        $attr_list[$attr['attr_id']] = $attr;
    }

    $sql = "SELECT attr_id, goods_attr_id, attr_value " .
        "FROM " . $GLOBALS['ecs']->table('goods_attr') .
        " WHERE goods_id = '$goods_id' " .
        "AND attr_id " . db_create_in($attr_id_list);
    $res = $GLOBALS['db']->query($sql);
    foreach ($res as $goods_attr) {
        $attr_list[$goods_attr['attr_id']]['goods_attr_list'][$goods_attr['goods_attr_id']] = $goods_attr['attr_value'];
    }

    return $attr_list;
}

/**
 * 获得购物车中商品的配件
 *
 * @access  public
 * @param   array $goods_list
 * @return  array
 */
function get_goods_fittings($goods_list = array(), $warehouse_id = 0, $area_id = 0, $rev = '', $type = 0, $goods_equal = array())
{
    $fitts_goodsList = '';
    if (count($goods_equal) > 0) {
        $fitts_goodsList = implode(',', $goods_equal);
        $fitts_goodsList = " and cc.goods_id in($fitts_goodsList) ";
    }

    if (!empty($_SESSION['user_id'])) {
        $sess_id = " cc.user_id = '" . $_SESSION['user_id'] . "' ";
        $sess = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " cc.session_id = '" . real_cart_mac_ip() . "' ";
        $sess = " session_id = '" . real_cart_mac_ip() . "' ";
    }

    $temp_index = 1;
    $arr = array();

    //ecmoban模板堂 --zhuo start
    $where = '';
    $goods_attr_id = '';
    $leftJoin = '';
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    if ($type == 1) {
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('cart_combo') . " as cc on g.goods_id = cc.goods_id ";
        $where .= " and cc.group_id = '$rev' and " . $sess_id;
        $goods_attr_id = " cc.goods_attr_id, cc.group_id as cc_group_id, ";
    } elseif ($type == 2) {
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('cart_combo') . " as cc on g.goods_id = cc.goods_id and " . $sess_id;
        $where .= " and gg.group_id = '$rev'";
    }
    //ecmoban模板堂 --zhuo end

    $sql = 'SELECT gg.parent_id, ggg.goods_name AS parent_name, gg.group_id, gg.goods_id, gg.goods_price, g.comments_number,g.sales_volume,g.goods_name, g.goods_thumb, g.goods_img, g.market_price, ' .
        $goods_attr_id .
        " IF(g.model_inventory < 1, g.goods_number, IF(g.model_inventory < 2, wg.region_number, wag.region_number)) as goods_number," .
        'IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
        //"IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
        "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price))) AS shop_price, " .
        "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, g.goods_type " .

        'FROM ' . $GLOBALS['ecs']->table('group_goods') . ' AS gg ' .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('goods') . 'AS g ON g.goods_id = gg.goods_id ' .
        "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
        "ON mp.goods_id = gg.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS ggg ON ggg.goods_id = gg.parent_id " .

        $leftJoin .

        "WHERE gg.parent_id " . db_create_in($goods_list) . " AND g.is_delete = 0 AND g.is_on_sale = 1 " . $where . $fitts_goodsList .
        "GROUP BY gg.goods_id ORDER BY gg.parent_id, gg.goods_id";

    $res = $GLOBALS['db']->query($sql);
    foreach ($res as $row) {
        $arr[$temp_index]['parent_id'] = $row['parent_id'];//配件的基本件ID
        /* 折扣节省计算 by ecmoban start */
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $arr[$temp_index]['zhekou'] = $discount_arr['discount'];  //zhekou
        $arr[$temp_index]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
        $arr[$temp_index]['parent_name'] = $row['parent_name'];//配件的基本件的名称
        $arr[$temp_index]['parent_short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
            sub_str($row['parent_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['parent_name'];//配件的基本件显示的名称
        $arr[$temp_index]['goods_id'] = $row['goods_id'];//配件的商品ID
        $arr[$temp_index]['goods_name'] = $row['goods_name'];//配件的名称
        $arr[$temp_index]['comments_number'] = $row['comments_number'];
        $arr[$temp_index]['sales_volume'] = $row['sales_volume'];
        $arr[$temp_index]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
            sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];//配件显示的名称
        $arr[$temp_index]['fittings_price'] = price_format($row['goods_price']);//配件价格
        $arr[$temp_index]['shop_price'] = price_format($row['shop_price']);//配件原价格
        $arr[$temp_index]['spare_price'] = price_format($row['shop_price'] - $row['goods_price']);//节省的差价 by mike

        $arr[$temp_index]['market_price'] = $row['market_price'];

        $minMax_price = get_goods_minMax_price($row['goods_id'], $warehouse_id, $area_id, $row['goods_price'], $row['market_price']); //配件价格min与max
        $arr[$temp_index]['fittings_minPrice'] = $minMax_price['goods_min'];
        $arr[$temp_index]['fittings_maxPrice'] = $minMax_price['goods_max'];

        $arr[$temp_index]['market_minPrice'] = $minMax_price['market_min'];
        $arr[$temp_index]['market_maxPrice'] = $minMax_price['market_max'];

        if (!empty($row['goods_attr_id'])) {
            $prod_attr = explode(',', $row['goods_attr_id']);
        } else {
            $prod_attr = array();
        }

        $warehouse_area = array(
            'warehouse_id' => $warehouse_id,
            'area_id' => $area_id,
        );
        $attr_price = spec_price($prod_attr, $row['goods_id'], $warehouse_area);
        $arr[$temp_index]['attr_price'] = $attr_price;

        $arr[$temp_index]['shop_price_ori'] = $row['shop_price'];//配件原价格 by mike
        $arr[$temp_index]['fittings_price_ori'] = $row['goods_price'];//配件价格 by mike
        $arr[$temp_index]['spare_price_ori'] = ($row['shop_price'] - $row['goods_price']);//节省的差价 by mike
        $arr[$temp_index]['group_id'] = $row['group_id'];//套餐组 by mike

        if ($type == 2) {
            $cc_rev = "m_goods_" . $rev . "_" . $row['parent_id'];
            $sql = "select cc.img_flie from " . $GLOBALS['ecs']->table('cart_combo') . " as cc" . " where cc.goods_id = '" . $row['goods_id'] . "'" .
                " AND cc.group_id = '$cc_rev' and " . $sess_id;
        } else {
            $sql = "select cc.img_flie from " . $GLOBALS['ecs']->table('cart_combo') . " as cc" . " where cc.goods_id = '" . $row['goods_id'] . "'" .
                " AND cc.group_id = '$rev' and " . $sess_id;
        }

        $img_flie = $GLOBALS['db']->getOne($sql);
        $arr[$temp_index]['img_flie'] = $img_flie;

        if (!empty($arr[$temp_index]['img_flie'])) {
            $arr[$temp_index]['goods_thumb'] = $arr[$temp_index]['img_flie'];
        } else {
            $arr[$temp_index]['goods_thumb'] = get_image_path($row['goods_thumb']);
        }

        $arr[$temp_index]['goods_img'] = get_image_path($row['goods_img']);
        $arr[$temp_index]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        $arr[$temp_index]['attr_id'] = !empty($row['goods_attr_id']) ? str_replace(',', '|', $row['goods_attr_id']) : "";
        $arr[$temp_index]['goods_number'] = get_goods_fittings_gnumber($row['goods_number'], $row['goods_id'], $warehouse_id, $area_id);

        //求组合购买商品已选择属性的库存量 start
        if (empty($row['goods_attr_id'])) {
            $arr[$temp_index]['goods_number'] = get_goods_fittings_gnumber($row['goods_number'], $row['goods_id'], $warehouse_id, $area_id);
        } else {
            $goods = get_goods_info($row['goods_id'], $warehouse_id, $area_id);
            $products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $row['goods_name'], $warehouse_id, $area_id);
            $attr_number = $products['product_number'];

            $attr_number = !empty($attr_number) ? $attr_number : 0;

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

            $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '" . $row['goods_id'] . "'" . $type_files . " LIMIT 0, 1";

            $prod = $GLOBALS['db']->getRow($sql);

            if (empty($prod)) { //当商品没有属性库存时
                $attr_number = $goods['goods_number'];
            }

            $arr[$temp_index]['goods_number'] = $attr_number;
        }
        //求组合购买商品已选择属性的库存量 end

        $arr[$temp_index]['properties'] = get_goods_properties($row['goods_id'], $warehouse_id, $area_id, $row['goods_attr_id']);

        if ($type == 2) {
            $group_id = "m_goods_" . $rev . "_" . $row['parent_id'];
            $sql = "select rec_id from " . $GLOBALS['ecs']->table('cart_combo') . " where goods_id = '" . $row['goods_id'] . "' and group_id = '$group_id' and " . $sess;
            $rec_id = $GLOBALS['db']->getOne($sql);

            $group_cnt = "m_goods_" . $rev . "=" . $row['parent_id'];
            $arr[$temp_index]['group_top'] = $row['goods_id'] . "|" . $warehouse_id . "|" . $area_id . "|" . $group_cnt;

            if ($rec_id > 0) {
                $arr[$temp_index]['selected'] = 1;
            } else {
                $arr[$temp_index]['selected'] = 0;
            }
        }

        $temp_index++;
    }

    return $arr;
}

/**
 * 获取组合购买里面的单个商品（属性总）库存量
 * @param $goods_number
 * @param $goods_id
 * @param $warehouse_id
 * @param $area_id
 * @return int
 */
function get_goods_fittings_gnumber($goods_number, $goods_id, $warehouse_id, $area_id)
{
    //ecmoban模板堂 --zhuo start
    $leftJoin = '';

    //ecmoban模板堂 --zhuo start
    if ($model_attr == 1) {
        $table_products = "products_warehouse";
        $type_files = " AND warehouse_id = '$warehouse_id'";
    } elseif ($model_attr == 2) {
        $table_products = "products_area";
        $type_files = " AND area_id = '$area_id'";
    } else {
        $table_products = "products";
        $type_files = "";
    }
    //ecmoban模板堂 --zhuo end

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '$goods_id' " . $type_files;
    $res = $GLOBALS['db']->getAll($sql);

    $arr = array();


    if ($res) { //当商品没有属性库存时
        $arr['product_number'] = 0;
        foreach ($res as $key => $row) {
            $arr[$key] = $row;
            $arr['product_number'] += $row['product_number'];
        }
    } else {
        $arr['product_number'] = $goods_number;
    }

    return $arr['product_number'];
}

/**
 * 获得组合购买的的主件商品
 * @param int $goods_id
 * @param int $warehouse_id
 * @param int $area_id
 * @param string $rev
 * @param int $type
 * @return array
 */

function get_goods_fittings_info($goods_id = 0, $warehouse_id = 0, $area_id = 0, $rev = '', $type = 0)
{

    if (!empty($_SESSION['user_id'])) {
        $sess_id = " cc.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " cc.session_id = '" . real_cart_mac_ip() . "' ";
    }

    $temp_index = 0;
    $arr = array();

    //ecmoban模板堂 --zhuo start
    $where = '';
    $select = '';
    $leftJoin = '';
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    if ($type == 0) {
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('cart_combo') . " as cc on g.goods_id = cc.goods_id ";
        $where .= " and cc.group_id = '$rev' and " . $sess_id;
        $select = "cc.goods_attr_id, ";
    }
    //ecmoban模板堂 --zhuo end

    $sql = 'SELECT g.goods_id,g.goods_number,g.sales_volume,g.goods_name, g.goods_thumb, g.goods_img, g.user_id, ' .
        'g.promote_start_date, g.promote_end_date, ' . $select . ' g.market_price, ' .
        " IF(g.model_inventory < 1, g.goods_number, IF(g.model_inventory < 2, wg.region_number, wag.region_number)) as goods_number," .
        'IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
        "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
        "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price))) AS shop_price, " .
        "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, g.goods_type " .

        'FROM ' . $GLOBALS['ecs']->table('goods') . 'AS g ' .
        "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " . "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        $leftJoin .
        "WHERE g.goods_id = '$goods_id' AND g.is_delete = 0 AND g.is_on_sale = 1 " . $where .
        "ORDER BY g.goods_id";

    $res = $GLOBALS['db']->query($sql);
    foreach ($res as $row) {
        $arr[$temp_index]['parent_id'] = $row['parent_id'];//配件的基本件ID
        /* 折扣节省计算 by ecmoban start */
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $arr[$temp_index]['zhekou'] = $discount_arr['discount'];  //zhekou
        $arr[$temp_index]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
        $arr[$temp_index]['parent_name'] = $row['parent_name'];//配件的基本件的名称
        $arr[$temp_index]['parent_short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['parent_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['parent_name'];//配件的基本件显示的名称
        $arr[$temp_index]['goods_id'] = $row['goods_id'];//配件的商品ID
        $arr[$temp_index]['goods_name'] = $row['goods_name'];//配件的名称
        $arr[$temp_index]['comments_number'] = $row['comments_number'];
        $arr[$temp_index]['sales_volume'] = $row['sales_volume'];
        $arr[$temp_index]['short_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];//配件显示的名称
        $arr[$temp_index]['fittings_price'] = price_format($row['goods_price']);//配件价格

        if ($row['promote_price'] > 0) {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        } else {
            $promote_price = 0;
        }

        $goods_price = ($promote_price > 0) ? $promote_price : $row['shop_price'];
        $arr[$temp_index]['market_price'] = $row['market_price'];

        $arr[$temp_index]['shop_price'] = price_format($goods_price);//配件原价格
        $arr[$temp_index]['spare_price'] = price_format(0);//节省的差价 by mike

        $minMax_price = get_goods_minMax_price($row['goods_id'], $warehouse_id, $area_id, $goods_price, $row['market_price']); //配件价格min与max
        $arr[$temp_index]['fittings_minPrice'] = $minMax_price['goods_min'];
        $arr[$temp_index]['fittings_maxPrice'] = $minMax_price['goods_max'];

        $arr[$temp_index]['market_minPrice'] = $minMax_price['market_min'];
        $arr[$temp_index]['market_maxPrice'] = $minMax_price['market_max'];

        if (!empty($row['goods_attr_id'])) {
            $prod_attr = explode(',', $row['goods_attr_id']);
        } else {
            $prod_attr = array();
        }

        $warehouse_area = array(
            'warehouse_id' => $warehouse_id,
            'area_id' => $area_id,
        );
        $attr_price = spec_price($prod_attr, $row['goods_id'], $warehouse_area);
        $arr[$temp_index]['attr_price'] = $attr_price;

        $arr[$temp_index]['shop_price_ori'] = $goods_price;//配件原价格 by mike
        $arr[$temp_index]['fittings_price_ori'] = 0;//配件价格 by mike
        $arr[$temp_index]['spare_price_ori'] = 0;//节省的差价 by mike
        $arr[$temp_index]['group_id'] = $row['group_id'];//套餐组 by mike

        $sql = "select cc.img_flie from " . $GLOBALS['ecs']->table('cart_combo') . " as cc" . " where cc.goods_id = '" . $row['goods_id'] . "'" .
            " AND cc.group_id = '$rev' and " . $sess_id;
        $img_flie = $GLOBALS['db']->getOne($sql);
        $arr[$temp_index]['img_flie'] = $img_flie;

        if (!empty($arr[$temp_index]['img_flie'])) {
            $arr[$temp_index]['goods_thumb'] = $arr[$temp_index]['img_flie'];
        } else {
            $arr[$temp_index]['goods_thumb'] = get_image_path($row['goods_thumb']);
        }

        $arr[$temp_index]['goods_img'] = get_image_path($row['goods_img']);
        $arr[$temp_index]['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        $arr[$temp_index]['attr_id'] = str_replace(',', '|', $row['goods_attr_id']);

        $goods = get_goods_info($goods_id, $warehouse_id, $area_id);
        $products = get_warehouse_id_attr_number($goods_id, $row['goods_attr_id'], $row['goods_name'], $warehouse_id, $area_id);
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
        $arr[$temp_index]['goods_number'] = $attr_number;

        $arr[$temp_index]['properties'] = get_goods_properties($goods_id, $warehouse_id, $area_id, $row['goods_attr_id']);

        $temp_index++;
    }

    //get_print_r($arr);
    return $arr;
}

/**
 * 取指定规格的货品信息
 *
 * @access      public
 * @param       string $goods_id
 * @param       array $spec_goods_attr_id
 * @return      array
 */
function get_products_info($goods_id, $spec_goods_attr_id, $warehouse_id = 0, $area_id = 0, $store_id=0)
{
    //ecmoban模板堂 --zhuo start
    $model_attr = get_table_date("goods", "goods_id = '$goods_id'", array('model_attr'), 2);
    //ecmoban模板堂 --zhuo end

    $return_array = array();

    if (empty($spec_goods_attr_id) || !is_array($spec_goods_attr_id) || empty($goods_id)) {
        return $return_array;
    }

    $goods_attr_array = sort_goods_attr_id_array($spec_goods_attr_id);

    if (isset($goods_attr_array['sort'])) {
        $goods_attr = implode('|', $goods_attr_array['sort']);

        //ecmoban模板堂 --zhuo start
        if($store_id > 0){
            /*门店商品 by kong 20160722*/
            $table_products = "store_products";
            $where = " and store_id = '$store_id'";
        }else{
            if($model_attr == 1){
                $table_products = "products_warehouse";
                $type_files = " AND warehouse_id = '$warehouse_id'";
            }elseif($model_attr == 2){
                $table_products = "products_area";
                $type_files = " AND area_id = '$area_id'";
            }else{
                $table_products = "products";
                $type_files = "";
            }
            //ecmoban模板堂 --zhuo end
        }
        //ecmoban模板堂 --zhuo end

        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '$goods_id' AND goods_attr = '$goods_attr' " . $type_files . " LIMIT 0, 1";
        $return_array = $GLOBALS['db']->getRow($sql);

    }
    return $return_array;
}

/*
 * 顶级分类页分类树
 */
function get_parent_cat_child($cat_id = 0)
{
    if ($cat_id > 0) {
        $sql = 'SELECT cat_id FROM ' . $GLOBALS['ecs']->table('category') . " WHERE parent_id = '$cat_id' AND is_show = 1 AND is_top_show = 1 LIMIT 1";
        if ($GLOBALS['db']->getOne($sql)) {
            /* 获取当前分类及其子分类 */
            $sql = 'SELECT cat_id,cat_name,parent_id,is_show ' .
                'FROM ' . $GLOBALS['ecs']->table('category') .
                "WHERE parent_id = '$cat_id' AND is_show = 1 AND is_top_show = 1 ORDER BY sort_order ASC, cat_id ASC";

            $res = $GLOBALS['db']->getAll($sql);

            foreach ($res AS $row) {
                $cat_arr[$row['cat_id']]['id'] = $row['cat_id'];
                $cat_arr[$row['cat_id']]['name'] = $row['cat_name'];
                $cat_arr[$row['cat_id']]['url'] = build_uri('category', array('cid' => $row['cat_id']), $row['cat_name']);

                if (isset($row['cat_id']) != NULL) {
                    $cat_arr[$row['cat_id']]['cat_id'] = get_child_tree_top($row['cat_id']);
                }
            }
        }


        if (isset($cat_arr)) {
            return $cat_arr;
        }
    }
}

function get_child_tree_top($tree_id = 0)
{
    $three_arr = array();
    $sql = 'SELECT count(*) FROM ' . $GLOBALS['ecs']->table('category') . " WHERE parent_id = '$tree_id' AND is_show = 1 ";
    if ($GLOBALS['db']->getOne($sql) || $tree_id == 0) {
        $child_sql = 'SELECT cat_id, cat_name, parent_id, is_show ' .
            'FROM ' . $GLOBALS['ecs']->table('category') .
            "WHERE parent_id = '$tree_id' AND is_show = 1 ORDER BY sort_order ASC, cat_id ASC";
        $res = $GLOBALS['db']->getAll($child_sql);
        foreach ($res AS $row) {
            $three_arr[$row['cat_id']]['id'] = $row['cat_id'];
            $three_arr[$row['cat_id']]['name'] = $row['cat_name'];
            $three_arr[$row['cat_id']]['url'] = build_uri('category', array('cid' => $row['cat_id']), $row['cat_name']);
        }
    }
    return $three_arr;
}


/**
 * 取得拍卖活动出价记录
 * @param   int $act_id 活动id
 * @return  array
 */
function snatch_log($snatch_id)
{
    $sql = "SELECT count(*) " .
        "FROM " . $GLOBALS['ecs']->table('snatch_log') . " AS s," .
        $GLOBALS['ecs']->table('users') . " AS u " .
        "WHERE s.user_id = u.user_id " .
        "AND snatch_id = '$snatch_id' ";
    $log = $GLOBALS['db']->getOne($sql);

    return $log;
}

/**
 * 查询商品最小起订量
 * @return
 */
function get_goods_min_quantity($goods_id, $attr_id = '', $admin_id = 0, $warehouse_id = 0, $area_id = 0)
{
    // model_inventory 商品库存模式 model_attr 商品属性模式
    $model = dao('goods')->field('model_inventory, model_attr, goods_type, min_quantity')->where(array('goods_id' => $goods_id))->find();

    if (empty($attr_id)) {
        $attr_id = 0;
    } else {
        if(is_array($attr_id)){
            $goods_attr_array = sort_goods_attr_id_array($attr_id);
            if (isset($goods_attr_array['sort'])) {
                $attr_id = implode('|', $goods_attr_array['sort']);
            }
        }else{
            //去掉复选属性by wu start
            $attr_arr = explode(',', $attr_id);
            foreach ($attr_arr as $key => $val) {
                $attr_type = get_goods_attr_id(array('goods_attr_id' => $val), array('a.attr_type'));
                if ($attr_type == 2 && $attr_arr[$key]) {
                    unset($attr_arr[$key]);
                }
            }
            $attr_id = implode(',', $attr_arr);
            //去掉复选属性by wu end
            $attr_id = str_replace(',', '|', $attr_id);
        }
    }

    $condition = array();
    $table_name = "goods"; // 兼容测试商品数据
    if($model['model_inventory'] == 0 && $model['model_attr'] == 0){
        // 默认模式 且有属性
        if($attr_id){
            $table_name = "products";
            $condition['goods_attr'] = $attr_id;
        }else{
            $table_name = "goods";
        }
    }elseif($model['model_inventory'] == 1 && $model['model_attr'] == 1){
        // 仓库模式 且有属性
        if($attr_id){
            $table_name = "products_warehouse";
            $condition['goods_attr'] = $attr_id;
            $condition['warehouse_id'] = $warehouse_id;
        }else{
            $table_name = "warehouse_goods";
            $condition['region_id'] = $warehouse_id;
        }
    }elseif($model['model_inventory'] == 2 && $model['model_attr'] == 2){
        // 地区模式 且有属性
        if($attr_id){
            $table_name = "products_area";
            $condition['goods_attr'] = $attr_id;
            $condition['area_id'] = $area_id;
        }else{
            $table_name = "warehouse_area_goods";
            $condition['region_id'] = $area_id;
        }
    }
    $condition['goods_id'] = $goods_id;
    $result = dao($table_name)->field('min_quantity')->where($condition)->find();

    if($model['goods_type'] == 0){
        $result['min_quantity'] = $model['min_quantity'];
    }else{
        if(empty($result)){
            //当商品没有属性最小起订量时
            $result['min_quantity'] = $model['min_quantity'];
        }
        if(!empty($result) && $GLOBALS['_CFG']['add_shop_price'] == 0){
            if(empty($result['min_quantity'])){
                $result['min_quantity'] = $model['min_quantity'];
            }
        }
    }
    return $result;
}
<?php
/**
 * 获得指定商品属性详情
 */
function get_attr_value($goods_id,$attr_id)
{
    $sql="select * from ".$GLOBALS['ecs']->table('goods_attr')." where goods_id='$goods_id' and goods_attr_id='$attr_id'";
    $re= $GLOBALS['db']->getRow($sql);

    if(!empty($re))
    {
        return $re;
    }
    else
    {
        return false;
    }
}
/**
 * 获得指定商品的各会员等级对应的价格
 *
 * @access public
 * @param integer $goods_id
 * @return array
 */
function get_user_rank_prices($goods_id, $shop_price) {

    $sql = "SELECT rank_id, IFNULL(mp.user_price, r.discount * $shop_price / 100) AS price, r.rank_name, r.discount " . 'FROM {pre}user_rank  AS r ' . 'LEFT JOIN {pre}member_price  AS mp ' . "ON mp.goods_id = '$goods_id' AND mp.user_rank = r.rank_id " . "WHERE r.show_price = 1 OR r.rank_id = '$_SESSION[user_rank]'";
    $res = $GLOBALS['db']->getAll($sql);
    $arr = array();
    foreach ($res as $row) {
        $arr [$row ['rank_id']] = array(
            'rank_name' => htmlspecialchars($row ['rank_name']),
            'price' => price_format($row ['price'])
        );
    }
    return $arr;
}

/**
 * 获得指定商品相关的商品
 *
 * @access  public
 * @param   integer $goods_id
 * @return  array
 */
/*function get_linked_goods($goods_id)
{
    $sql = "SELECT lg.link_goods_id AS goods_id, g.goods_name, lg.is_double " .
        "FROM {pre}link_goods AS lg, {pre}goods AS g " .
        "WHERE lg.goods_id = '$goods_id' " .
        "AND lg.link_goods_id = g.goods_id ";
    if ($goods_id == 0)
    {
        $sql .= " AND lg.admin_id = '$_SESSION[admin_id]'";
    }
    $row = $GLOBALS['db']->getAll($sql);

    foreach ($row AS $key => $val)
    {
        $linked_type = $val['is_double'] == 0 ? L('single') : L('double');

        $row[$key]['goods_name'] = $val['goods_name'] . " -- [$linked_type]";

        unset($row[$key]['is_double']);
    }

    return $row;
}*/

/**
 * 获得购买过该商品的人还买过的商品
 *
 * @access  public
 * @param   integer     $goods_id
 * @return  array
 */
function get_also_bought($goods_id)
{
    $sql = 'SELECT COUNT(b.goods_id ) AS num, g.goods_id, g.goods_name, g.goods_thumb, g.goods_img, g.shop_price, g.promote_price, g.promote_start_date, g.promote_end_date ' .
        'FROM {pre}order_goods AS a ' .
        'LEFT JOIN {pre}order_goods  AS b ON b.order_id = a.order_id ' .
        'LEFT JOIN {pre}goods  AS g ON g.goods_id = b.goods_id ' .
        "WHERE a.goods_id = '$goods_id' AND b.goods_id <> '$goods_id' AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 " .
        'GROUP BY b.goods_id ' .
        'ORDER BY num DESC ' .
        'LIMIT ' . C('shop.bought_goods');
    $res = $GLOBALS['db']->query($sql);
    $arr = array();
    foreach($res as $key=>$row){
        $arr[$key]['goods_id']    = $row['goods_id'];
        $arr[$key]['goods_name']  = $row['goods_name'];
        $arr[$key]['short_name']  = C('shop.goods_name_length') > 0 ? sub_str($row['goods_name'],C('shop.goods_name_length')) : $row['goods_name'];
        $arr[$key]['goods_thumb'] = get_image_path($row['goods_thumb']);
        $arr[$key]['goods_img']   = get_image_path($row['goods_img']);
        $arr[$key]['shop_price']  = price_format($row['shop_price']);
        $arr[$key]['url']         = build_uri('goods', array('gid'=>$row['goods_id']));
        if ($row['promote_price'] > 0){
            $arr[$key]['promote_price'] = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
            $arr[$key]['formated_promote_price'] = price_format($arr[$key]['promote_price']);
        }else{
            $arr[$key]['promote_price'] = 0;
        }
    }
    return $arr;
}


/**
 * 获得指定商品的销售排名
 *
 * @access  public
 * @param   integer     $goods_id
 * @return  integer
 */
function get_goods_rank($goods_id)
{
    /* 查询该商品销量 */
    $sql = 'SELECT IFNULL(SUM(g.goods_number), 0) ' .
        'FROM {pre}order_info AS o, {pre}order_goods  AS g ' .
        "WHERE o.order_id = g.order_id " .
        "AND o.order_status = '" . OS_CONFIRMED . "' " .
        "AND o.shipping_status " . db_create_in(array(SS_SHIPPED, SS_RECEIVED)) .
        " AND o.pay_status " . db_create_in(array(PS_PAYED, PS_PAYING)) .
        " AND g.goods_id = '$goods_id'";
    $sales_count = $GLOBALS['db']->getOne($sql);
    if ($sales_count > 0){
        /* 只有在商品销售量大于0时才去计算该商品的排行 */
        $sql = 'SELECT DISTINCT SUM(goods_number) AS num ' .
            'FROM {pre}order_info  AS o,  {pre}order_goods  AS g ' .
            "WHERE o.order_id = g.order_id " .
            "AND o.order_status = '" . OS_CONFIRMED . "' " .
            "AND o.shipping_status " . db_create_in(array(SS_SHIPPED, SS_RECEIVED)) .
            " AND o.pay_status " . db_create_in(array(PS_PAYED, PS_PAYING)) .
            " GROUP BY g.goods_id HAVING num > $sales_count";
        $res = $GLOBALS['db']->query($sql);
        $rank = count($res) + 1;
        if ($rank > 10){
            $rank = 0;
        }
    }else{
        $rank = 0;
    }
    return $rank;
}


/**
 * 取得跟商品关联的礼包列表
 *
 * @param string $goods_id
 *        	商品编号
 *
 * @return 礼包列表
 */
function get_package_goods_list($goods_id) {
    $now = gmtime();
    $sql = "SELECT pg.goods_id, ga.act_id, ga.act_name, ga.act_desc, ga.goods_name, ga.start_time,
					   ga.end_time, ga.is_finished, ga.ext_info
				FROM {pre}goods_activity AS ga, {pre}package_goods  AS pg
				WHERE pg.package_id = ga.act_id
				AND ga.start_time <= '" . $now . "'
				AND ga.end_time >= '" . $now . "'
				AND pg.goods_id = " . $goods_id . "
                AND ga.review_status = 3
				GROUP BY ga.act_id
				ORDER BY ga.act_id ";
    $res = $GLOBALS['db']->getAll($sql);

    foreach ($res as $tempkey => $value) {
        $subtotal = 0;
        $row = unserialize($value ['ext_info']);
        unset($value ['ext_info']);
        if ($row) {
            foreach ($row as $key => $val) {
                $res [$tempkey] [$key] = $val;
            }
        }

        $sql = "SELECT pg.package_id, pg.goods_id, pg.goods_number, pg.admin_id, p.goods_attr, g.goods_sn, g.goods_name, g.market_price, g.goods_thumb, IFNULL(mp.user_price, g.shop_price * '$_SESSION[discount]') AS rank_price
        FROM {pre}package_goods  AS pg
						LEFT JOIN {pre}goods AS g
							ON g.goods_id = pg.goods_id
						LEFT JOIN {pre}products AS p
							ON p.product_id = pg.product_id
						LEFT JOIN {pre}member_price AS mp
						ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]'
						    WHERE pg.package_id = " . $value ['act_id'] . "
					ORDER BY pg.package_id, pg.goods_id";

        $goods_res = $GLOBALS['db']->getAll($sql);

        foreach ($goods_res as $key => $val) {
            $goods_id_array [] = $val ['goods_id'];
            $goods_res [$key] ['goods_thumb'] = get_image_path($val ['goods_thumb']);
            $goods_res [$key] ['market_price'] = price_format($val ['market_price']);
            $goods_res [$key] ['rank_price'] = price_format($val ['rank_price']);
            $subtotal += $val ['rank_price'] * $val ['goods_number'];
        }

        /* 取商品属性 */
        $sql = "SELECT ga.goods_attr_id, ga.attr_value
					FROM {pre}goods_attr AS ga, {pre}attribute AS a
					    WHERE a.attr_id = ga.attr_id
					AND a.attr_type = 1
					AND " . db_create_in($goods_id_array, 'goods_id');
        $result_goods_attr = $GLOBALS['db']->getAll($sql);

        $_goods_attr = array();
        foreach ($result_goods_attr as $value) {
            $_goods_attr [$value ['goods_attr_id']] = $value ['attr_value'];
        }

        /* 处理货品 */
        $format = '[%s]';
        foreach ($goods_res as $key => $val) {
            if ($val ['goods_attr'] != '') {
                $goods_attr_array = explode('|', $val ['goods_attr']);

                $goods_attr = array();
                foreach ($goods_attr_array as $_attr) {
                    $goods_attr [] = $_goods_attr [$_attr];
                }

                $goods_res [$key] ['goods_attr_str'] = sprintf($format, implode('，', $goods_attr));
            }
        }

        $res [$tempkey] ['goods_list'] = $goods_res;
        $res [$tempkey] ['subtotal'] = price_format($subtotal);
        $res [$tempkey] ['saving'] = price_format(($subtotal - $res [$tempkey] ['package_price']));
        $res [$tempkey] ['package_price'] = price_format($res [$tempkey] ['package_price']);
    }

    return $res;
}

/**
 * 获取商品销量总数
 *
 * @access public
 * @param integer $goods_id
 * @return integer
 */
function get_goods_sales($goods_id)
{
    /* 查询该商品销量 */
    $sql = 'SELECT IFNULL(SUM(g.goods_number), 0) as count ' .
        'FROM {pre}order_info AS o, {pre}order_goods AS g ' .
        "WHERE o . order_id = g . order_id " .
        " AND o . order_status = '" . OS_CONFIRMED . "'" .
        " AND o . shipping_status " . db_create_in(array(SS_SHIPPED, SS_RECEIVED)) .
        " AND o . pay_status " . db_create_in(array(PS_PAYED, PS_PAYING)) .
        " AND g . goods_id = '$goods_id'";
    $result = $GLOBALS['db']->getOne($sql);
    return $result;
}


/**
 * 调用购物车信息
 *
 * @access  public
 * @return  string
 */
function cart_number()
{
    if(!empty($_SESSION['user_id'])){
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }
    $sql = 'SELECT SUM(goods_number) AS number ' .
        ' FROM ' . $GLOBALS['ecs']->table('cart') .
        " WHERE " . $sess_id . " AND rec_type = '" . CART_GENERAL_GOODS . "'";
    $row = $GLOBALS['db']->GetRow($sql);

    if ($row)
    {
        $number = intval($row['number']);
    }
    else
    {
        $number = 0;
    }
    return $number;
}


/**
 * 查询评论内容
 *
 * @access  public
 * @params  integer     $id
 * @params  integer     $page
 * @return  array
 */
function comment($id, $page = 1)
{
    $type = 0;
    /* 取得评论列表 */
    $count = $GLOBALS['db']->getOne('SELECT COUNT(*) FROM ' . $GLOBALS['ecs']->table('comment') .
        " WHERE id_value = '$id' AND comment_type = '$type' AND status = 1 AND parent_id = 0");
    $size = !empty($GLOBALS['_CFG']['comments_number']) ? $GLOBALS['_CFG']['comments_number'] : 5;

    $page_count = ($count > 0) ? intval(ceil($count / $size)) : 1;

    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('comment') .
        " WHERE id_value = '$id' AND comment_type = '$type' AND status = 1 AND parent_id = 0" .
        ' ORDER BY comment_id DESC';
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    $arr = array();
    $ids = '';
    foreach ($res as $row) {
        $ids .= $ids ? ",$row[comment_id]" : $row['comment_id'];
        $arr[$row['comment_id']]['id'] = $row['comment_id'];
        $arr[$row['comment_id']]['email'] = $row['email'];
        $arr[$row['comment_id']]['username'] = $row['user_name'];
        $arr[$row['comment_id']]['content'] = str_replace('\r\n', '<br />', htmlspecialchars($row['content']));
        $arr[$row['comment_id']]['content'] = nl2br(str_replace('\n', '<br />', $arr[$row['comment_id']]['content']));
        $arr[$row['comment_id']]['rank'] = $row['comment_rank'];
        $arr[$row['comment_id']]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
    }

    /* 取得已有回复的评论 */
    if ($ids) {
        $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('comment') .
            " WHERE parent_id IN( $ids )";
        $res = $GLOBALS['db']->query($sql);
        foreach($res as $row){
            $arr[$row['parent_id']]['re_content'] = nl2br(str_replace('\n', '<br />', htmlspecialchars($row['content'])));
            $arr[$row['parent_id']]['re_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
            $arr[$row['parent_id']]['re_email'] = $row['email'];
            $arr[$row['parent_id']]['re_username'] = $row['user_name'];
        }
    }
    /* 分页样式 */
    //$pager['styleid'] = isset($GLOBALS['_CFG']['page_style'])? intval($GLOBALS['_CFG']['page_style']) : 0;
    $pager['page'] = $page;
    $pager['size'] = $size;
    $pager['record_count'] = $count;
    $pager['page_count'] = $page_count;
    $pager['page_first'] = "javascript:gotoPage(1,$id,$type)";
    $pager['page_prev'] = $page > 1 ? "javascript:gotoPage(" . ($page - 1) . ",$id,$type)" : 'javascript:;';
    $pager['page_next'] = $page < $page_count ? 'javascript:gotoPage(' . ($page + 1) . ",$id,$type)" : 'javascript:;';
    $pager['page_last'] = $page < $page_count ? 'javascript:gotoPage(' . $page_count . ",$id,$type)" : 'javascript:;';

    $cmt = array('comments' => $arr, 'pager' => $pager);

    return $cmt;
}

/**
 * 获得指定商品的关联商品
 *
 * @access  public
 * @param   integer     $goods_id
 * @return  array
 */
function get_linked_goods($goods_id, $warehouse_id = 0, $area_id = 0)
{
    //ecmoban模板堂 --zhuo start
    $where = '';
    $leftJoin = '';

    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    if($GLOBALS['_CFG']['open_area_goods'] == 1){
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('link_area_goods'). " as lag on g.goods_id = lag.goods_id ";
        $where .= " and lag.region_id = '$area_id' ";
    }
    //ecmoban模板堂 --zhuo end
//g.setting_sales,
    $sql = 'SELECT g.goods_id, g.goods_name, g.goods_thumb, g.goods_img, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
        "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, ".
        'g.market_price, g.sales_volume,' .
        'IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, ' .
        ' g.promote_start_date, g.promote_end_date ' .
        'FROM ' . $GLOBALS['ecs']->table('link_goods') . ' lg ' .
        'LEFT JOIN ' . $GLOBALS['ecs']->table('goods') . ' AS g ON g.goods_id = lg.link_goods_id ' .
        "LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp ".
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' ".
        $leftJoin.
        "WHERE lg.goods_id = '$goods_id' AND g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 " .
        $where .
        "LIMIT " . $GLOBALS['_CFG']['related_goods_number'];
    $res = $GLOBALS['db']->query($sql);

    $arr = array();
    foreach($res as $row){
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
        $arr[$row['goods_id']]['goods_id']     = $row['goods_id'];
        $arr[$row['goods_id']]['goods_name']   = $row['goods_name'];
        $arr[$row['goods_id']]['short_name']   = $GLOBALS['_CFG']['goods_name_length'] > 0 ?
            sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        $arr[$row['goods_id']]['goods_thumb']  = get_image_path($row['goods_thumb']);
        $arr[$row['goods_id']]['goods_img']    = get_image_path($row['goods_img']);
        $arr[$row['goods_id']]['market_price'] = price_format($row['market_price']);
        $arr[$row['goods_id']]['shop_price']   = price_format($row['shop_price']);
        $arr[$row['goods_id']]['url']          = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);
        $arr[$row['goods_id']]['sales_volume'] = $row['sales_volume'];
        $arr[$row['goods_id']]['setting_sales'] = $row['setting_sales'];
        $arr[$row['goods_id']]['promote_price']    = ($promote_price > 0) ? price_format($promote_price) : '';
    }

    return $arr;
}


/**
 * 查询商品评论
 * @param $id
 * @param string $rank
 * @param int $start
 * @param int $size
 * @return bool
 */
function get_good_comment($id, $rank = NULL, $hasgoods = 0, $start = 0, $size = 10){
    if(empty($id)){
        return false;
    }
    $where = '';

    $rank = (empty($rank) && $rank !== 0) ? '' : intval($rank);

    if($rank == 4){
        //好评
        $where = ' AND  comment_rank in (4, 5)';
    }
    elseif($rank == 2){
        //中评
        $where = ' AND  comment_rank in (2, 3)';
    }
    elseif($rank === 0){
        //差评
        $where = ' AND  comment_rank in (0, 1)';
    }elseif($rank == 1){
        //差评
        $where = ' AND  comment_rank in (0, 1)';
    }else if($rank == 5){
        $where = ' AND  comment_rank in (0, 1, 2, 3, 4,5)';
    }

    $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('comment'). " WHERE id_value = '".$id."' and comment_type = 0 and status = 1 and parent_id = 0 ".$where." ORDER BY comment_id DESC LIMIT $start, $size";

    $comment = $GLOBALS['db']->getAll($sql);
    $arr = array();
    if($comment){
        $ids = '';
        foreach ($comment as $key=>$row) {
            $ids .= $ids ? ",$row[comment_id]" : $row['comment_id'];
            $arr[$row['comment_id']]['id'] = $row['comment_id'];
            $arr[$row['comment_id']]['email'] = $row['email'];
            $sql = "SELECT user_picture, nick_name FROM {pre}users WHERE user_name = '$row[user_name]'";
            $one = $GLOBALS['db']->getAll($sql);
            $arr[$row['comment_id']]['username'] =encrypt_username($one[0]['nick_name']);
            $arr[$row['comment_id']]['user_picture'] = get_image_path($one[0]['user_picture']);
            $arr[$row['comment_id']]['content'] = str_replace('\r\n', '<br />',$row['content']);
            $arr[$row['comment_id']]['content'] = nl2br(str_replace('\n', '<br />', $arr[$row['comment_id']]['content']));
            $arr[$row['comment_id']]['rank'] = $row['comment_rank'];
            $arr[$row['comment_id']]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
            if($row['order_id'] && $hasgoods){
                $sql = "SELECT o.goods_id, o.goods_name, o.goods_attr, g.goods_img FROM ".$GLOBALS['ecs']->table('order_goods')." o LEFT JOIN ".$GLOBALS['ecs']->table('goods')." g ON o.goods_id = g.goods_id WHERE o.order_id = '".$row['order_id']."' ORDER BY rec_id DESC";
                $goods = $GLOBALS['db']->getAll($sql);
                if($goods){
                    foreach($goods as $k=>$v){
                        $goods[$k]['goods_img']  = get_image_path($v['goods_img']);
                        $goods[$k]['goods_attr'] = str_replace('\r\n', '<br />', $v['goods_attr']);
                    }
                }
                $arr[$row['comment_id']]['goods'] = $goods;
            }
            $sql = "SELECT img_thumb FROM {pre}comment_img WHERE comment_id = ".$row['comment_id'];
            $comment_thumb = $GLOBALS['db']->getCol($sql);
            if(count($comment_thumb)>0){
                foreach ($comment_thumb as $k => $v){
                    $comment_thumb[$k] = get_image_path($v);
                }
                $arr[$row['comment_id']]['thumb'] = $comment_thumb;
            }else{
                $arr[$row['comment_id']]['thumb']= 0;
            }

        }

        /* 取得已有回复的评论 */
        if ($ids) {
            $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('comment') ." WHERE parent_id IN( $ids )";
            $res = $GLOBALS['db']->query($sql);
            foreach($res as $row){
                $arr[$row['parent_id']]['re_content'] = nl2br(str_replace('\n', '<br />', htmlspecialchars($row['content'])));
                $arr[$row['parent_id']]['re_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
                $arr[$row['parent_id']]['re_email'] = $row['email'];
                $arr[$row['parent_id']]['re_username'] = $row['user_name'];
            }
        }
        $arr = array_values($arr);
    }
    return $arr;
}
function get_good_comment_as($id, $rank = NULL, $hasgoods = 0, $start = 0, $size = 10){
    if(empty($id)){
        return false;
    }
    $where = '';

    $rank = (empty($rank) && $rank !== 0) ? '' : intval($rank);

    if($rank == 4){
        //好评
        $where = ' AND  comment_rank in (4, 5)';
    }
    elseif($rank == 2){
        //中评
        $where = ' AND  comment_rank in (2, 3)';
    }
    elseif($rank === 0){
        //差评
        $where = ' AND  comment_rank in (0, 1)';
    }elseif($rank == 1){
        //差评
        $where = ' AND  comment_rank in (0, 1)';
    }else if($rank == 5){
        $where = ' AND  comment_rank in (0, 1, 2, 3, 4,5)';
    }

    $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('comment'). " WHERE id_value = '".$id."' and comment_type = 0 and status = 1 and parent_id = 0 ".$where." ORDER BY comment_id DESC LIMIT $start, $size";

    $comment = $GLOBALS['db']->getAll($sql);


    $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('comment'). " WHERE id_value = '".$id."' and comment_type = 0 and status = 1 and parent_id = 0 ".$where;

    $max = $GLOBALS['db']->getAll($sql);

    $max = ceil(count($max)/$size);
    $arr = array();
    if($comment){
        $ids = '';
        foreach ($comment as $key=>$row) {
            $ids .= $ids ? ",$row[comment_id]" : $row['comment_id'];
            $arr[$row['comment_id']]['id'] = $row['comment_id'];
            $arr[$row['comment_id']]['email'] = $row['email'];
            
            $users = get_wechat_user_info($row['user_id']);
            $arr[$row['comment_id']]['username'] = encrypt_username($users['nick_name']);
            $arr[$row['comment_id']]['user_picture'] = get_image_path($users['user_picture']);
         
            $arr[$row['comment_id']]['content'] = str_replace('\r\n', '<br />', $row['content']);
            $arr[$row['comment_id']]['content'] = nl2br(str_replace('\n', '<br />', $arr[$row['comment_id']]['content']));
            $arr[$row['comment_id']]['rank'] = $row['comment_rank'];
            $arr[$row['comment_id']]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
            if($row['order_id'] && $hasgoods){
                $sql = "SELECT o.goods_id, o.goods_name, o.goods_attr, g.goods_img FROM ".$GLOBALS['ecs']->table('order_goods')." o LEFT JOIN ".$GLOBALS['ecs']->table('goods')." g ON o.goods_id = g.goods_id WHERE o.order_id = '".$row['order_id']."' ORDER BY rec_id DESC";
                $goods = $GLOBALS['db']->getAll($sql);
                if($goods){
                    foreach($goods as $k=>$v){
                        $goods[$k]['goods_img']  = get_image_path($v['goods_img']);
                        $goods[$k]['goods_attr'] = str_replace('\r\n', '<br />',$v['goods_attr']);
                    }
                }
                $arr[$row['comment_id']]['goods'] = $goods;
            }
            $sql = "SELECT img_thumb FROM {pre}comment_img WHERE comment_id = ".$row['comment_id'];
            $comment_thumb = $GLOBALS['db']->getCol($sql);
            if(count($comment_thumb)>0){
                foreach ($comment_thumb as $k => $v){
                    $comment_thumb[$k] = get_image_path($v);
                }
                $arr[$row['comment_id']]['thumb'] = $comment_thumb;
            }else{
                $arr[$row['comment_id']]['thumb']= 0;
            }

        }
        
        /* 取得已有回复的评论 */
        if ($ids) {
            $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('comment') ." WHERE parent_id IN( $ids )";
            $res = $GLOBALS['db']->query($sql);
            foreach($res as $row){
                $arr[$row['parent_id']]['re_content'] = nl2br(str_replace('\n', '<br />', htmlspecialchars($row['content'])));
                $arr[$row['parent_id']]['re_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
                $arr[$row['parent_id']]['re_email'] = $row['email'];
                $arr[$row['parent_id']]['re_username'] = $row['user_name'];
            }
        }
        $arr = array_values($arr);
    }

    return array('arr'=>$arr,'max'=>$max);
}
/*
 * 取得商品评论条数
 */
function commentCol($id){
     if(empty($id)){
         return false;
     }
     $sql = "SELECT count(comment_id) as num FROM {pre}comment WHERE id_value =".$id.' and comment_type = 0 and status = 1 and parent_id = 0';
     $arr['all_comment'] = $GLOBALS['db']->getOne($sql);
     $sql = "SELECT count(comment_id) as num FROM {pre}comment WHERE id_value =".$id.' AND  comment_rank in (4, 5) and comment_type = 0 and status = 1 and parent_id = 0 ';
     $arr['good_comment'] = $GLOBALS['db']->getOne($sql);
     $sql = "SELECT count(comment_id) as num FROM {pre}comment WHERE id_value =".$id.' AND  comment_rank in (2, 3) and comment_type = 0 and status = 1 and parent_id = 0 ';
     $arr['in_comment'] = $GLOBALS['db']->getOne($sql);
     $sql = "SELECT count(comment_id) as num FROM {pre}comment WHERE id_value =".$id.' AND  comment_rank in (0, 1) and comment_type = 0 and status = 1 and parent_id = 0 ';
     $arr['rotten_comment'] = $GLOBALS['db']->getOne($sql);
     $sql = "SELECT count( DISTINCT b.comment_id) as num FROM {pre}comment as a LEFT JOIN {pre}comment_img as b ON a.id_value=b.goods_id WHERE a.id_value =".$id." and a.comment_type = 0 and a.status = 1 and a.parent_id = 0 and b.img_thumb != ''";
     $arr['img_comment'] = $GLOBALS['db']->getOne($sql);
     foreach ($arr as $key =>$val){
         $arr[$key] = empty($val) ? 0 : $arr[$key];
     }
     return $arr;

}



/**
 * 获取商品ajax属性是否都选中
 */
function get_goods_attr_ajax($goods_id, $goods_attr, $goods_attr_id){

    $arr = array();
    $arr['attr_id'] = '';
    $goods_attr = implode(",", $goods_attr);
    if($goods_attr){

        if($goods_attr_id){
            $goods_attr_id = implode(",", $goods_attr_id);
            $where = " AND ga.goods_attr_id IN($goods_attr_id)";
        }else{
            $where = '';
        }

        $sql = "SELECT ga.goods_attr_id, ga.attr_id, ga.attr_value  FROM " .$GLOBALS['ecs']->table('goods_attr') ." AS ga".
            " LEFT JOIN " . $GLOBALS['ecs']->table('attribute') ." AS a ON ga.attr_id = a.attr_id ".
            " WHERE ga.attr_id IN($goods_attr) AND ga.goods_id = '$goods_id' $where AND a.attr_type > 0 ORDER BY a.sort_order, ga.attr_sort, ga.goods_attr_id";
        $res = $GLOBALS['db']->getAll($sql);

        foreach($res as $key=>$row){
            $arr[$row['attr_id']][$row['goods_attr_id']] = $row;

            $arr['attr_id'] .= $row['attr_id'] . ",";
        }

        if($arr['attr_id']){
            $arr['attr_id'] = substr($arr['attr_id'], 0, -1);
            $arr['attr_id'] = explode(",", $arr['attr_id']);
        }else{
            $arr['attr_id'] = array();
        }
    }

    return $arr;
}
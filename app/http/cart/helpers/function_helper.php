<?php

/**
 * 取得某用户等级当前时间可以享受的优惠活动
 * @param   int     $user_rank      用户等级id，0表示非会员
 * @return  array
 */
function favourable_list($user_rank)
{
    /* 购物车中已有的优惠活动及数量 */
    $used_list = cart_favourable();
    /* 当前用户可享受的优惠活动 */
    $favourable_list = array();
    $user_rank = ',' . $user_rank . ',';
    $now = gmtime();
    $sql = "SELECT * " .
        "FROM {pre}favourable_activity" .
        " WHERE CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'" .
        " AND start_time <= '$now' AND end_time >= '$now'" .
        " AND review_status = 3 " .
        " ORDER BY sort_order";
    $res = $GLOBALS['db']->query($sql);
    $favourable_list = array();
    if($res){
        foreach($res as $favourable)
        {
            $favourable['start_time'] = local_date(C('shop.time_format'), $favourable['start_time']);
            $favourable['end_time']   = local_date(C('shop.time_format'), $favourable['end_time']);
            $favourable['formated_min_amount'] = price_format($favourable['min_amount'], false);
            $favourable['formated_max_amount'] = price_format($favourable['max_amount'], false);
            $favourable['gift']       = unserialize($favourable['gift']);
            foreach ($favourable['gift'] as $key => $value)
            {
                $favourable['gift'][$key]['formated_price'] = price_format($value['price'], false);
                $sql = "SELECT COUNT(*) FROM {pre}goods WHERE is_on_sale = 1 AND goods_id = ".$value['id'];
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
    }
    return $favourable_list;
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
 * 取得购物车中已有的优惠活动及数量
 *
 * @return array
 */
function cart_favourable() {
    if(!empty($_SESSION['user_id'])){
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }

    $sql = "SELECT is_gift, COUNT(*) AS num " . "FROM {pre}cart  WHERE " . $sess_id  . " AND rec_type = '" . CART_GENERAL_GOODS . "'" . " AND is_gift > 0" . " GROUP BY is_gift";
    $res = $GLOBALS['db']->getAll($sql);
    $list = array();
    if($res){
        foreach ($res as $row) {
            $list [$row ['is_gift']] = $row ['num'];
        }
    }
    return $list;
}

/**
 * 比较优惠活动的函数，用于排序（把可用的排在前面）
 *
 * @param array $a
 *        	优惠活动a
 * @param array $b
 *        	优惠活动b
 * @return int 相等返回0，小于返回-1，大于返回1
 */
function cmp_favourable($a, $b) {
    if ($a ['available'] == $b ['available']) {
        if ($a ['sort_order'] == $b ['sort_order']) {
            return 0;
        } else {
            return $a ['sort_order'] < $b ['sort_order'] ? - 1 : 1;
        }
    } else {
        return $a ['available'] ? - 1 : 1;
    }
}

/*
 * 取得购物车条件
 */
function sess (){
 //ecmoban模板堂 --zhuo start
        if(!empty($_SESSION['user_id'])){
            $info['sess_id'] = " user_id = '" . $_SESSION['user_id'] . "' ";

            $info['a_sess'] = " a.user_id = '" . $_SESSION['user_id'] . "' ";
            $info['b_sess'] = " b.user_id = '" . $_SESSION['user_id'] . "' ";
            $info['c_sess'] = " c.user_id = '" . $_SESSION['user_id'] . "' ";

            $info['sess_cart'] = "";
        }else{
            $info['sess_id'] = " session_id = '" . real_cart_mac_ip() . "' ";

            $info['a_sess'] = " a.session_id = '" . real_cart_mac_ip() . "' ";
            $info['b_sess'] = " b.session_id = '" . real_cart_mac_ip() . "' ";
            $info['c_sess'] = " c.session_id = '" . real_cart_mac_ip() . "' ";

            $info['sess_cart'] = real_cart_mac_ip();
        }
        //ecmoban模板堂 --zhuo ends
    return $info;
}
/**
 * 删除购物车中的商品
 *
 * @access public
 * @param integer $id
 * @return void
 */
function flow_drop_cart_goods($id) {
	$sess=sess();
    /* 取得商品id */
    $sql = "SELECT * FROM {pre}cart WHERE rec_id = '$id'";
    $row = $GLOBALS['db']->getRow($sql);
    //删除购物车中不能单独销售的商品
    flow_clear_cart_alone();
    if ($row) {
        // 如果是超值礼包
        if ($row ['extension_code'] == 'package_buy') {
            $sql = "DELETE FROM {pre}cart WHERE ".$sess['sess_id']." AND rec_id = '$id' LIMIT 1";
        }
        // 如果是普通商品，同时删除所有赠品及其配件
        elseif ($row ['parent_id'] == 0 && $row ['is_gift'] == 0) {
            /* 检查购物车中该普通商品的不可单独销售的配件并删除 */
            $sql = "SELECT c.rec_id
				FROM {pre}cart AS c, {pre}group_goods AS gg, {pre}goods AS g
				WHERE gg.parent_id = '" . $row ['goods_id'] . "'
				AND c.goods_id = gg.goods_id
				AND c.parent_id = '" . $row ['goods_id'] . "'
				AND c.extension_code <> 'package_buy'
				AND gg.goods_id = g.goods_id
				AND g.is_alone_sale = 0";
            $res = $GLOBALS['db']->getAll($sql);
            $_del_str = $id . ',';
            foreach ($res as $id_alone_sale_goods) {
                $_del_str .= $id_alone_sale_goods ['rec_id'] . ',';
            }
            $_del_str = trim($_del_str, ',');

            $sql = "DELETE FROM {pre}cart WHERE ".$sess['sess_id']." AND (rec_id IN ($_del_str) OR parent_id = '$row[goods_id]' OR is_gift <> 0)";
        }
        // 如果不是普通商品，只删除该商品即可
        else {
            $sql = "DELETE FROM {pre}cart WHERE ".$sess['sess_id']." AND rec_id = '$id' LIMIT 1";
        }
        $result = $GLOBALS['db']->query($sql);
    }
    return $result? $result : false;

}


/**
 * 删除购物车中不能单独销售的商品
 *
 * @access public
 * @return void
 */
function flow_clear_cart_alone() {
    $sess=sess();
    /* 查询：购物车中所有不可以单独销售的配件 */
    $sql = "SELECT c.rec_id, gg.parent_id
		FROM {pre}cart AS c
		LEFT JOIN {pre}group_goods AS gg ON c.goods_id = gg.goods_id
		LEFT JOIN {pre}goods AS g ON c.goods_id = g.goods_id
		WHERE ".$sess['c_sess']."  
		AND c.extension_code <> 'package_buy'
		AND gg.parent_id > 0
		AND g.is_alone_sale = 0";
    $res = $GLOBALS['db']->query($sql);
    $rec_id = array();
    foreach ($res as $row) {
        $rec_id [$row ['rec_id']] [] = $row ['parent_id'];
    }
    if (empty($rec_id)) {
        return;
    }

    /* 查询：购物车中所有商品 */
    $sql = "SELECT DISTINCT goods_id
		FROM {pre}cart WHERE ".$sess['sess_id']." 
		AND extension_code <> 'package_buy'";
    $res = $GLOBALS['db']->query($sql);
    $cart_good = array();
    foreach ($res as $row) {
        $cart_good [] = $row ['goods_id'];
    }
    if (empty($cart_good)) {
        return;
    }

    /* 如果购物车中不可以单独销售配件的基本件不存在则删除该配件 */
    $del_rec_id = '';
    foreach ($rec_id as $key => $value) {
        foreach ($value as $v) {
            if (in_array($v, $cart_good)) {
                continue 2;
            }
        }

        $del_rec_id = $key . ',';
    }
    $del_rec_id = trim($del_rec_id, ',');

    if ($del_rec_id == '') {
        return;
    }

    /* 删除 */
    $sql = "DELETE FROM {pre}cart WHERE ".$sess['sess_id']." 
    AND rec_id IN ($del_rec_id)";
    $GLOBALS['db']->query($sql);
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
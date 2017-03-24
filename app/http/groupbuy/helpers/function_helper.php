<?php
/*
 * 取得团购活动列表
 * @return   array
 */
function group_buy_list($size, $page, $keywords, $sort, $order)
{
    /* 取得团购活动 */
    $gb_list = array();
    $now = gmtime();
    $where = "";
    if ($keywords) {
        $where = "AND (b.act_name LIKE '%$keywords%' OR g.goods_name LIKE '%$keywords%') ";
    }
    if ($sort == 'comments_number') {
        $sql = "SELECT b.*, IFNULL(g.goods_thumb, '') AS goods_thumb, b.act_id AS group_buy_id, g.market_price," .
            "b.start_time AS start_date, b.end_time AS end_date " .
            "FROM " . $GLOBALS['ecs']->table('goods_activity') . " AS b " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON b.goods_id = g.goods_id " .
            "WHERE b.act_type = '" . GAT_GROUP_BUY . "' $where " .
            "AND b.start_time <= '$now' AND b.is_finished < 3 AND b.review_status = 3 ORDER BY g." . $sort . ' ' . $order;
    } else {
        $sql = "SELECT b.*, IFNULL(g.goods_thumb, '') AS goods_thumb, b.act_id AS group_buy_id, g.market_price," .
            "b.start_time AS start_date, b.end_time AS end_date " .
            "FROM " . $GLOBALS['ecs']->table('goods_activity') . " AS b " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON b.goods_id = g.goods_id " .
            "WHERE b.act_type = '" . GAT_GROUP_BUY . "' $where " .
            "AND b.start_time <= '$now' AND b.is_finished < 3 AND b.review_status = 3 ORDER BY b." . $sort . ' ' . $order;
    }

    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    foreach ($res as $key => $val) {

        $ext_info = unserialize($val['ext_info']);
        $val = array_merge($val, $ext_info);

        /* 格式化时间 */
        $val['formated_end_date'] =  groupbuydate($val['end_date']);
        $val['is_end'] = $now > $val['end_date'] ? 1 : 0;

        /* 格式化保证金 */
        $val['formated_deposit'] = price_format($val['deposit'], false);
        /* 处理价格阶梯 */
        $price_ladder = $val['price_ladder'];
        if (!is_array($price_ladder) || empty($price_ladder)) {
            $price_ladder = array(array('amount' => 0, 'price' => 0));
        } else {
            foreach ($price_ladder as $key => $amount_price) {
                $price_ladder[$key]['formated_price'] = price_format($amount_price['price']);
            }
        }

        $val['price_ladder'] = $price_ladder;

        /*团购节省和折扣计算 by ecmoban start*/
        $price = $val['market_price']; //原价
        $nowprice = $val['price_ladder'][0]['price']; //现价
        $val['jiesheng'] = $price - $nowprice; //节省金额
        if ($nowprice > 0 && $price > 0) {
            $val['zhekou'] = round(10 / ($price / $nowprice), 1);
        } else {
            $val['zhekou'] = 0;
        }

        $stat = group_buy_stat($val['act_id'], $ext_info['deposit']);
        $val['cur_amount'] = $stat['valid_goods'];         // 当前数量

        $val['goods_thumb'] = get_image_path($val['goods_thumb']);

        /* 处理链接 */
        $val['url'] = build_uri('groupbuy', array('gbid' => $val['group_buy_id']));
        $val['price'] = price_format($nowprice, false);
        $group_buy[] = $val;
    }
    return $group_buy;
}
function groupbuydate($time = NULL) {
    $text = '';
    $t = $time - gmtime(); //时间差 （秒）
    if($t <= 0){
        return 1;
    }
    $y = date('Y', $time)-date('Y', gmtime());//是否跨年
    switch($t){
        case $t == 0:
            $text = '刚刚';
            break;
        case $t < 60:
            $text = $t . '秒'; // 一分钟内
            break;
        case $t < 60 * 60:
            $text = floor($t / 60) . '分'; //一小时内
            break;
        case $t < 60 * 60 * 24:
            $text = floor($t / (60 * 60)) . '时'; // 一天内
            break;
        default:
            $text = floor($t/(60*60*24)) . '天'; //一年以前
            break;
    }

    return $text;
}

/**
 * 取得上次的过滤条件
 * @param   string $param_str 参数字符串，由list函数的参数组成
 * @return  如果有，返回array('filter' => $filter, 'sql' => $sql)；否则返回false
 */
function get_filter($param_str = '')
{
    $filterfile = basename(PHP_SELF, '.php');
    if ($param_str) {
        $filterfile .= $param_str;
    }
    if (isset($_GET['uselastfilter']) && isset($_COOKIE['ECSCP']['lastfilterfile'])
        && $_COOKIE['ECSCP']['lastfilterfile'] == sprintf('%X', crc32($filterfile))
    ) {
        return array(
            'filter' => unserialize(urldecode($_COOKIE['ECSCP']['lastfilter'])),
            'sql' => base64_decode($_COOKIE['ECSCP']['lastfiltersql'])
        );
    } else {
        return false;
    }
}

/**
 * 取得上次的过滤条件
 * @param   string $param_str 参数字符串，由list函数的参数组成
 * @return  如果有，返回array('filter' => $filter, 'sql' => $sql)；否则返回false
 */
function get_filter_one($id)
{
    if ($id == 0) {
        return 0;
    }
    $sql = "SELECT * FROM {pre}goods_activity WHERE review_status = 3 and act_id = " . $id;
    $info = $GLOBALS['db']->getRow($sql);
    $info['ext_info'] = unserialize($info['ext_info']);
    return $info;
}


/* 取得团购活动总数 */
function group_buy_count($keywords)
{
    $now = gmtime();
    $where = '';
    if ($keywords) {
        $where = "AND (ga.act_name LIKE '%$keywords%' OR g.goods_name LIKE '%$keywords%') ";
    }
    $sql = "SELECT COUNT(*) " .
        "FROM " . $GLOBALS['ecs']->table('goods_activity') . " AS ga " .
        "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON ga.goods_id = g.goods_id " .
        "WHERE ga.act_type = '" . GAT_GROUP_BUY . "' " .
        "AND ga.start_time <= '$now' AND ga.is_finished < 3 AND ga.review_status = 3 " . $where;

    return $GLOBALS['db']->getOne($sql);
}

function get_merchant_group_goods($group_buy_id){
    $ru_id = $GLOBALS['db']->getOne("SELECT user_id FROM " .$GLOBALS['ecs']->table('goods_activity'). " WHERE act_id = '$group_buy_id' AND review_status = 3");
    $sql = "SELECT ga.act_id, ga.ext_info, ga.act_name, g.goods_thumb, g.sales_volume FROM " . $GLOBALS['ecs']->table('goods_activity') . " ga"
        ." LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " g ON ga.goods_id = g.goods_id "
        . " WHERE ga.user_id = '$ru_id' AND act_type = '" . GAT_GROUP_BUY . "' AND ga.review_status = 3 LIMIT 4 ";
    $merchant_group = $GLOBALS['db']->getAll($sql);

    foreach($merchant_group as $key=>$row){
        $ext_info = unserialize($row['ext_info']);
        $row = array_merge($row, $ext_info);
        $merchant_group[$key]['cur_price'] = $row['ext_info']['cur_price'];

        /* 处理价格阶梯 */
        $price_ladder = $row['price_ladder'];
        if (!is_array($price_ladder) || empty($price_ladder))
        {
            $price_ladder = array(array('amount' => 0, 'price' => 0));
        }
        else
        {
            foreach ($price_ladder as $k => $amount_price)
            {
                $price_ladder[$k]['formated_price'] = price_format($amount_price['price'], false);
            }
        }

        $merchant_group[$key]['shop_price'] = $price_ladder[0]['formated_price'];
        $merchant_group[$key]['goods_thumb']  = get_image_path($row['goods_thumb']);
    }

    return $merchant_group;
}
function get_top_group_goods($order)
{
    $sql = "SELECT ga.*, g.sales_volume, g.goods_thumb, g.goods_id FROM " . $GLOBALS['ecs']->table('goods_activity') . " ga"
        ." LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " g ON ga.goods_id = g.goods_id "
        . " WHERE ga.user_id = '$user_id' AND g.goods_id > 0 AND act_type = '" . GAT_GROUP_BUY . "' AND ga.review_status = 3 ORDER BY g.".$order." LIMIT 5 ";
    $look_top_list = $GLOBALS['db']->getAll($sql);

    foreach ($look_top_list as $key => $look_top)
    {
        $ext_info = unserialize($look_top['ext_info']);
        $look_top['ext_info'] = $ext_info;
        // 处理价格阶梯
        $price_ladder = $look_top['ext_info']['price_ladder'];
        if (!is_array($price_ladder) || empty($price_ladder))
        {
            $price_ladder = array(array('amount' => 0, 'price' => 0));
        }
        else
        {
            foreach ($price_ladder as $k => $amount_price)
            {
                $price_ladder[$k]['formated_price'] = price_format($amount_price['price'], false);
            }
        }
        $look_top['ext_info']['price_ladder'] = $price_ladder;

        // 计算当前价
        $cur_price  = $price_ladder[0]['price']; // 初始化

        foreach ($price_ladder as $amount_price)
        {
            if ($cur_amount >= $amount_price['amount'])
            {
                $cur_price = $amount_price['price'];
            }
            else
            {
                break;
            }
        }

        $look_top['goods_thumb']  = get_image_path($look_top['goods_thumb']);

        $look_top['ext_info']['cur_price'] = price_format($cur_price,false); //现价
        $look_top_list_1[$key] = $look_top;
    }

    return $look_top_list_1;
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
            $arr[$row['comment_id']]['username'] = encrypt_username($row['user_name']);
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
/**
 * 清空购物车
 * @param   int     $type   类型：默认普通商品
 */
function clear_cart($type = CART_GENERAL_GOODS, $cart_value = '')
{
    //ecmoban模板堂 --zhuo start
    if(!empty($_SESSION['user_id'])){
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }

    $goodsIn = '';
    if(!empty($cart_value)){
        $goodsIn = " and rec_id in($cart_value)";
    }
    //ecmoban模板堂 --zhuo end

    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') .
        " WHERE " .$sess_id. " AND rec_type = '$type'" . $goodsIn;
    $GLOBALS['db']->query($sql);

    if(!empty($_SESSION['user_id'])){
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
        $sess_id = " user_id = '" . real_cart_mac_ip() . "' ";
    }

    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart_user_info') . " WHERE " .$sess_id;
    $GLOBALS['db']->query($sql);
}

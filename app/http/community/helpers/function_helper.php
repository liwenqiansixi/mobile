<?php
/**
 * 讨论贴 $type = 1 问答帖 $type = 2 圈子帖 $type = 3
 * @var [type]
 */
function community_list($type = 0, $page = 1, $size = 10, $user_id = '')
{
    $where = "";
    if ($type) {
        $where .= " AND dis_type = " . $type;
    }
    if ($user_id) {
        $where .= " AND u.user_id = " . $user_id;
    }
    $sql = "SELECT d.*,u.user_id, u.user_name, u.nick_name, u.user_picture, g.goods_name FROM {pre}discuss_circle d " .
        " LEFT JOIN {pre}users u ON d.user_id = u.user_id " .
        " LEFT JOIN {pre}goods g ON d.goods_id = g.goods_id " .
        " WHERE d.parent_id = 0 AND d.user_id <> 0 AND d.dis_type != 4" . $where . " ORDER BY add_time DESC LIMIT " . ($page - 1) * $size . ",  $size";

    $list = $GLOBALS['db']->query($sql);
    $total = community_num($type, 0, $user_id);
    if ($list) {
        foreach ($list as $k => $v) {
            $list[$k]['add_time'] = mdate($v['add_time']);
            $users = get_wechat_user_info($v['user_id']);
            $list[$k]['user_name'] = encrypt_username($users['nick_name']);
            $list[$k]['user_picture'] = get_image_path($users['user_picture']);
            $list[$k]['community_num'] = community_num(0, $v['dis_id']);
            $list[$k]['url'] = build_uri('community', array('r' => 'index/detail', 'coid' => $v['dis_id'],'type' => $v['dis_type']));
            if (isset($_COOKIE[$v['dis_id'] . $v['dis_type'] . 'islike']) && ($_COOKIE[$v['dis_id'] . $v['dis_type'] . 'islike'] == '1')) {
                $list[$k]['islike'] = '1';
            } else {
                $list[$k]['islike'] = '0';
            }
        }
    }
    return array('list' => $list, 'totalPage' => ceil($total / $size));
}

/**
 * 晒单列表
 */
function comment_list($page = 1, $size = 10, $user_id = '')
{

    $where = "AND cmt.status=1";
    if($user_id){
        $where .= " AND cmt.user_id = " . $user_id ;
    }
    $sql = "SELECT u.user_picture,u.user_name,u.nick_name,cmt.like_num, cmt.comment_id AS dis_id,cmt.id_value,cmt.useful,cmt.parent_id,cmt.content,cmt.order_id,cmt.add_time,cmt.user_name,cmt2.comment_img,cmt.dis_browse_num FROM " .
        $GLOBALS['ecs']->table('comment') . " AS cmt " .
        "LEFT JOIN (SELECT comment_id,goods_id,comment_img FROM " . $GLOBALS['ecs']->table('comment_img') . " GROUP BY comment_id) cmt2 ON (cmt2.comment_id = cmt.comment_id) " .
        "LEFT JOIN  " . $GLOBALS['ecs']->table('users') . " u ON (cmt.user_id = u.user_id) " .
        "WHERE  cmt2.comment_img != '' AND cmt.comment_id <> '0' $where  LIMIT " . ($page - 1) * $size . ",  $size";
    $list = $GLOBALS['db']->getAll($sql);

    $total = sd_count();
    if ($list) {
        foreach ($list as $k => $v) {
            $list[$k]['add_time'] = mdate($v['add_time']);
            $list[$k]['dis_browse_num'] = $v['dis_browse_num'] ? $v['dis_browse_num'] : 0;
            $list[$k]['user_picture'] = get_image_path($v['user_picture']);
            $list[$k]['user_name'] = !empty($v['nick_name']) ? encrypt_username($v['nick_name']) : encrypt_username($v['user_name']);
            $list[$k]['dis_type'] = 4;
            $list[$k]['community_num'] = community_num(4,$v['dis_id']);
            $list[$k]['dis_title'] = sub_str($v['content'], 20); //晒单贴没有标题，从内容截取
            $list[$k]['url'] = build_uri('community', array('r' => 'index/detail', 'coid' => $v['dis_id'],'type' => 4));
            if (isset($_COOKIE[$v['dis_id'] . '4' . 'islike']) && ($_COOKIE[$v['dis_id'] . '4' . 'islike'] == '1')) {
                $list[$k]['islike'] = '1';
            } else {
                $list[$k]['islike'] = '0';
            }
        }
    }
  
    return array('list' => $list, 'totalPage' => ceil($total / $size));

}

/**
 * 查询三种类型帖子的数量
 * @param   $type           帖子类型
 * @param   $parent_id
 * @param   $user_id
 */
function community_num($type = 0, $parent_id = 0, $user_id = '')
{
    $where = " AND parent_id = " . $parent_id;
    if ($type) {
        $where .= " AND dis_type = " . $type;
    }
    if ($user_id) {
        $where .= " AND user_id = " . $user_id;
    }
    $sql = "SELECT COUNT(*) as num FROM {pre}discuss_circle WHERE user_id <> 0 " . $where;
    $num = $GLOBALS['db']->query($sql);
    if ($num) {
        return $num[0]['num'];
    }
    return 0;
}


/**
 * 是否有新帖子
 */
function community_has_new($type = 0, $comment = 0)
{
    $where = "";
    if ($_SESSION['user_id']) {
        if (!isset($_COOKIE['community_view_time_' . $type]) || empty($_COOKIE['community_view_time_' . $type])) {
            cookie('community_view_time_' . $type, gmtime(), 3600 * 24);
        }
        $where .= " AND add_time > '" . $_COOKIE['community_view_time_' . $type] . "'";
        //如果是晒单
        if ($comment) {
            $sql = "SELECT COUNT(*) as num FROM {pre}comment WHERE status = 1 AND parent_id = 0 " . $where;
        } else {
            if ($type) {
                $where .= " AND dis_type = " . $type;
            }
            $sql = "SELECT COUNT(*) as num FROM {pre}discuss_circle WHERE user_id <> 0 " . $where;
        }
        $num = $GLOBALS['db']->query($sql);
        if ($num && $num[0]['num'] > 0) {
            return true;
        }
    }
    return false;
}


/**
 * 晒单帖总条数
 * */
function sd_count($user_id = '')
{

    $where = "AND cmt.status=1 ";
    if ($user_id) {
        $where .= " AND cmt.user_id = " . $user_id;
    }
    $sql = "SELECT count(*) FROM " . $GLOBALS['ecs']->table('comment') . " AS cmt " .
        "LEFT JOIN (SELECT comment_id,goods_id,comment_img FROM " . $GLOBALS['ecs']->table('comment_img') . " GROUP BY comment_id) cmt2 ON (cmt2.comment_id = cmt.comment_id) " .
        "LEFT JOIN  " . $GLOBALS['ecs']->table('users') . " u ON (cmt.user_id = u.user_id) " .
        "WHERE cmt2.comment_img != '' AND cmt.comment_id <> '0' " . $where;
    $num = $GLOBALS['db']->getOne($sql);

    if($num){
        return $num;
    }else{
        return 0;
    }
}

/**
 * 调用浏览历史
 *
 * @access  public
 * @return  string
 */
function historys() {
    $str = '';
    $history = array();
    if (!empty($_COOKIE['ECS']['history_goods'])) {
        $where = db_create_in($_COOKIE['ECS']['history_goods'], 'goods_id');
        $sql = 'SELECT goods_id, goods_name, goods_thumb, shop_price FROM {pre}goods' .
            " WHERE $where AND is_on_sale = 1 AND is_alone_sale = 1 AND is_delete = 0";
        $query = $GLOBALS['db']->getAll($sql);
        $res = array();
        foreach ($query as $key => $row) {
            $goods['goods_id'] = $row['goods_id'];
            $name = mb_substr($row['goods_name'],0,7, 'utf-8');
            $goods['goods_name'] = $name;
            $goods['short_name'] = C('goods_name_length') > 0 ? sub_str($row['goods_name'], C('goods_name_length')) : $row['goods_name'];
            $goods['goods_thumb'] = get_image_path($row['goods_thumb']);
            $goods['shop_price'] = price_format($row['shop_price']);
            $goods['url'] = url('goods/index/index', array('id' => $row['goods_id']));
            $history[] = $goods;
        }
    }
    return $history;
}

/**
 * 是否有新回复
 */
function reply_has_new()
{
    if (!isset($_COOKIE['community_reply']) || empty($_COOKIE['community_reply'])) {
        cookie('community_reply', gmtime() + 3600 * 24);
    }
    if($_COOKIE['community_reply']){
        $sql = "SELECT COUNT(*) as num FROM {pre}discuss_circle dc
            LEFT JOIN {pre}discuss_circle as dc2 ON dc.parent_id = dc2.dis_id
            WHERE dc.user_id != " . $_SESSION['user_id'] . " AND dc2.user_id = " . $_SESSION['user_id'] . " AND dc.parent_id != 0
            AND dc.add_time > ".$_COOKIE['community_reply'];
        $num = $GLOBALS['db']->query($sql);
        if ($num && $num[0]['num'] > 0) {
            return true;
        }
    }
    return false;
}

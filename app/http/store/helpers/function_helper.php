<?php

/**
 * 获得二级分类的信息
 *
 * @param integer $cat_id
 *
 * @return void
 */
 function get_cat_two($cat_id)
{
    $sql = "SELECT a.*,b.cat_img FROM {pre}category AS a LEFT JOIN {pre}touch_category AS b ON a.cat_id = b.cat_id WHERE a.is_show = 1 AND a.parent_id = ".$cat_id;
    $cate = $GLOBALS['db']->getAll($sql);
    if($cate){
        foreach($cate as $key=>$val){
            $cate[$key]['cat_img'] = get_image_path($val['goods_img']); //设置默认图片
        }
    }

    return $cate;
}


/**
 * 获得分类的信息
 *
 * @param integer $cat_id
 *
 * @return void
 */
function get_cat_info($cat_id)
{
    return $GLOBALS['db']->getRow('SELECT cat_name, keywords, cat_desc, style, grade, filter_attr, parent_id FROM {pre}category as g WHERE cat_id = ' . $cat_id);
}

/**
 * 获得分类下的商品总数
 *
 * @access public
 * @param string $cat_id            
 * @return integer
 */
function get_cagtegory_goods_count($children, $brand = 0, $min = 0, $max = 0, $ext = '')
{
    $where = "g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 AND ($children OR " . get_extension_goods($children) . ')';
    
    if ($brand > 0) {
        $where .= " AND g.brand_id = $brand ";
    }
    
    if ($min > 0) {
        $where .= " AND g.shop_price >= $min ";
    }
    
    if ($max > 0) {
        $where .= " AND g.shop_price <= $max ";
    }
    
    /* 返回商品总数 */
    return $this->db->getOne('SELECT COUNT(*) FROM {pre}goods' . " AS g WHERE $where $ext");
}

/**
 * 获得分类下的商品
 *
 * @access public
 * @param string $children            
 * @return array
 */
function get_cagtegory_goods($cat_id, $sort = 'g.last_update')
{  
    $sql = "SELECT g.goods_id, g.goods_name,g.goods_img,g.shop_price FROM {pre}goods AS g  WHERE g.is_on_sale = 1 AND g.cat_id = ".$cat_id." ORDER BY ".$sort." DESC";
    $goods_list =  $GLOBALS['db']->query($sql);
    foreach ($goods_list as $key => $val){
        $goods_list[$key]['goods_img'] = get_image_path($val['goods_img']);
        $sql = "SELECT (AVG(comment_rank) * 20) AS rank,COUNT(*) AS count FROM {pre}comment WHERE comment_type = 0 AND id_value = ".$val['goods_id'];
        $rank = $GLOBALS['db']->getRow($sql);
        $goods_list[$key]['rank'] = substr($rank['rank'],0,2) ? substr($rank['rank'],0,2) : 0;
        $goods_list[$key]['count'] = $rank['count'];
    }
    return $goods_list;
}

/**
 * 取得最近的上级分类的grade值
 *
 * @access  public
 * @param   int     $cat_id    //当前的cat_id
 *
 * @return int
 */
function get_parent_grade($cat_id)
{
    static $res = NULL;

    if ($res === NULL)
    {
        $data = read_static_cache('cat_parent_grade');
        if ($data === false)
        {
            $sql = "SELECT parent_id, cat_id, grade ".
                " FROM " . $GLOBALS['ecs']->table('category');
            $res = $GLOBALS['db']->getAll($sql);
            write_static_cache('cat_parent_grade', $res);
        }
        else
        {
            $res = $data;
        }
    }

    if (!$res)
    {
        return 0;
    }

    $parent_arr = array();
    $grade_arr = array();

    foreach ($res as $val)
    {
        $parent_arr[$val['cat_id']] = $val['parent_id'];
        $grade_arr[$val['cat_id']] = $val['grade'];
    }

    while ($parent_arr[$cat_id] >0 && $grade_arr[$cat_id] == 0)
    {
        $cat_id = $parent_arr[$cat_id];
    }

    return $grade_arr[$cat_id];

}

/**
 * 获得分类下的商品
 * @param $keywords 关键词查询条件
 * @param $children
 * @param $brand
 * @param $min
 * @param $max
 * @param $ext
 * @param $size
 * @param $page
 * @param $sort
 * @param $order
 * @param int $warehouse_id
 * @param int $area_id
 * @param $ubrand
 * @return array
 */
function category_get_goods($keywords = '', $children, $brand, $min, $max, $ext, $size, $page, $sort, $order, $warehouse_id = 0, $area_id = 0, $ubrand){
    $display = isset($_COOKIE['ECS']['display']) ? $_COOKIE['ECS']['display'] : 'grid';
    $where = "g.is_on_sale = 1 AND g.is_alone_sale = 1 AND g.is_delete = 0 ";
    if($keywords){
        $where .= $keywords;
    }
    else{
        $where .= " AND ($children OR " . get_extension_goods($children) . ')';
    }

    $leftJoin = '';
    if ($brand > 0)
    {
        $leftJoin .= "LEFT JOIN " . $GLOBALS['ecs']->table('link_brand') . " AS lb " . "ON lb.bid = g.brand_id ";
        $leftJoin .= 'LEFT JOIN ' . $GLOBALS['ecs']->table('merchants_shop_brand') . ' AS msb ' . "ON msb.bid = lb.bid ";
        $where .=  "AND (g.brand_id = '$brand' OR (lb.brand_id = '$brand' AND g.brand_id = lb.bid AND msb.audit_status = 1)) ";
    }else{
        $leftJoin .= "LEFT JOIN " . $GLOBALS['ecs']->table('brand') . " AS b " . "ON b.brand_id = g.brand_id ";
        $leftJoin .= "LEFT JOIN " . $GLOBALS['ecs']->table('link_brand') . " AS lb " . "ON lb.bid = g.brand_id ";
        $leftJoin .= 'LEFT JOIN ' . $GLOBALS['ecs']->table('merchants_shop_brand') . ' AS msb ' . "ON msb.bid = lb.bid ";
        $where .=  "AND (b.audit_status = 1 OR msb.audit_status = 1) ";
    }

    //ecmoban模板堂 --zhuo start
    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_goods'). " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " .$GLOBALS['ecs']->table('warehouse_area_goods'). " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

    if($GLOBALS['_CFG']['open_area_goods'] == 1){
        $leftJoin .= " left join " .$GLOBALS['ecs']->table('link_area_goods'). " as lag on g.goods_id = lag.goods_id ";
        $where .= " and lag.region_id = '$area_id' ";
    }
    //ecmoban模板堂 --zhuo end

    if ($min > 0)
    {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) >= $min ";
    }

    if ($max > 0)
    {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) <= $max ";
    }

    if($sort == 'last_update'){
        $sort ='g.last_update';
    }

    //ecmoban模板堂 --zhuo start
    if($GLOBALS['_CFG']['review_goods'] == 1){
        $where .= ' AND g.review_status > 2 ';
    }
    //ecmoban模板堂 --zhuo end

    /* 获得商品列表 */
    $sql = 'SELECT g.goods_id, g.user_id, g.goods_name, ' .$shop_price. ' g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot, ' .
        ' IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, ' .
        ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, g.model_price, ' .
        "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, " .
        "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, g.goods_type, " .
        'g.promote_start_date, g.promote_end_date, g.is_promote, g.goods_brief, g.goods_thumb , g.goods_img ' .
        'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' .
        $leftJoin.
        'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
        "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
        "WHERE $where $ext group by g.goods_id  ORDER BY $sort $order";

    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    $arr = array();
    foreach($res as $row){
        $arr[$row['goods_id']]['org_price']             = $row['org_price'];
        $arr[$row['goods_id']]['model_price']             = $row['model_price'];

        $arr[$row['goods_id']]['warehouse_price']         = $row['warehouse_price'];
        $arr[$row['goods_id']]['warehouse_promote_price'] = $row['warehouse_promote_price'];
        $arr[$row['goods_id']]['region_price']            = $row['region_price'];
        $arr[$row['goods_id']]['region_promote_price']    = $row['region_promote_price'];

        if ($row['promote_price'] > 0)
        {
            $promote_price = bargain_price($row['promote_price'], $row['promote_start_date'], $row['promote_end_date']);
        }
        else
        {
            $promote_price = 0;
        }

        /* 处理商品水印图片 */
        $watermark_img = '';

        if ($promote_price != 0)
        {
            $watermark_img = "watermark_promote_small";
        }
        elseif ($row['is_new'] != 0)
        {
            $watermark_img = "watermark_new_small";
        }
        elseif ($row['is_best'] != 0)
        {
            $watermark_img = "watermark_best_small";
        }
        elseif ($row['is_hot'] != 0)
        {
            $watermark_img = 'watermark_hot_small';
        }

        if ($watermark_img != '')
        {
            $arr[$row['goods_id']]['watermark_img'] =  $watermark_img;
        }

        $arr[$row['goods_id']]['goods_id']         = $row['goods_id'];
        if($display == 'grid')
        {
            $arr[$row['goods_id']]['goods_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        }
        else
        {
            $arr[$row['goods_id']]['goods_name']       = $row['goods_name'];
        }
        $arr[$row['goods_id']]['name']             = $row['goods_name'];
        $arr[$row['goods_id']]['goods_brief']      = $row['goods_brief'];
        $arr[$row['goods_id']]['sales_volume']      = $row['sales_volume'];
        $arr[$row['goods_id']]['comments_number']      = $row['comments_number'];
        $arr[$row['goods_id']]['is_promote']             = $row['is_promote'];
        /* 折扣节省计算 by ecmoban start */
        if($row['market_price'] > 0)
        {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $arr[$row['goods_id']]['zhekou']  = $discount_arr['discount'];  //zhekou
        $arr[$row['goods_id']]['jiesheng']  = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
        $arr[$row['goods_id']]['goods_style_name'] = add_style($row['goods_name'],$row['goods_name_style']);

        $goods_id = $row['goods_id'];
        $count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where id_value ='$goods_id' AND status = 1 AND parent_id = 0");
        $arr[$row['goods_id']]['review_count']      = $count;

        $arr[$row['goods_id']]['market_price']     = price_format($row['market_price']);
        $arr[$row['goods_id']]['shop_price']       = price_format($row['shop_price']);
        $arr[$row['goods_id']]['type']             = $row['goods_type'];
        $arr[$row['goods_id']]['promote_price']    = ($promote_price > 0) ? price_format($promote_price) : '';
        $arr[$row['goods_id']]['goods_thumb']      = get_image_path($row['goods_thumb']);
        $arr[$row['goods_id']]['goods_img']        = get_image_path($row['goods_img']);
        $arr[$row['goods_id']]['url']              = build_uri('goods', array('gid'=>$row['goods_id']), $row['goods_name']);

        //ecmoban模板堂 --zhuo start
        if($row['model_attr'] == 1){
            $table_products = "products_warehouse";
            $type_files = " and warehouse_id = '$warehouse_id'";
        }elseif($row['model_attr'] == 2){
            $table_products = "products_area";
            $type_files = " and area_id = '$area_id'";
        }else{
            $table_products = "products";
            $type_files = "";
        }

        $sql = "SELECT * FROM " .$GLOBALS['ecs']->table($table_products). " WHERE goods_id = '" .$row['goods_id']. "'" .$type_files. " LIMIT 0, 1";
        $arr[$row['goods_id']]['prod'] = $GLOBALS['db']->getRow($sql);

        if(empty($prod)){ //当商品没有属性库存时
            $arr[$row['goods_id']]['prod'] = 1;
        }else{
            $arr[$row['goods_id']]['prod'] = 0;
        }

        $arr[$row['goods_id']]['goods_number'] = $row['goods_number'];

        $sql="select * from ".$GLOBALS['ecs']->table('seller_shopinfo')." where ru_id='" .$row['user_id']. "'";
        $basic_info = $GLOBALS['db']->getRow($sql);
        $arr[$row['goods_id']]['kf_type'] = $basic_info['kf_type'];
        $arr[$row['goods_id']]['kf_ww'] = $basic_info['kf_ww'];
        $arr[$row['goods_id']]['kf_qq'] = $basic_info['kf_qq'];
        $arr[$row['goods_id']]['rz_shopName'] = get_shop_name($row['user_id'], 1); //店铺名称
        $arr[$row['goods_id']]['user_id'] = $row['user_id'];
        $arr[$row['goods_id']]['store_url'] = build_uri('merchants_store', array('urid'=>$row['user_id']), $arr[$row['goods_id']]['rz_shopName']);

        $arr[$row['goods_id']]['count'] = selled_count($row['goods_id']);
        $mc_all = ments_count_all($row['goods_id']);       //总条数
        $mc_one = ments_count_rank_num($row['goods_id'],1);		//一颗星
        $mc_two = ments_count_rank_num($row['goods_id'],2);	    //两颗星
        $mc_three = ments_count_rank_num($row['goods_id'],3);   	//三颗星
        $mc_four = ments_count_rank_num($row['goods_id'],4);		//四颗星
        $mc_five = ments_count_rank_num($row['goods_id'],5);		//五颗星
        $arr[$row['goods_id']]['zconments'] = get_conments_stars($mc_all,$mc_one,$mc_two,$mc_three,$mc_four,$mc_five);
        //ecmoban模板堂 --zhuo end
    }

    return $arr;
}

 //查询所有商家的顶级分类
  function get_cat_store_list($cat_id){
    $sql = "SELECT user_shopMain_category AS user_cat, user_id FROM {pre}merchants_shop_information  WHERE 1 AND user_shopMain_category <> '' AND merchants_audit = 1";
    $res =  $GLOBALS['db']->query($sql);
    $user_id = '';
    $arr = array();
    foreach($res as $key=>$row){
        $row['cat_str'] = '';
        $row['user_cat'] = explode('-', $row['user_cat']);
        
        foreach($row['user_cat'] as $uck=>$ucrow){
            if($ucrow){
                $row['user_cat'][$uck] = explode(':', $ucrow);
                if(!empty($row['user_cat'][$uck][0])){
                    $row['cat_str'] .= $row['user_cat'][$uck][0] . ",";
                }
            }

        }
        
        if($row['cat_str']){
            $row['cat_str'] = substr($row['cat_str'], 0, -1);
            $row['cat_str'] = explode(',', $row['cat_str']);
            
            if(in_array($cat_id, $row['cat_str'])){
                $user_id .= $row['user_id'] . ",";
            }
        }
        
        $arr[] = $row;
    }
    
    if($user_id){
        $user_id = substr($user_id, 0, -1);
    }
 
    return $user_id;
}


/**
 * 获得分类下的商品
 *
 * @access  public
 * @param   string  $children
 * @return  array
 */
function store_get_goods($children, $brand, $min, $max, $ext, $size, $page, $sort, $order, $merchant_id, $warehouse_id = 0, $area_id = 0, $keyword, $type) {
    //ecmoban模板堂 --zhuo start
    if ($children == '') {
        $cat_where = " AND g.user_id = '$merchant_id' ";
    } else {
        $cat_where = " AND $children ";
    }


    $display = $GLOBALS['display'];
    $where = "g.is_on_sale = 1 AND g.is_alone_sale = 1 AND " .
        "g.is_delete = 0 $cat_where";

    if ($brand > 0) {
        $where .= "AND g.brand_id=$brand ";
    }

    //ecmoban模板堂 --zhuo start
    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wag.region_price, wag.region_promote_price, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end

    if ($min > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) >= $min ";
    }

    if ($max > 0) {
        $where .= " AND IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) <= $max ";
    }

    $where .= " AND g.user_id = '$merchant_id'";

    if (!empty($keyword)) {
        $where .= " AND g.goods_name LIKE '%" . mysql_like_quote($keyword) . "%'";
    }


    if ($sort == 'last_update') {
        $sort = 'g.last_update';
    }

    if($type){
        $turetype .= "$type = 1 AND";
    }else{
        $turetype = '';
    }

    //ecmoban模板堂 --zhuo start
    if ($GLOBALS['_CFG']['review_goods'] == 1) {
        $where .= ' AND g.review_status > 2 ';
    }
    //ecmoban模板堂 --zhuo end


    /* 计算总数*/
    if($size == 0 && $page == 0){
        $sql = 'SELECT count(*), ' .
            ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
            "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, g.is_promote, " .
            "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, g.goods_type, g.goods_number " .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' . $leftJoin .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            "WHERE $where $ext  ORDER BY $sort $order";
        $res = $GLOBALS['db']->getRow($sql);

        return $res['count(*)'];
    }else{
        /* 获得商品列表 */
        $sql = 'SELECT g.goods_id, g.goods_name, g.goods_number, ' . $shop_price . ' g.goods_name_style, g.comments_number,g.sales_volume,g.market_price, g.is_new, g.is_best, g.is_hot, ' .
            ' IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, ' .
            "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price, g.is_promote, " .
            "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, g.goods_type, " .
            'g.promote_start_date, g.promote_end_date, g.goods_brief, g.goods_thumb , g.goods_img ' .
            'FROM ' . $GLOBALS['ecs']->table('goods') . ' AS g ' . $leftJoin .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('member_price') . ' AS mp ' .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            "WHERE $turetype $where $ext  group by g.goods_id   ORDER BY $sort $order";

        $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);
    }

    $arr = array();
    $idx = 0;
    foreach ($res as $row) {
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

        /* 处理商品水印图片 */
        $watermark_img = '';

        if ($promote_price != 0) {
            $watermark_img = "watermark_promote_small";
        } elseif ($row['is_new'] != 0) {
            $watermark_img = "watermark_new_small";
        } elseif ($row['is_best'] != 0) {
            $watermark_img = "watermark_best_small";
        } elseif ($row['is_hot'] != 0) {
            $watermark_img = 'watermark_hot_small';
        }

        if ($watermark_img != '') {
            $arr[$idx]['watermark_img'] = $watermark_img;
        }

        $arr[$idx]['goods_id'] = $row['goods_id'];
        if ($display == 'grid') {
            $arr[$idx]['goods_name'] = $GLOBALS['_CFG']['goods_name_length'] > 0 ? sub_str($row['goods_name'], $GLOBALS['_CFG']['goods_name_length']) : $row['goods_name'];
        } else {
            $arr[$idx]['goods_name'] = $row['goods_name'];
        }
        $arr[$idx]['name'] = $row['goods_name'];
        $arr[$idx]['goods_brief'] = $row['goods_brief'];
        $arr[$idx]['sales_volume'] = $row['sales_volume'];
        $arr[$idx]['comments_number'] = $row['comments_number'];
        /* 折扣节省计算 by ecmoban start */
        if ($row['market_price'] > 0) {
            $discount_arr = get_discount($row['goods_id']); //函数get_discount参数goods_id
        }
        $arr[$idx]['zhekou'] = $discount_arr['discount'];  //zhekou
        $arr[$idx]['jiesheng'] = $discount_arr['jiesheng']; //jiesheng
        /* 折扣节省计算 by ecmoban end */
        $arr[$idx]['goods_style_name'] = add_style($row['goods_name'], $row['goods_name_style']);
        $goods_id = $row['goods_id'];

        $count = $GLOBALS['db']->getOne("SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('comment') . " where comment_type=0 and id_value ='$goods_id'");

        $arr[$idx]['review_count'] = $count;

        $arr[$idx]['market_price'] = price_format($row['market_price']);
        $arr[$idx]['shop_price'] = price_format($row['shop_price']);
        $arr[$idx]['type'] = $row['goods_type'];
        $arr[$idx]['is_promote'] = $row['is_promote'];
        $arr[$idx]['goods_number'] = $row['goods_number'];
        $arr[$idx]['promote_price'] = ($promote_price > 0) ? price_format($promote_price) : '';
        $arr[$idx]['goods_thumb'] = get_image_path($row['goods_thumb']);
        $arr[$idx]['goods_img'] = get_image_path($row['goods_thumb']);
        $arr[$idx]['goods_url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);

        $arr[$idx]['count'] = selled_count($row['goods_id']);

        $arr[$idx]['pictures'] = get_goods_gallery($row['goods_id']);// 商品相册
        $attr = get_goods_properties($row['goods_id'], $warehouse_id, $area_id);
        $arr[$idx]['spe'] = $attr['spe'];
        $idx++;
    }

    return $arr;
}
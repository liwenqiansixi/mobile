<?php

/**
* 获取当前分类
 */
function top_all($type=''){
	/* 获取当前分类及其子分类 */
	 $sql = 'SELECT c.cat_id,c.cat_name,c.parent_id,c.is_show ' .
			'FROM {pre}zc_category
 as c ' .
			"WHERE c.parent_id = 0 AND c.is_show = 1 ORDER BY c.sort_order ASC, c.cat_id ASC";

	$res = $GLOBALS['db']->getAll($sql);
	foreach ($res AS $row) {
		if ($row['is_show']) {
			$cat_arr[$row['cat_id']]['id'] = $row['cat_id'];
			$cat_arr[$row['cat_id']]['type'] = $type;
			$cat_arr[$row['cat_id']]['name'] = $row['cat_name'];
			if (isset($row['cat_id']) == isset($row['parent_id'])) {
				$cat_arr[$row['cat_id']]['cat_id'] = get_child_trees($row['cat_id']);
			}
		}
	}

	return $cat_arr;
}
/**
* 获取分类下子分类
 */
function get_child_trees($tree_id = 0) {
	$three_arr = array();
	$sql = 'SELECT count(*) FROM ' . "{pre}zc_category
 WHERE parent_id = '$tree_id' AND is_show = 1 ";
	if ($GLOBALS['db']->getOne($sql) || $tree_id == 0) {
		$child_sql = 'SELECT c.cat_id, c.cat_name, c.parent_id, c.is_show ' .
				'FROM {pre}zc_category
 as c ' .
				" WHERE c.parent_id = '$tree_id' AND c.is_show = 1 GROUP BY c.cat_id ORDER BY c.sort_order ASC, c.cat_id ASC";
		$res = $GLOBALS['db']->getAll($child_sql);
		foreach ($res AS $row) {
			if ($row['is_show']) {
				$three_arr[$row['cat_id']]['id'] = $row['cat_id'];
				$three_arr[$row['cat_id']]['name'] = $row['cat_name'];
			}
			if (isset($row['cat_id']) != NULL) {
				$three_arr[$row['cat_id']]['cat_id'] = get_child_trees($row['cat_id']);
			}
		}
	}
	return $three_arr;
}

/**
* 众筹项目信息
 */
function zc_goods_info($goods_id = 0){

	$sql = " SELECT * FROM {pre}zc_project WHERE id = '$goods_id' ";
    $zhongchou = $GLOBALS['db']->getRow($sql);
	$zhongchou['title_img'] = get_image_path($zhongchou['title_img']);
	//项目状态 by wu
    if(time()<$zhongchou['start_time'])
	{
        $zhongchou['zc_status'] = 0;
    }
	elseif(time()>$zhongchou['end_time'])
	{
		$zhongchou['zc_status'] = 2;
	}
	else
	{
        $zhongchou['zc_status'] = 1;
    }
	//项目成功与否 by wu
	if($zhongchou['amount']>$zhongchou['join_money'] && $zhongchou['zc_status']==2)
	{
		$zhongchou['result'] = 1;
	}
	elseif($zhongchou['amount']<$zhongchou['join_money'] && $zhongchou['zc_status']==2)
	{
		$zhongchou['result'] = 2;
	}
	else
	{
		$zhongchou['result'] = 0;
	}

	//百分比
	$zhongchou['baifen_bi'] = round($zhongchou['join_money']/$zhongchou['amount'],2)*100;

    $zhongchou['shenyu_time'] = ceil(($zhongchou['end_time']-time())/3600/24);
    $zhongchou['zw_end_time'] = date('Y年m月d日',$zhongchou['end_time']);
    $zhongchou['star_time'] = date('Y/m/d',$zhongchou['start_time']);
    $zhongchou['end_time'] = date('Y/m/d/s/u',$zhongchou['end_time']);


	return $zhongchou;

}

/**
* 众筹商品详情
 */
function zc_goods($goods_id = 0){
	 $sql = " SELECT `id`,`pid`,`limit`,`backer_num`,`price`,`shipping_fee`,`content`,`img`,`return_time`,`backer_list`,(`limit`-`backer_num`) as shenyu_ren FROM {pre}zc_goods WHERE pid = '$goods_id' ";
     $goods_arr = $GLOBALS['db']->getAll($sql);

	 foreach($goods_arr as $k=>$goods){
		$goods_arr[$k]['img'] = get_image_path($goods['img']);
		if($goods['limit']<0){
			$goods_arr[$k]['wuxian'] = '无限额';
		}

	}

	return $goods_arr;
}


/**
* 众筹项目动态
 */
function zc_progress($goods_id = 0){
	 $sql = " SELECT `id`,`pid`,`progress`,`add_time`,img FROM {pre}zc_progress WHERE pid = '$goods_id' order by id DESC ";
     $goods_arr = $GLOBALS['db']->getAll($sql);

	 foreach($goods_arr as $k=>$goods){
		$goods_arr[$k]['add_time'] = date('Y年m月d日',$goods['add_time']);
		$goods['img']=unserialize($goods['img']);
		if(!empty($goods['img'])){
			foreach($goods['img'] as $k2=>$v2){
				$goods['img'][$k2]=get_image_path($v2);
			}
			$goods_arr[$k]['img']=$goods['img'];
		}
	}
	return $goods_arr;
}
/**
* 众筹项目支持者
 */
function get_backer_list($goods_id = 0){

	//支持者列表
	$sql=" SELECT oi.user_id,oi.add_time,u.user_name,u.nick_name,u.user_picture,zg.price,zg.content ".
		" FROM {pre}order_info as oi ".
		" LEFT JOIN {pre}users as u on u.user_id=oi.user_id ".
		" LEFT JOIN {pre}zc_goods as zg on zg.id=oi.zc_goods_id ".
		" LEFT JOIN {pre}zc_project as zd on zd.id=zg.pid ".
		" WHERE oi.is_zc_order=1 AND oi.pay_status=2 AND zd.id = '$goods_id' ".
		" ORDER BY oi.order_id DESC ";
    $backer_list = $GLOBALS['db']->getAll($sql);
	//补充信息
	foreach($backer_list as $key=>$val)
    {
        $backer_list[$key]['add_time'] =get_time_past($val['add_time'],time());
        if(empty($row['nick_name'])){
            $backer_list[$key]['user_name']= $val['user_name'];
        }else{
            $backer_list[$key]['user_name']= $val['nick_name'];
        }



        $backer_list[$key]['user_picture'] = get_image_path($row['user_picture']);

    }

	return $backer_list;


}

/**
* 众筹项目详情评论
 */
function get_topic_list($goods_id = 0){
	//话题列表
	$sql=" SELECT * FROM {pre}zc_topic WHERE pid='$goods_id' AND parent_topic_id=0 AND topic_status = 1 ".
		" ORDER BY topic_id DESC LIMIT 0,3";
		//" LIMIT ".(($page-1)*$size).",".$size;
	$topic_list = $GLOBALS['db']->getAll($sql);

	//补充信息
	foreach($topic_list as $key=>$val)
	{
		//用户名、头像
		$sql=" select user_name,nick_name,user_picture from {pre}users where user_id=".$val['user_id'];
		$user_info=$GLOBALS['db']->getRow($sql);

        if(empty($row['nick_name'])){
            $topic_list[$key]['user_name']= encrypt_username($user_info['user_name']);
        }else{
            $topic_list[$key]['user_name']= encrypt_username($user_info['nick_name']);
        }

		$topic_list[$key]['user_picture']=get_image_path($user_info['user_picture']);

		//时间的处理
		$topic_list[$key]['time_past']=get_time_past($val['add_time'],gmtime());

		//子评论列表
		$sql=" select * from {pre}zc_topic where parent_topic_id=".$val['topic_id']." AND topic_status = 1 order by topic_id desc limit 5";
		$child_topic=$GLOBALS['db']->getAll($sql);
		if(count($child_topic)>0)
		{
			foreach($child_topic as $k=>$v)
			{
				$sql=" select user_name,user_picture from {pre}users where user_id=".$v['user_id'];
				$child_user_info=$GLOBALS['db']->getRow($sql);
				$child_topic[$k]['user_name']=encrypt_username($child_user_info['user_name']);
				$child_topic[$k]['user_picture']=$child_user_info['user_picture'];
				$child_topic[$k]['time_past']=get_time_past($v['add_time'],time());

				//回复对象
				if($v['reply_topic_id']>0)
				{
					$sql=" select u.user_name from {pre}zc_topic as zt ".
						" left join {pre}users as u on u.user_id=zt.user_id ".
						" where zt.topic_id= ".$v['reply_topic_id']." AND zt.topic_status = 1 ";
					$reply_user_info=$GLOBALS['db']->getRow($sql);
					$child_topic[$k]['reply_user']=encrypt_username($reply_user_info['user_name']);
				}
			}
		}
		$topic_list[$key]['child_topic']=$child_topic;

		//子评论数量
		$sql=" select count(*) from ".$GLOBALS['ecs']->table('zc_topic')." where parent_topic_id=".$val['topic_id']." AND topic_status = 1 order by topic_id desc ";
		$topic_list[$key]['child_topic_num']=$GLOBALS['db']->getOne($sql);
	}
	return $topic_list;
}

/**
* 众筹项目评论列表
 */
function zc_comment_list($id, $size, $page){
    if(empty($id)){
        return false;
    }

    $sql = "SELECT * FROM " .$GLOBALS['ecs']->table('zc_topic'). " WHERE pid = '".$id."'  and topic_status = 1 and parent_topic_id = 0  ORDER BY topic_id DESC ";

    $comment = $GLOBALS['db']->getAll($sql);
	$total = is_array($comment) ? count($comment) : 0;
    $res = $GLOBALS['db']->selectLimit($sql, $size, ($page - 1) * $size);

    $arr = array();
    if($res){

        foreach ($res as $key=>$row) {

            $arr[$row['topic_id']]['id'] = $row['topic_id'];
			$arr[$row['topic_id']]['user_id'] = $row['user_id'];
			//用户名、头像
			$sql=" select user_name,nick_name,user_picture from {pre}users where user_id=".$row['user_id'];
			$user_info=$GLOBALS['db']->getRow($sql);
			if(empty($user_info['nick_name'])){
               $arr[$row['topic_id']]['username']= encrypt_username($user_info['user_name']);
            }else{
                $arr[$row['topic_id']]['username']= encrypt_username($user_info['nick_name']);
            }
			$arr[$row['topic_id']]['user_picture']=get_image_path($user_info['user_picture']);

            $arr[$row['topic_id']]['topic_content'] = str_replace('\r\n', '<br />', htmlspecialchars($row['topic_content']));
            $arr[$row['topic_id']]['add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
			$sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('zc_topic') ." WHERE 	parent_topic_id	= '".$row['topic_id']."'";
            $res = $GLOBALS['db']->getRow($sql);

            $arr[$row['topic_id']]['re_topic_content'] = nl2br(str_replace('\n', '<br />', htmlspecialchars($res['topic_content'])));
            $arr[$row['topic_id']]['re_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $res['add_time']);
                //$arr[$row['	parent_topic_id	']]['re_username'] = $row['user_name'];

        }

        /* 取得已有回复的评论 */
       /*  if ($ids) {
            $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('zc_topic') ." WHERE 	parent_topic_id	 IN( $ids )";
            $res = $GLOBALS['db']->query($sql);
            foreach($res as $row){
                $arr[$row['	parent_topic_id	']]['re_topic_content'] = nl2br(str_replace('\n', '<br />', htmlspecialchars($row['topic_content'])));
                $arr[$row['	parent_topic_id	']]['re_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['add_time']);
                //$arr[$row['	parent_topic_id	']]['re_username'] = $row['user_name'];
            }
        } */

    }
    return array('list'=>array_values($arr), 'totalpage'=>ceil($total/$size));
}


//将时间转化为刚刚、几分钟前等等 by wu
function get_time_past($time=0,$now=0)
{
	$time_past="";

	if($now>=$time)
	{
		//相差时间
		$diff=$now-$time;

		//一分钟内：刚刚
		if($diff>0 && $diff<=60)
		{
			$time_past="刚刚";
		}
		//一小时内：n分钟前
		elseif($diff>60 && $diff<=3600)
		{
			$time_past=floor($diff/60)."分钟前";
		}
		//一天内：n小时前
		elseif($diff>3600 && $diff<=86400)
		{
			$time_past=floor($diff/3600)."小时前";
		}
		//一月内：n天前
		elseif($diff>86400 && $diff<=2592000)
		{
			$time_past=floor($diff/86400)."天前";
		}
		//一年内：n月前
		elseif($diff>2592000 && $diff<=31536000)
		{
			$time_past=floor($diff/2592000)."月前";
		}
		//一年后：n年前
		elseif($diff>31536000)
		{
			$time_past=floor($diff/31536000)."年前";
		}
	}
	else
	{
		$time_past="时间不合法";
	}

	return $time_past;
}


/**
 * 检查收货人信息是否完整
 * @param   array   $consignee  收货人信息
 * @param   int     $flow_type  购物流程类型
 * @return  bool    true 完整 false 不完整
 */
function zc_check_consignee_info($consignee)
{

	/* 如果存在实体商品 */
	$res = !empty($consignee['consignee']) &&(!empty($consignee['tel']) || !empty($consignee['mobile']));

	if ($res)
	{
		if (empty($consignee['province']))
		{
			/* 没有设置省份，检查当前国家下面有没有设置省份 */
			$pro = get_regions(1, $consignee['country']);
			$res = empty($pro);
		}
		elseif (empty($consignee['city']))
		{
			/* 没有设置城市，检查当前省下面有没有城市 */
			$city = get_regions(2, $consignee['province']);
			$res = empty($city);
		}
	}

	return $res;

}


function zc_cart_goods($pid, $id,$number){

	$sql = "SELECT zp.id, zp.title, zp.amount, zp.join_money, zp.title_img ,zp.start_time, zp.end_time, zp.join_num, g.price,g.limit,g.backer_num, g.content  FROM {pre}zc_project as zp left join  {pre}zc_goods as g on g.pid = zp.id  WHERE g.pid = '$pid' and g.id = '$id'";

    $zhongchou = $GLOBALS['db']->getRow($sql);

	$zhongchou['title_img'] = get_image_path($zhongchou['title_img']);
	//项目状态
    if(time()<$zhongchou['start_time'])
	{
        $zhongchou['zc_status'] = 0;
    }
	elseif(time()>$zhongchou['end_time'])
	{
		$zhongchou['zc_status'] = 2;
	}
	else
	{
        $zhongchou['zc_status'] = 1;
    }
	//项目成功与否 by wu
	if($zhongchou['amount']>$zhongchou['join_money'] && $zhongchou['zc_status']==2)
	{
		$zhongchou['result'] = 1;
	}
	elseif($zhongchou['amount']<$zhongchou['join_money'] && $zhongchou['zc_status']==2)
	{
		$zhongchou['result'] = 2;
	}
	else
	{
		$zhongchou['result'] = 0;
	}
	$zhongchou['formated_subtotal'] = $zhongchou['price']*$number;//商品总价
	$zhongchou['number'] = $number;
	//百分比
	$zhongchou['baifen_bi'] = round($zhongchou['join_money']/$zhongchou['amount'],2)*100;

    $zhongchou['shenyu_time'] = ceil(($zhongchou['end_time']-time())/3600/24);
    $zhongchou['zw_end_time'] = date('Y年m月d日',$zhongchou['end_time']);
    $zhongchou['star_time'] = date('Y/m/d',$zhongchou['start_time']);
    $zhongchou['end_time'] = date('Y/m/d/s/u',$zhongchou['end_time']);


	return $zhongchou;


}


//提交订单配送方式
function zc_get_order_shipping_fee($goods, $consignee,$shipping){

	$consignee = isset($_SESSION['flow_consignee']) ? $_SESSION['flow_consignee'] : $consignee;
    $region            = array($consignee['country'], $consignee['province'], $consignee['city'], $consignee['district']);
    if($shipping){
		$sql = 'SELECT s.shipping_id, s.shipping_code, s.shipping_name, ' .
					's.shipping_desc, s.insure, s.support_cod, a.configure ' .
				'FROM ' . $GLOBALS['ecs']->table('shipping') . ' AS s, ' .
					$GLOBALS['ecs']->table('shipping_area') . ' AS a, ' .
					$GLOBALS['ecs']->table('area_region') . ' AS r ' .
				'WHERE r.region_id ' . db_create_in($region) .
	   " AND r.shipping_area_id = a.shipping_area_id AND a.shipping_id = s.shipping_id AND s.enabled = 1 AND a.ru_id = '$ru_id' and s.shipping_id = '$shipping' ORDER BY s.shipping_order"; //by wu
		$shipping_list = $GLOBALS['db']->getAll($sql);

	   foreach($shipping_list as $key=>$val) {
			if ($GLOBALS['_CFG']['freight_model'] == 0) {

				$shipping_cfg = unserialize_config($val['configure']);
				$shipping_fee = ($shipping_count == 0 AND $cart_weight_price['free_shipping'] == 1) ? 0 : shipping_fee($val['shipping_code'], unserialize($val['configure']),
					$cart_weight_price['weight'], $cart_weight_price['amount'], $cart_weight_price['number']);

				$shipping_list[$key]['free_money'] = price_format($shipping_cfg['free_money'], false);
			} elseif ($GLOBALS['_CFG']['freight_model'] == 1) {


				$shipping_cfg = unserialize_config($val['configure']);

				$shipping_fee = goods_shipping_fee($val['shipping_code'],  unserialize($val['configure']), 0, $goods['price'], $goods['number']);

				return $shipping_fee;
			}


		}
    }

}



/*
 * 查询商家默认配送方式
 */
function zc_get_ru_shippng_info($cart_goods, $consignee = ''){


    if(!empty($_SESSION['user_id'])){
            $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    }else{
            $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    $order = flow_order_info();

    $seller_shipping = get_seller_shipping_type($ru_id);
    $shipping_id = $seller_shipping['shipping_id'];

    $consignee = isset($_SESSION['flow_consignee']) ? $_SESSION['flow_consignee'] : $consignee;
    $region            = array($consignee['country'], $consignee['province'], $consignee['city'], $consignee['district']);
    $cart_weight_price = cart_weight_price($flow_type, $cart_value); //ecmoban模板堂 --zhuo
    $insure_disabled   = true;
    $cod_disabled      = true;

    $sql = 'SELECT s.shipping_id, s.shipping_code, s.shipping_name, ' .
                's.shipping_desc, s.insure, s.support_cod, a.configure ' .
            'FROM ' . $GLOBALS['ecs']->table('shipping') . ' AS s, ' .
                $GLOBALS['ecs']->table('shipping_area') . ' AS a, ' .
                $GLOBALS['ecs']->table('area_region') . ' AS r ' .
            'WHERE r.region_id ' . db_create_in($region) .
   " AND r.shipping_area_id = a.shipping_area_id AND a.shipping_id = s.shipping_id AND s.enabled = 1 AND a.ru_id = '$ru_id' ORDER BY s.shipping_order"; //by wu
    $shipping_list = $GLOBALS['db']->getAll($sql);

    foreach($shipping_list as $key=>$val) {
        if ($GLOBALS['_CFG']['freight_model'] == 0) {

            $shipping_cfg = unserialize_config($val['configure']);
            $shipping_fee = ($shipping_count == 0 AND $cart_weight_price['free_shipping'] == 1) ? 0 : shipping_fee($val['shipping_code'], unserialize($val['configure']),
                $cart_weight_price['weight'], $cart_weight_price['amount'], $cart_weight_price['number']);

            $shipping_list[$key]['free_money'] = price_format($shipping_cfg['free_money'], false);
        } elseif ($GLOBALS['_CFG']['freight_model'] == 1) {
            $goods_region = array(
                'country' => $region[0],
                'province' => $region[1],
                'city' => $region[2],
                'district' => $region[3]
            );


            /* $shippingFee = get_goods_order_shipping_fee($cart_goods, $goods_region, $val['shipping_id']);
            $shipping_fee = $shippingFee['shipping_fee'];
            $shippingFee['free_money'] = isset($shippingFee['free_money']) ? $shippingFee['free_money'] : 0;
            $shipping_list[$key]['free_money']          = price_format($shippingFee['free_money'], false); */

			$shipping_cfg = unserialize_config($val['configure']);

			$shipping_fee = goods_shipping_fee($val['shipping_code'],  unserialize($val['configure']), 0, $cart_goods['price'], $cart_goods['number']);

            $free_money = isset($shipping_cfg['free_money']) ? $shipping_cfg['free_money'] : 0;
            $shipping_list[$key]['free_money']          = price_format(shipping_cfg, false);
        }

        $shipping_list[$key]['shipping_id']        = $val['shipping_id'];
        $shipping_list[$key]['shipping_name']        = $val['shipping_name'];
        $shipping_list[$key]['shipping_code']        = $val['shipping_code'];
        $shipping_list[$key]['format_shipping_fee'] = price_format($shipping_fee, false);
        $shipping_list[$key]['shipping_fee']        = $shipping_fee;
        $shipping_list[$key]['insure_formated']     = strpos($val['insure'], '%') === false ? price_format($val['insure'], false) : $val['insure'];
        /* 当前的配送方式是否支持保价 */
        if ($val['shipping_id'] == $order['shipping_id']) {
            $insure_disabled = ($val['insure'] == 0);
            $cod_disabled = ($val['support_cod'] == 0);
        }
        //默认配送方式
        $shipping_list[$key]['default'] = 0;
        if($shipping_id == $val['shipping_id'])
        {
            $shipping_list[$key]['default'] = 1;
        }
        $shipping_list[$key]['insure_disabled']     = $insure_disabled;
        $shipping_list[$key]['cod_disabled']        = $cod_disabled;
    }
    //去掉重复配送方式 by wu start
    $shipping_type = array();
    foreach($shipping_list as $key=>$val)
    {
        $shipping_type[$val['shipping_code']][] = $key;
    }
    foreach($shipping_type as $key=>$val)
    {
        if(count($val)>1)
        {
            for($i = 1; $i<count($val); $i++)
            {
                unset($shipping_list[$val[$i]]);
            }
        }
    }

    //去掉重复配送方式 by wu end
    return $shipping_list;
}

/**
 * 获得订单中的费用信息
 *
 * @access  public
 * @param   array   $order
 * @param   array   $goods
 * @param   array   $consignee
 * @param   bool    $is_gb_deposit  是否团购保证金（如果是，应付款金额只计算商品总额和支付费用，可以获得的积分取 $gift_integral）
 * @return  array
 */
function zc_order_fee($order, $goods, $consignee)
{
	$consignee = isset($_SESSION['flow_consignee']) ? $_SESSION['flow_consignee'] : $consignee;
    $region            = array($consignee['country'], $consignee['province'], $consignee['city'], $consignee['district']);

    $total  = array('real_goods_count' => 0,
                    'gift_amount'      => 0,
                    'goods_price'      => 0,
                    'market_price'     => 0,
                    'discount'         => 0,
                    'pack_fee'         => 0,
                    'card_fee'         => 0,
                    'shipping_fee'     => 0,
                    'shipping_insure'  => 0,
                    'integral_money'   => 0,
                    'bonus'            => 0,
                    'surplus'          => 0,
                    'cod_fee'          => 0,
                    'pay_fee'          => 0,
                    'tax'              => 0,
                    'presale_price'    => 0
                    );
    $weight = 0;

    /* 商品总价 */

    $arr = array();

	/* 商品总价 */
	$cat_goods = array(0=>$goods);
	foreach ($cat_goods AS $val) {
		$total['goods_price'] += $val['price'] * $val['number'];
	}

    $total['saving']    = $total['market_price'] - $total['goods_price'];
    $total['save_rate'] = $total['market_price'] ? round($total['saving'] * 100 / $total['market_price']) . '%' : 0;

    $total['goods_price_formated']  = price_format($total['goods_price'], false);
    $total['market_price_formated'] = price_format($total['market_price'], false);
    $total['saving_formated']       = price_format($total['saving'], false);

    /* 折扣 */
    if ($order['extension_code'] != 'group_buy')
    {
        $discount = compute_discount(3, $cart_value);
        $total['discount'] = $discount['discount'];
        if ($total['discount'] > $total['goods_price'])
        {
            $total['discount'] = $total['goods_price'];
        }
    }
    $total['discount_formated'] = price_format($total['discount'], false);

    /* 税额 */
    //$total['tax'] = get_order_invoice_total($total['goods_price'], $order['inv_content']);
    //$total['tax_formated'] = price_format($total['tax'], false);
    if (!empty($order['need_inv']) && $order['inv_type'] != '')
    {
        /* 查税率 */
        $rate = 0;
        foreach ($GLOBALS['_CFG']['invoice_type']['type'] as $key => $type)
        {
            if ($type == $order['inv_type'])
            {
                $rate = floatval($GLOBALS['_CFG']['invoice_type']['rate'][$key]) / 100;
                break;
            }
        }
        if ($rate > 0)
        {
            $total['tax'] = $rate * $total['goods_price'];
        }
    }
    $total['tax_formated'] = price_format($total['tax'], false);

    /* 包装费用 */
    if (!empty($order['pack_id']))
    {
        $total['pack_fee']      = pack_fee($order['pack_id'], $total['goods_price']);
    }
    $total['pack_fee_formated'] = price_format($total['pack_fee'], false);

    /* 贺卡费用 */
    if (!empty($order['card_id']))
    {
        $total['card_fee']      = card_fee($order['card_id'], $total['goods_price']);
    }
    $total['card_fee_formated'] = price_format($total['card_fee'], false);

    /* 红包 */

    if (!empty($order['bonus_id']))
    {
        $bonus          = bonus_info($order['bonus_id']);
        $total['bonus'] = $bonus['type_money'];
        $total['admin_id'] = $bonus['admin_id']; //ecmoban模板堂 --zhuo
    }

    $total['bonus_formated'] = price_format($total['bonus'], false);

    /* 线下红包 */
    if (!empty($order['bonus_kill']))
    {
        $bonus = bonus_info(0,$order['bonus_kill']);
        $total['bonus_kill'] = $order['bonus_kill'];
        $total['bonus_kill_formated'] = price_format($total['bonus_kill'], false);
    }

    /* 配送费用 */
    $shipping_cod_fee = NULL;

	//$total['shipping_fee'] = get_order_shipping_fee($cart_goods_list);
	$shopping = zc_get_order_shipping_fee($goods, $consignee,$order['shipping_id']);
	$total['shipping_fee'] = $shopping;
	//by wu end

    $total['shipping_fee_formated']    = price_format($total['shipping_fee'], false);
    $total['shipping_insure_formated'] = price_format($total['shipping_insure'], false);


    /* 计算订单总额 */

	$total['amount'] = $total['goods_price'] - $total['discount'] + $total['tax'] + $total['pack_fee'] + $total['card_fee'] +
		$total['shipping_fee'] + $total['shipping_insure'] + $total['cod_fee'];

	// 减去红包金额  //红包支付，如果红包的金额大于订单金额 则去订单金额定义为红包金额的最终结果(相当于订单金额减去本身的金额，为0) ecmoban模板堂 --zhuo
	$use_bonus        = min($total['bonus'], $max_amount); // 实际减去的红包金额
	if(isset($total['bonus_kill']))
	{
		$use_bonus_kill   = min($total['bonus_kill'], $max_amount);
		$total['amount'] -=  $price = number_format($total['bonus_kill'], 2, '.', ''); // 还需要支付的订单金额
	}

	$total['bonus']   = $use_bonus;
	$total['bonus_formated'] = price_format($total['bonus'], false);
	$total['amount'] -= $use_bonus; // 还需要支付的订单金额
	$max_amount      -= $use_bonus; // 积分最多还能支付的金额




    /* 余额 */
    $order['surplus'] = $order['surplus'] > 0 ? $order['surplus'] : 0;
    if ($total['amount'] > 0)
    {
        if (isset($order['surplus']) && $order['surplus'] > $total['amount'])
        {
            $order['surplus'] = $total['amount'];
            $total['amount']  = 0;
        }
        else
        {
            $total['amount'] -= floatval($order['surplus']);
        }
    }
    else
    {
        $order['surplus'] = 0;
        $total['amount']  = 0;
    }
    $total['surplus'] = $order['surplus'];
    $total['surplus_formated'] = price_format($order['surplus'], false);

    /* 积分 */
    $order['integral'] = $order['integral'] > 0 ? $order['integral'] : 0;
    if ($total['amount'] > 0 && $max_amount > 0 && $order['integral'] > 0)
    {
        $integral_money = value_of_integral($order['integral']);

        // 使用积分支付
        $use_integral            = min($total['amount'], $max_amount, $integral_money); // 实际使用积分支付的金额
        $total['amount']        -= $use_integral;
        $total['integral_money'] = $use_integral;
        $order['integral']       = integral_of_value($use_integral);
    }
    else
    {
        $total['integral_money'] = 0;
        $order['integral']       = 0;
    }
    $total['integral'] = $order['integral'];
    $total['integral_formated'] = price_format($total['integral_money'], false);

    /* 保存订单信息 */
    $_SESSION['flow_order'] = $order;

    $se_flow_type = isset($_SESSION['flow_type']) ? $_SESSION['flow_type'] : '';

    /* 支付费用 */
    if (!empty($order['pay_id']) && ($total['real_goods_count'] > 0 || $se_flow_type != CART_EXCHANGE_GOODS))
    {
        $total['pay_fee']      = pay_fee($order['pay_id'], $total['amount'], $shipping_cod_fee);
    }

    $total['pay_fee_formated'] = price_format($total['pay_fee'], false);

    $total['amount']           += $total['pay_fee']; // 订单总额累加上支付费用
    $total['amount_formated']  = price_format($total['amount'], false);

    /* 取得可以得到的积分和红包 */
    if ($order['extension_code'] == 'group_buy')
    {
        $total['will_get_integral'] = $group_buy['gift_integral'];
    }
    elseif ($order['extension_code'] == 'exchange_goods')
    {
        $total['will_get_integral'] = 0;
    }
    else
    {
        $total['will_get_integral'] = get_give_integral($goods, $cart_value); //ecmoban模板堂 --zhuo
    }

    $total['will_get_bonus']        = $order['extension_code'] == 'exchange_goods' ? 0 : price_format(get_total_bonus(), false);
    $total['formated_goods_price']  = price_format($total['goods_price'], false);
    $total['formated_market_price'] = price_format($total['market_price'], false);
    $total['formated_saving']       = price_format($total['saving'], false);

    if ($order['extension_code'] == 'exchange_goods')
    {
        $sql = 'SELECT SUM(eg.exchange_integral * c.goods_number) '.
               'FROM ' . $GLOBALS['ecs']->table('cart') . ' AS c,' . $GLOBALS['ecs']->table('exchange_goods') . 'AS eg '.
               "WHERE c.goods_id = eg.goods_id AND " . $c_sess .
               "  AND c.rec_type = '" . CART_EXCHANGE_GOODS . "' " .
               '  AND c.is_gift = 0 AND c.goods_id > 0 AND eg.review_status = 3 ' .
               'GROUP BY eg.goods_id';
        $exchange_integral = $GLOBALS['db']->getOne($sql);
        $total['exchange_integral'] = $exchange_integral;
    }

    return $total;
}














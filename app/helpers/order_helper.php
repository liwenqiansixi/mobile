<?php

defined('IN_ECTOUCH') or die('Deny Access');

/**
 * 取得已安装的配送方式
 * @return  array   已安装的配送方式
 */
function shipping_list() {
    $sql = 'SELECT shipping_id, shipping_name ' .
            'FROM ' . $GLOBALS['ecs']->table('shipping') .
            ' WHERE enabled = 1';

    return $GLOBALS['db']->getAll($sql);
}

/**
 * 取得配送方式信息
 * @param   int     $shipping_id    配送方式id
 * @return  array   配送方式信息
 */
function shipping_info($shipping_id) {
    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('shipping') .
            " WHERE shipping_id = '$shipping_id' " .
            'AND enabled = 1';

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 取得某配送方式对应于某收货地址的区域信息
 * @param   int     $shipping_id        配送方式id
 * @param   array   $region_id_list     收货人地区id数组
 * @return  array   配送区域信息（config 对应着反序列化的 configure）
 */
function shipping_area_info($shipping_id, $region_id_list) {
    $sql = 'SELECT s.shipping_code, s.shipping_name, ' .
            's.shipping_desc, s.insure, s.support_cod, a.configure ' .
            'FROM ' . $GLOBALS['ecs']->table('shipping') . ' AS s, ' .
            $GLOBALS['ecs']->table('shipping_area') . ' AS a, ' .
            $GLOBALS['ecs']->table('area_region') . ' AS r ' .
            "WHERE s.shipping_id = '$shipping_id' " .
            'AND r.region_id ' . db_create_in($region_id_list) .
            ' AND r.shipping_area_id = a.shipping_area_id AND a.shipping_id = s.shipping_id AND s.enabled = 1';
    $row = $GLOBALS['db']->getRow($sql);

    if (!empty($row)) {
        $shipping_config = unserialize_config($row['configure']);
        if (isset($shipping_config['pay_fee'])) {
            if (strpos($shipping_config['pay_fee'], '%') !== false) {
                $row['pay_fee'] = floatval($shipping_config['pay_fee']) . '%';
            } else {
                $row['pay_fee'] = floatval($shipping_config['pay_fee']);
            }
        } else {
            $row['pay_fee'] = 0.00;
        }
    }

    return $row;
}

/**
 * 获取指定配送的保价费用
 *
 * @access  public
 * @param   string      $shipping_code  配送方式的code
 * @param   float       $goods_amount   保价金额
 * @param   mix         $insure         保价比例
 * @return  float
 */
function shipping_insure_fee($shipping_code, $goods_amount, $insure) {
    if (strpos($insure, '%') === false) {
        /* 如果保价费用不是百分比则直接返回该数值 */
        return floatval($insure);
    } else {
        $path = ADDONS_PATH . 'shipping/' . $shipping_code . '.php';

        if (file_exists($path)) {
            include_once($path);

            $shipping = new $shipping_code;
            $insure = floatval($insure) / 100;

            if (method_exists($shipping, 'calculate_insure')) {
                return $shipping->calculate_insure($goods_amount, $insure);
            } else {
                return ceil($goods_amount * $insure);
            }
        } else {
            return false;
        }
    }
}

/**
 * 取得已安装的支付方式列表
 * @return  array   已安装的配送方式列表
 */
function payment_list() {
    $sql = 'SELECT pay_id, pay_name ' .
            'FROM ' . $GLOBALS['ecs']->table('payment') .
            ' WHERE enabled = 1';

    return $GLOBALS['db']->getAll($sql);
}

/**
 * 取得支付方式信息
 * @param   int     $pay_id     支付方式id
 * @return  array   支付方式信息
 */
function payment_info($pay_id) {
    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('payment') .
            " WHERE pay_id = '$pay_id' AND enabled = 1";

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 获得订单需要支付的支付费用
 *
 * @access  public
 * @param   integer $payment_id
 * @param   float   $order_amount
 * @param   mix     $cod_fee
 * @return  float
 */
function pay_fee($payment_id, $order_amount, $cod_fee = null) {
    $pay_fee = 0;
    $payment = payment_info($payment_id);
    $rate = ($payment['is_cod'] && !is_null($cod_fee)) ? $cod_fee : $payment['pay_fee'];

    if (strpos($rate, '%') !== false) {
        /* 支付费用是一个比例 */
        $val = floatval($rate) / 100;
        $pay_fee = $val > 0 ? $order_amount * $val / (1 - $val) : 0;
    } else {
        $pay_fee = floatval($rate);
    }

    return round($pay_fee, 2);
}

/**
 * 取得可用的支付方式列表
 * @param   bool    $support_cod        配送方式是否支持货到付款
 * @param   int     $cod_fee            货到付款手续费（当配送方式支持货到付款时才传此参数）
 * @param   int     $is_online          是否支持在线支付
 * @return  array   配送方式数组
 */
function available_payment_list($support_cod, $cod_fee = 0, $is_online = false) {
    $sql = 'SELECT pay_id, pay_code, pay_name, pay_fee, pay_desc, pay_config, is_cod' .
            ' FROM ' . $GLOBALS['ecs']->table('payment') .
            ' WHERE enabled = 1 ';
    if (!$support_cod) {
        $sql .= 'AND is_cod = 0 '; // 如果不支持货到付款
    }
    if ($is_online) {
        $sql .= "AND is_online = '1' ";
    }
    $sql .= 'ORDER BY pay_order'; // 排序
    $res = $GLOBALS['db']->query($sql);

    $pay_list = array();
    foreach ($res as $row) {
        if ($row['is_cod'] == '1') {
            $row['pay_fee'] = $cod_fee;
        }

        $row['format_pay_fee'] = strpos($row['pay_fee'], '%') !== false ? $row['pay_fee'] :
                price_format($row['pay_fee'], false);
        $modules[] = $row;
    }

    include_once(BASE_PATH . 'helpers/compositor.php');
    if (isset($modules)) {
        return $modules;
    }
}

/**
 * 取得包装列表
 * @return  array   包装列表
 */
function pack_list() {
    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('pack');
    $res = $GLOBALS['db']->query($sql);

    $list = array();
    foreach ($res as $row) {
        $row['format_pack_fee'] = price_format($row['pack_fee'], false);
        $row['format_free_money'] = price_format($row['free_money'], false);
        $list[] = $row;
    }

    return $list;
}

/**
 * 取得包装信息
 * @param   int     $pack_id    包装id
 * @return  array   包装信息
 */
function pack_info($pack_id) {
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('pack') .
            " WHERE pack_id = '$pack_id'";

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 根据订单中的商品总额来获得包装的费用
 *
 * @access  public
 * @param   integer $pack_id
 * @param   float   $goods_amount
 * @return  float
 */
function pack_fee($pack_id, $goods_amount) {
    $pack = pack_info($pack_id);

    $val = (floatval($pack['free_money']) <= $goods_amount && $pack['free_money'] > 0) ? 0 : floatval($pack['pack_fee']);

    return $val;
}

/**
 * 取得贺卡列表
 * @return  array   贺卡列表
 */
function card_list() {
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('card');
    $res = $GLOBALS['db']->query($sql);

    $list = array();
    foreach ($res as $row) {
        $row['format_card_fee'] = price_format($row['card_fee'], false);
        $row['format_free_money'] = price_format($row['free_money'], false);
        $list[] = $row;
    }

    return $list;
}

/**
 * 取得贺卡信息
 * @param   int     $card_id    贺卡id
 * @return  array   贺卡信息
 */
function card_info($card_id) {
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('card') .
            " WHERE card_id = '$card_id'";

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 根据订单中商品总额获得需要支付的贺卡费用
 *
 * @access  public
 * @param   integer $card_id
 * @param   float   $goods_amount
 * @return  float
 */
function card_fee($card_id, $goods_amount) {
    $card = card_info($card_id);

    return ($card['free_money'] <= $goods_amount && $card['free_money'] > 0) ? 0 : $card['card_fee'];
}

/**
 * 取得订单信息
 * @param   int     $order_id   订单id（如果order_id > 0 就按id查，否则按sn查）
 * @param   string  $order_sn   订单号
 * @return  array   订单信息（金额都有相应格式化的字段，前缀是formated_）
 */
function order_info($order_id, $order_sn = '') {
    /* 计算订单各种费用之和的语句 */
    $total_fee = " (goods_amount - discount + tax + shipping_fee + insure_fee + pay_fee + pack_fee + card_fee) AS total_fee ";
    $order_id = intval($order_id);
    if ($order_id > 0) {
        $sql = "SELECT *, " . $total_fee . " FROM " . $GLOBALS['ecs']->table('order_info') .
                " WHERE order_id = '$order_id'";
    } else {
        $sql = "SELECT *, " . $total_fee . "  FROM " . $GLOBALS['ecs']->table('order_info') .
                " WHERE order_sn = '$order_sn'";
    }
    $order = $GLOBALS['db']->getRow($sql);
    $os = L('os');

    //确认收货按钮
    /* if ($order['order_status'] == OS_UNCONFIRMED) {
      $order['handler'] = "<a href=\"".url('user/order/cancel',array('order_id'=>$order['order_id'])) . "\" onclick=\"if (!confirm('" . L('confirm_cancel') . "')) return false;\">" . L('cancel') . "</a>";
      }
      else if ($order['order_status'] == OS_SPLITED) {
      //对配送状态的处理
      if ($order['shipping_status'] == SS_SHIPPED ) {

      @$order['handler'] = "<a href=\"".url('user/order/affirmreceived',array('order_id'=>$order['order_id'])). "\" onclick=\"if (!confirm('" . L('confirm_received') . "')) return false;\">" .L('received') . "</a>";
      } elseif ($order['shipping_status'] == SS_RECEIVED) {

      @$order['handler'] = '<span style="color:red">' . L('ss_received') . '</span>';
      } else {

      if ($order['pay_status'] == PS_UNPAYED) {

      @$order['handler'] = "<a href=\"".url('user/order/detail',array('order_id'=>$order['order_id'])). '">' . L('view_order') . '</a>';
      } else {

      @$order['handler'] = "<a href=\"".url('user/order/detail',array('order_id'=>$order['order_id'])) . '">' . L('view_order') . '</a>';
      }
      }
      }
      else {
      $order['handler'] = '<span style="color:red">' . $os[$order['order_status']]. '</span>';
      } */

    /* 格式化金额字段 */
    if ($order) {
        $order['formated_goods_amount'] = price_format($order['goods_amount'], false);
        $order['formated_discount'] = price_format($order['discount'], false);
        $order['formated_tax'] = price_format($order['tax'], false);
        $order['formated_shipping_fee'] = price_format($order['shipping_fee'], false);
        $order['formated_insure_fee'] = price_format($order['insure_fee'], false);
        $order['formated_pay_fee'] = price_format($order['pay_fee'], false);
        $order['formated_pack_fee'] = price_format($order['pack_fee'], false);
        $order['formated_card_fee'] = price_format($order['card_fee'], false);
        $order['formated_total_fee'] = price_format($order['total_fee'], false);
        $order['formated_money_paid'] = price_format($order['money_paid'], false);
        $order['formated_bonus'] = price_format($order['bonus'], false);
        $order['formated_integral_money'] = price_format($order['integral_money'], false);
        $order['formated_surplus'] = price_format($order['surplus'], false);
        $order['formated_order_amount'] = price_format(abs($order['order_amount']), false);
        $order['formated_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $order['add_time']);
    }
    return $order;
}

/**
 * 判断订单是否已完成
 * @param   array   $order  订单信息
 * @return  bool
 */
function order_finished($order) {
    return $order['order_status'] == OS_CONFIRMED &&
            ($order['shipping_status'] == SS_SHIPPED || $order['shipping_status'] == SS_RECEIVED) &&
            ($order['pay_status'] == PS_PAYED || $order['pay_status'] == PS_PAYING);
}

/**
 * 取得订单商品
 * @param   int     $order_id   订单id
 * @return  array   订单商品数组
 */
function order_goods($order_id) {
    $sql = "SELECT og.rec_id, og.goods_id, og.ru_id, og.goods_name, og.goods_sn, og.market_price, og.goods_number, og.warehouse_id, " .
            "og.goods_price, og.goods_attr, og.is_real, og.parent_id, og.is_gift, " .
            "og.goods_price * og.goods_number AS subtotal, og.extension_code,g.shop_price " .
            "FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON og.goods_id = g.goods_id " .
            " WHERE og.order_id = '$order_id'";

    $res = $GLOBALS['db']->query($sql);
    $goods_list = array();
    foreach ($res as $row) {
        $sql = "select goods_img, goods_thumb from " . $GLOBALS['ecs']->table('goods') . " where goods_id = '" . $row['goods_id'] . "'";
        $goods = $GLOBALS['db']->getRow($sql);

        if ($row['extension_code'] == 'package_buy') {
            $row['package_goods_list'] = get_package_goods($row['goods_id']);

            $sql = "SELECT activity_thumb FROM {pre}goods_activity WHERE act_id =" . $row['goods_id'] . ' and is_finished = 0 and review_status = 3';
            $activity_thumb = $GLOBALS['db']->getRow($sql);
            $row['goods_thumb'] = get_image_path($activity_thumb['activity_thumb']);
        } else {
            $row['goods_thumb'] = get_image_path($goods['goods_thumb']);
        }
        /* 修正商品图片 */
        $row['goods_img'] = get_image_path($goods['goods_img']);

        //ecmoban模板堂 --zhuo
        $row['warehouse_name'] = $GLOBALS['db']->getOne("select region_name from " . $GLOBALS['ecs']->table('region_warehouse') . " where region_id = '" . $row['warehouse_id'] . "'");
        //ecmoban模板堂 --zhuo start 商品金额促销
        $row['goods_amount'] = $row['goods_price'] * $row['goods_number'];
        $goods_con = get_con_goods_amount($row['goods_amount'], $row['goods_id'], 0, 0, $row['parent_id']);

        $goods_con['amount'] = explode(',', $goods_con['amount']);
        $row['amount'] = min($goods_con['amount']);

        $row['dis_amount'] = $row['goods_amount'] - $row['amount'];
        $row['discount_amount'] = price_format($row['dis_amount'], false);
        //ecmoban模板堂 --zhuo end 商品金额促销
        //订单表extension_id---活动Id
        $extension_id = $GLOBALS['db']->getOne("SELECT extension_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_id'");

        if ($row['extension_code'] == "presale" && !empty($extension_id)) {
            $row['url'] = build_uri('presale', array('presaleid' => $extension_id), $row['goods_name']);
        } elseif ($row['extension_code'] == "package_buy") {
            $row['url'] = url('package/index/index');
        } else {
            $row['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);
        }
        $row['shop_name'] = get_shop_name($row['ru_id'], 1); //店铺名称
        $row['shopUrl'] = url('store/index/index', array('id' => $row['ru_id']));
        $goods_list[] = $row;
    }

    return $goods_list;
}

/**
 * 取得订单总金额
 * @param   int     $order_id   订单id
 * @param   bool    $include_gift   是否包括赠品
 * @return  float   订单总金额
 */
function order_amount($order_id, $include_gift = true) {
    $sql = "SELECT SUM(goods_price * goods_number) " .
            "FROM " . $GLOBALS['ecs']->table('order_goods') .
            " WHERE order_id = '$order_id'";
    if (!$include_gift) {
        $sql .= " AND is_gift = 0";
    }

    return floatval($GLOBALS['db']->getOne($sql));
}

/**
 * 取得某订单商品总重量和总金额（对应 cart_weight_price）
 * @param   int     $order_id   订单id
 * @return  array   ('weight' => **, 'amount' => **, 'formated_weight' => **)
 */
function order_weight_price($order_id) {
    $sql = "SELECT SUM(g.goods_weight * o.goods_number) AS weight, " .
            "SUM(o.goods_price * o.goods_number) AS amount ," .
            "SUM(o.goods_number) AS number " .
            "FROM " . $GLOBALS['ecs']->table('order_goods') . " AS o, " .
            $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE o.order_id = '$order_id' " .
            "AND o.goods_id = g.goods_id";

    $row = $GLOBALS['db']->getRow($sql);
    $row['weight'] = floatval($row['weight']);
    $row['amount'] = floatval($row['amount']);
    $row['number'] = intval($row['number']);

    /* 格式化重量 */
    $row['formated_weight'] = formated_weight($row['weight']);

    return $row;
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
function order_fee($order, $goods, $consignee, $type = 0, $cart_value = '', $pay_type = 0, $cart_goods_list = '', $warehouse_id = 0, $area_id = 0) {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    /* 初始化订单的扩展code */
    if (!isset($order['extension_code'])) {
        $order['extension_code'] = '';
    }

    if ($order['extension_code'] == 'group_buy') {
        $group_buy = group_buy_info($order['extension_id']);
    }
    if ($order['extension_code'] == 'presale') {
        $presale = presale_info($order['extension_id']);
    }

    $total = array('real_goods_count' => 0,
        'gift_amount' => 0,
        'goods_price' => 0,
        'market_price' => 0,
        'discount' => 0,
        'pack_fee' => 0,
        'card_fee' => 0,
        'shipping_fee' => 0,
        'shipping_insure' => 0,
        'integral_money' => 0,
        'bonus' => 0,
        'coupons' => 0, //优惠券 bylu
        'surplus' => 0,
        'cod_fee' => 0,
        'pay_fee' => 0,
        'tax' => 0,
        'presale_price' => 0
    );
    $weight = 0;

    /* 商品总价 */

    $arr = array();
    foreach ($goods AS $key => $val) {
        /* 统计实体商品的个数 */
        if ($val['is_real']) {
            $total['real_goods_count'] ++;
        }
        //ecmoban模板堂 --zhuo start 商品金额促销
        $arr[$key]['goods_amount'] = $val['goods_price'] * $val['goods_number'];
        $goods_con = get_con_goods_amount($arr[$key]['goods_amount'], $val['goods_id'], 0, 0, $val['parent_id']);

        $goods_con['amount'] = explode(',', $goods_con['amount']);
        $arr[$key]['amount'] = min($goods_con['amount']);

        $total['goods_price'] += $arr[$key]['amount'];
        //ecmoban模板堂 --zhuo end 商品金额促销
        if (isset($val['deposit']) && $val['deposit'] > 0 && $val['rec_type'] == CART_PRESALE_GOODS) {
            $total['presale_price'] += $val['deposit'] * $val['goods_number']; //预售定金
        }
        $total['market_price'] += $val['market_price'] * $val['goods_number'];
    }

    $total['saving'] = $total['market_price'] - $total['goods_price'];
    $total['save_rate'] = $total['market_price'] ? round($total['saving'] * 100 / $total['market_price']) . '%' : 0;

    $total['goods_price_formated'] = price_format($total['goods_price'], false);
    $total['market_price_formated'] = price_format($total['market_price'], false);
    $total['saving_formated'] = price_format($total['saving'], false);

    /* 折扣 */
    if ($order['extension_code'] != 'group_buy') {
        $discount = compute_discount(3, $cart_value);
        $total['discount'] = $discount['discount'];
        if ($total['discount'] > $total['goods_price']) {
            $total['discount'] = $total['goods_price'];
        }
    }
    $total['discount_formated'] = price_format($total['discount'], false);
    /* 税额 */
    // if($GLOBALS['_CFG']['can_invoice'] == 1){
    //     $total['tax'] = get_order_invoice_total($total['goods_price'], $order['inv_content']);
    // }else{
    //     $total['tax'] = 0;
    // }
    if (!empty($order['need_inv']) && $order['inv_type'] != '') {
        /* 查税率 */
        $rate = 0;
        foreach ($GLOBALS['_CFG']['invoice_type']['type'] as $key => $type) {
            if ($type == $order['inv_type']) {
                $rate = floatval($GLOBALS['_CFG']['invoice_type']['rate'][$key]) / 100;
                break;
            }
        }
        if ($rate > 0) {
            $total['tax'] = $rate * $total['goods_price'];
        }
    }
    $total['tax_formated'] = price_format($total['tax'], false);

    /* 包装费用 */
    if (!empty($order['pack_id'])) {
        $total['pack_fee'] = pack_fee($order['pack_id'], $total['goods_price']);
    }
    $total['pack_fee_formated'] = price_format($total['pack_fee'], false);

    /* 贺卡费用 */
    if (!empty($order['card_id'])) {
        $total['card_fee'] = card_fee($order['card_id'], $total['goods_price']);
    }
    $total['card_fee_formated'] = price_format($total['card_fee'], false);

    /* 红包 */

    if (!empty($order['bonus_id'])) {
        $bonus = bonus_info($order['bonus_id']);
        $total['bonus'] = $bonus['type_money'];
        $total['admin_id'] = $bonus['admin_id']; //ecmoban模板堂 --zhuo
    }

    $total['bonus_formated'] = price_format($total['bonus'], false);

    /* 线下红包 */
    if (!empty($order['bonus_kill'])) {
        $bonus = bonus_info(0, $order['bonus_kill']);
        $total['bonus_kill'] = $order['bonus_kill'];
        $total['bonus_kill_formated'] = price_format($total['bonus_kill'], false);
    }

    /* 优惠券 bylu */

    if (!empty($order['uc_id'])) {
        $coupons = get_coupons_cpy($order['uc_id']);
        $total['coupons'] = $coupons['cou_money']; // 优惠券面值 bylu
    }
    $total['coupons_formated'] = price_format($total['coupons'], false);

    /* 配送费用 */
    $shipping_cod_fee = NULL;
    if ($order['shipping_id'] > 0 && $total['real_goods_count'] > 0) {
        $total['shipping_fee'] = get_order_shipping_fee($cart_goods_list);
    }
    $total['shipping_fee_formated'] = price_format($total['shipping_fee'], false);
    $total['shipping_insure_formated'] = price_format($total['shipping_insure'], false);

    // 购物车中的商品能享受红包支付的总额
    $bonus_amount = compute_discount_amount($cart_value);
    // 红包和积分最多能支付的金额为商品总额
    $max_amount = $total['goods_price'] == 0 ? $total['goods_price'] : $total['goods_price'] - $bonus_amount;

    /* 计算订单总额 */
    if ($order['extension_code'] == 'group_buy' && $group_buy['deposit'] > 0) {
        $total['amount'] = $total['goods_price'];
    } elseif ($order['extension_code'] == 'presale' && $presale['deposit'] > 0) {
        $total['amount'] = $total['presale_price'];
    } elseif ($order['extension_code'] == 'team_buy' ) {
        $total['amount'] = $total['goods_price']+$total['shipping_fee'] + $total['shipping_insure'];
    }else {
        $total['amount'] = $total['goods_price'] - $total['discount'] + $total['tax'] + $total['pack_fee'] + $total['card_fee'] +
                $total['shipping_fee'] + $total['shipping_insure'] + $total['cod_fee'];

        // 减去红包金额  //红包支付，如果红包的金额大于订单金额 则去订单金额定义为红包金额的最终结果(相当于订单金额减去本身的金额，为0) ecmoban模板堂 --zhuo
        $use_bonus = min($total['bonus'], $max_amount); // 实际减去的红包金额
        $use_coupons = min($total['coupons'], $max_amount); // 实际减去的优惠券金额 bylu
        if (isset($total['bonus_kill'])) {
            $use_bonus_kill = min($total['bonus_kill'], $max_amount);
            $total['amount'] -= $price = number_format($total['bonus_kill'], 2, '.', ''); // 还需要支付的订单金额
        }

        $total['bonus'] = $use_bonus;
        $total['bonus_formated'] = price_format($total['bonus'], false);

        $total['coupons'] = $use_coupons; //bylu
        $total['coupons_formated'] = price_format($total['coupons'], false); //bylu

        $total['amount'] -= $use_bonus + $use_coupons; // 还需要支付的订单金额
        $max_amount -= $use_bonus + $use_coupons; // 积分最多还能支付的金额
    }


    /* 余额 */
    $order['surplus'] = $order['surplus'] > 0 ? $order['surplus'] : 0;
    if ($total['amount'] > 0) {
        if (isset($order['surplus']) && $order['surplus'] > $total['amount']) {
            $order['surplus'] = $total['amount'];
            $total['amount'] = 0;
        } else {
            $total['amount'] -= floatval($order['surplus']);
        }
    } else {
        $order['surplus'] = 0;
        $total['amount'] = 0;
    }
    $total['surplus'] = $order['surplus'];
    $total['surplus_formated'] = price_format($order['surplus'], false);

    /* 积分 */
    $order['integral'] = $order['integral'] > 0 ? $order['integral'] : 0;
    if ($total['amount'] > 0 && $max_amount > 0 && $order['integral'] > 0) {
        $integral_money = value_of_integral($order['integral']);

        // 使用积分支付
        $use_integral = min($total['amount'], $max_amount, $integral_money); // 实际使用积分支付的金额
        $total['amount'] -= $use_integral;
        $total['integral_money'] = $use_integral;
        $order['integral'] = integral_of_value($use_integral);
    } else {
        $total['integral_money'] = 0;
        $order['integral'] = 0;
    }
    $total['integral'] = $order['integral'];
    $total['integral_formated'] = price_format($total['integral_money'], false);

    /* 保存订单信息 */
    $_SESSION['flow_order'] = $order;

    $se_flow_type = isset($_SESSION['flow_type']) ? $_SESSION['flow_type'] : '';

    /* 支付费用 */
    if (!empty($order['pay_id']) && ($total['real_goods_count'] > 0 || $se_flow_type != CART_EXCHANGE_GOODS)) {
        $total['pay_fee'] = pay_fee($order['pay_id'], $total['amount'], $shipping_cod_fee);
    }

    $total['pay_fee_formated'] = price_format($total['pay_fee'], false);

    $total['amount'] += $total['pay_fee']; // 订单总额累加上支付费用
    $total['amount_formated'] = price_format($total['amount'], false);

    /* 取得可以得到的积分和红包 */
    if ($order['extension_code'] == 'group_buy') {
        $total['will_get_integral'] = $group_buy['gift_integral'];
    } elseif ($order['extension_code'] == 'exchange_goods') {
        $total['will_get_integral'] = 0;
    } else {
        $total['will_get_integral'] = get_give_integral($goods, $cart_value, $warehouse_id, $area_id); //ecmoban模板堂 --zhuo
    }

    $total['will_get_bonus'] = $order['extension_code'] == 'exchange_goods' ? 0 : price_format(get_total_bonus(), false);
    $total['formated_goods_price'] = price_format($total['goods_price'], false);
    $total['formated_market_price'] = price_format($total['market_price'], false);
    $total['formated_saving'] = price_format($total['saving'], false);

    if ($order['extension_code'] == 'exchange_goods') {
        $sql = 'SELECT SUM(eg.exchange_integral * c.goods_number) ' .
                'FROM ' . $GLOBALS['ecs']->table('cart') . ' AS c,' . $GLOBALS['ecs']->table('exchange_goods') . 'AS eg ' .
                "WHERE c.goods_id = eg.goods_id AND " . $c_sess .
                "  AND c.rec_type = '" . CART_EXCHANGE_GOODS . "' " .
                '  AND c.is_gift = 0 AND c.goods_id > 0 AND eg.review_status = 3' .
                'GROUP BY eg.goods_id';
        $exchange_integral = $GLOBALS['db']->getOne($sql);
        $total['exchange_integral'] = $exchange_integral;
    }

    return $total;
}

//查询票税金额
function get_order_invoice_total($goods_price, $inv_content) {
    $invoice = get_invoice_list($GLOBALS['_CFG']['invoice_type'], 1, $inv_content);
    $rate = floatval($invoice['rate']) / 100;

    if ($rate > 0) {
        $tax = $rate * $goods_price;
    }

    return $tax;
}

//获取订单运费金额 ecmoban模板堂 --zhuo
function get_order_shipping_fee($cart_goods) {
    $shipping_fee = 0;
    if ($cart_goods) {
        foreach ($cart_goods as $row) {
            foreach ($row['shipping'] as $kk => $vv) {
                //结算页切换配送方式
                if (isset($row['tmp_shipping_id'])) {
                    if ($row['tmp_shipping_id'] == $vv['shipping_id']) {
                        //自营时--自提时运费清0
                        if (isset($rows['shipping_code']) && $row['shipping_code'] == 'cac') {
                            $vv['shipping_fee'] = 0;
                        }
                        $shipping_fee += $vv['shipping_fee'];
                    }
                } else {
                    if ($vv['default'] == 1) {
                        //自营时--自提时运费清0
                        if ($row['shipping_code'] == 'cac') {
                            $vv['shipping_fee'] = 0;
                        }
                        $shipping_fee += $vv['shipping_fee'];
                    }
                }
            }
        }
    } else {
        $shipping_fee = 0;
    }
    return $shipping_fee;
}

/**
 * 修改订单
 * @param   int     $order_id   订单id
 * @param   array   $order      key => value
 * @return  bool
 */
function update_order($order_id, $order) {
    return $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), $order, 'UPDATE', "order_id = '$order_id'");
}

/**
 * 得到新订单号
 * @return  string
 */
function get_order_sn() {
    $time = explode(" ", microtime());
    $time = $time[1] . ($time[0] * 1000);
    $time = explode(".", $time);
    $time = isset($time[1]) ? $time[1] : 0;
    $time = date('YmdHis') + $time;

    /* 选择一个随机的方案 */
    mt_srand((double) microtime() * 1000000);
    return $time . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

/**
 * 取得购物车商品
 * @param   int     $type   类型：默认普通商品
 * @return  array   购物车商品数组
 */
function cart_goods($type = CART_GENERAL_GOODS, $cart_value = '', $ru_type = 0, $warehouse_id = 0, $area_id = 0, $consignee = '', $store_id = 0) {
    $rec_txt = array('普通', '团够', '拍卖', '夺宝奇兵', '积分商城', '预售');

    $where = " g.is_delete = 0 AND ";
    if ($type == CART_PRESALE_GOODS) {
        $where .= " g.is_on_sale = 0 AND ";
    }

    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }

    $goodsIn = '';
    if (!empty($cart_value)) {
        $goodsIn = " and c.rec_id in($cart_value)";
    }
    //ecmoban模板堂 --zhuo end
    //查询非超值礼包商品
    $sql = "SELECT c.warehouse_id, c.area_id, c.rec_id, c.user_id, c.goods_id, g.user_id as ru_id, g.cat_id, c.goods_name, g.goods_thumb, c.goods_sn, c.goods_number, g.default_shipping, g.goods_weight as goodsWeight, " . //储值卡指定分类 liu
            "c.market_price, c.goods_price, c.goods_attr, c.is_real, c.extension_code, c.parent_id, c.is_gift, c.rec_type, " .
            "c.goods_price * c.goods_number AS subtotal, c.goods_attr_id, c.goods_number, c.stages_qishu, " . //查出分期期数 bylu;
            "c.parent_id, c.group_id, pa.deposit, g.is_shipping, g.freight, g.tid, g.shipping_fee, c.store_id " .
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON c.goods_id = g.goods_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('presale_activity') . " AS pa ON pa.goods_id = g.goods_id " .
            "WHERE $where " . $c_sess .
            "AND rec_type = '$type'" . $goodsIn . " AND c.extension_code <> 'package_buy' order by c.rec_id DESC";

    $arr = $GLOBALS['db']->getAll($sql);

    //查询超值礼包商品
    $sql = "SELECT c.warehouse_id, c.rec_id, c.user_id, c.goods_id, c.ru_id, c.goods_name, c.goods_sn, c.goods_number, " .
            "c.market_price, c.goods_price, c.goods_attr, c.is_real, c.extension_code, c.parent_id, c.is_gift, c.rec_type, " .
            "c.goods_price * c.goods_number AS subtotal, c.goods_attr_id, c.goods_number, c.stages_qishu," . //查出分期期数 bylu;
            " c.parent_id, c.group_id, g.is_shipping, g.freight, g.tid, g.shipping_fee " .
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " AS g ON c.goods_id = g.goods_id " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('goods_activity') . " AS ga ON c.goods_id = ga.act_id " .
            "WHERE " . $c_sess .
            "AND rec_type = '$type'" . $goodsIn . " AND c.extension_code = 'package_buy' AND ga.review_status = 3 order by c.rec_id DESC";

    $arr2 = $GLOBALS['db']->getAll($sql);

    $arr = array_merge($arr, $arr2);

    if ($GLOBALS['_CFG']['add_shop_price'] == 1) {
        $add_tocart = 1;
    } else {
        $add_tocart = 0;
    }

    /* 格式化价格及礼包商品 */
    foreach ($arr as $key => $value) {
        /* 判断购物车商品价格是否与目前售价一致，如果不同则返回购物车价格失效 */
        $currency_format = !empty($GLOBALS['_CFG']['currency_format']) ? explode('%', $GLOBALS['_CFG']['currency_format']) : '';
        $attr_id = !empty($value['goods_attr_id']) ? explode(',', $value['goods_attr_id']) : '';
        if($row['extension_code'] !== 'package_buy'){
            if (count($currency_format) > 1) {
                $goods_price = trim(get_final_price($value['goods_id'], $value['goods_number'], true, $attr_id, $value['warehouse_id'], $value['area_id'], 0, 0, $add_tocart), $currency_format[0]);
                $cart_price = trim($value['goods_price'], $currency_format[0]);
            } else {
                $goods_price = get_final_price($value['goods_id'], $value['goods_number'], true, $attr_id, $value['warehouse_id'], $value['area_id'], 0, 0, $add_tocart);
                $cart_price = $value['goods_price'];
            }
        }

        $goods_price = floatval($goods_price);
        $cart_price = floatval($cart_price);

        if ($goods_price != $cart_price && empty($value['is_gift']) && isset($row['group_id'])) {
            $value['is_invalid'] = 1; //价格已过期
        } else {
            $value['is_invalid'] = 0; //价格未过期
        }
        if ($value['is_invalid'] && $value['rec_type'] == 0 && empty($value['is_gift']) && $value['extension_code'] != 'package_buy') {
            if (isset($_SESSION['flow_type']) && $_SESSION['flow_type'] == 0) {
                get_update_cart_price($goods_price, $value['rec_id']);
                $value['goods_price'] = $goods_price;
            }
        }

        $arr[$key]['formated_goods_price'] = price_format($value['goods_price'], false);
        $arr[$key]['formated_subtotal'] = price_format($arr[$key]['subtotal'], false);

        if ($value['extension_code'] == 'package_buy') {
            $value['amount'] = 0;
            $arr[$key]['dis_amount'] = 0;
            $arr[$key]['discount_amount'] = price_format($arr[$key]['dis_amount'], false);

            $arr[$key]['package_goods_list'] = get_package_goods($value['goods_id']);

            $package = get_package_goods_info($arr[$key]['package_goods_list']);
            $arr[$key]['goods_weight'] = $package['goods_weight'];
            $arr[$key]['goodsWeight'] = $package['goods_weight'];
            $arr[$key]['goods_number'] = $value['goods_number'];
            $arr[$key]['attr_number'] = 1;
            $sql = "SELECT activity_thumb FROM {pre}goods_activity WHERE act_id =" . $value['goods_id']. ' and is_finished = 0 and review_status = 3';
            $activity_thumb = $GLOBALS['db']->getRow($sql);
            $arr[$key]['goods_thumb'] = get_image_path($activity_thumb['activity_thumb']);
        } else {
            //ecmoban模板堂 --zhuo start 商品金额促销
            $goods_con = get_con_goods_amount($value['subtotal'], $value['goods_id'], 0, 0, $value['parent_id']);
            $goods_con['amount'] = explode(',', $goods_con['amount']);
            $value['amount'] = min($goods_con['amount']);

            $arr[$key]['dis_amount'] = $value['subtotal'] - $value['amount'];
            $arr[$key]['discount_amount'] = price_format($arr[$key]['dis_amount'], false);
            //ecmoban模板堂 --zhuo end 商品金额促销

            $arr[$key]['subtotal'] = $value['amount'];
            $arr[$key]['goods_thumb'] = get_image_path($value['goods_thumb']);
            $arr[$key]['formated_market_price'] = price_format($value['market_price'], false);

            $arr[$key]['formated_presale_deposit'] = price_format($value['deposit'], false);

            //ecmoban模板堂 --zhuo
            $arr[$key]['region_name'] = $GLOBALS['db']->getOne("select region_name from " . $GLOBALS['ecs']->table('region_warehouse') . " where region_id = '" . $value['warehouse_id'] . "'");
            $arr[$key]['rec_txt'] = $rec_txt[$value['rec_type']];
            if ($value['rec_type'] == 1) {
                $sql = "SELECT act_id,act_name FROM " . $GLOBALS['ecs']->table('goods_activity') . " WHERE act_type = '" . GAT_GROUP_BUY . "' AND goods_id = '" . $value['goods_id'] . "' AND review_status = 3";
                $group_buy = $GLOBALS['db']->getRow($sql);

                $arr[$key]['url'] = build_uri('group_buy', array('gbid' => $group_buy['act_id']));
                $arr[$key]['act_name'] = $group_buy['act_name'];
            } elseif ($value['rec_type'] == 5) {
                $sql = "SELECT act_id,act_name FROM " . $GLOBALS['ecs']->table('presale_activity') . " WHERE goods_id = '" . $value['goods_id'] . "' AND review_status = 3";
                $presale = $GLOBALS['db']->getRow($sql);

                $arr[$key]['url'] = "presale.php?act=view&id=" . $presale['act_id'];
                $arr[$key]['act_name'] = $presale['act_name'];
            } else {
                $arr[$key]['url'] = build_uri('goods', array('gid' => $value['goods_id']), $value['goods_name']);
            }

            //预售商品，不受库存限制
            if ($value['extension_code'] == 'presale') {
                $arr[$key]['attr_number'] = 1;
            } else {
                //ecmoban模板堂 --zhuo start
                if ($ru_type == 1 && $warehouse_id > 0 && $store_id == 0) {

                    $leftJoin = " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
                    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

                    $sql = "SELECT IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, g.user_id, g.model_attr FROM " .
                            $GLOBALS['ecs']->table('goods') . " AS g " . $leftJoin .
                            " WHERE g.goods_id = '" . $value['goods_id'] . "' LIMIT 1";
                    $goodsInfo = $GLOBALS['db']->getRow($sql);

                    $products = get_warehouse_id_attr_number($value['goods_id'], $value['goods_attr_id'], $goodsInfo['user_id'], $warehouse_id, $area_id);
                    $attr_number = $products['product_number'];

                    if ($goodsInfo['model_attr'] == 1) {
                        $table_products = "products_warehouse";
                        $type_files = " and warehouse_id = '$warehouse_id'";
                    } elseif ($goodsInfo['model_attr'] == 2) {
                        $table_products = "products_area";
                        $type_files = " and area_id = '$area_id'";
                    } else {
                        $table_products = "products";
                        $type_files = "";
                    }

                    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '" . $value['goods_id'] . "'" . $type_files . " LIMIT 0, 1";
                    $prod = $GLOBALS['db']->getRow($sql);

                    if (empty($prod)) { //当商品没有属性库存时
                        $attr_number = ($GLOBALS['_CFG']['use_storage'] == 1) ? $goodsInfo['goods_number'] : 1;
                    }

                    $attr_number = !empty($attr_number) ? $attr_number : 0;
                    $arr[$key]['attr_number'] = $attr_number;
                } else {
                    $arr[$key]['attr_number'] = $value['goods_number'];
                }

                //ecmoban模板堂 --zhuo end
            }

            //by kong  切换门店获取商品门店库存 start 20160721
            if ($store_id > 0) {
                $sql = "SELECT goods_number,ru_id FROM" . $GLOBALS['ecs']->table("store_goods") . " WHERE store_id = '$store_id' AND goods_id = '" . $value['goods_id'] . "' ";
                $goodsInfo = $GLOBALS['db']->getRow($sql);

                $products = get_warehouse_id_attr_number($value['goods_id'], $value['goods_attr_id'], $goodsInfo['ru_id'], 0, 0, '', $store_id); //获取属性库存
                $attr_number = $products['product_number'];
                if ($value['goods_attr_id']) { //当商品没有属性库存时
                    $arr[$key]['attr_number'] = $attr_number;
                } else {
                    $arr[$key]['attr_number'] = $goodsInfo['goods_number'];
                }
            }
            //by kong  切换门店获取商品门店库存 end 20160721
        }
    }

    if ($ru_type == 1) {
        $arr = get_cart_goods_ru_list($arr, $ru_type);

        $arr = get_cart_ru_goods_list($arr, $cart_value, $consignee, $store_id);
    }

    return $arr;
}

//by wang end修改
/**
 * 取得购物车总金额
 * @params  boolean $include_gift   是否包括赠品
 * @param   int     $type           类型：默认普通商品
 * @return  float   购物车总金额
 */
function cart_amount($include_gift = true, $type = CART_GENERAL_GOODS) {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    $sql = "SELECT SUM(goods_price * goods_number) " .
            " FROM " . $GLOBALS['ecs']->table('cart') .
            " WHERE " . $sess_id .
            "AND rec_type = '$type' ";

    if (!$include_gift) {
        $sql .= ' AND is_gift = 0 AND goods_id > 0';
    }

    return floatval($GLOBALS['db']->getOne($sql));
}

/**
 * 检查某商品是否已经存在于购物车
 *
 * @access  public
 * @param   integer     $id
 * @param   array       $spec
 * @param   int         $type   类型：默认普通商品
 * @return  boolean
 */
function cart_goods_exists($id, $spec, $type = CART_GENERAL_GOODS) {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    /* 检查该商品是否已经存在在购物车中 */
    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('cart') .
            "WHERE " . $sess_id . " AND goods_id = '$id' " .
            "AND parent_id = 0 AND goods_attr = '" . get_goods_attr_info($spec) . "' " .
            "AND rec_type = '$type'";

    return ($GLOBALS['db']->getOne($sql) > 0);
}

/**
 * 获得购物车中商品的总重量、总价格、总数量
 *
 * @access  public
 * @param   int     $type   类型：默认普通商品
 * @return  array
 */
function cart_weight_price($type = CART_GENERAL_GOODS, $cart_value) {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }

    $goodsIn = '';
    $pack_goodsIn = '';
    if (!empty($cart_value)) {
        $goodsIn = " and c.rec_id in($cart_value)";
        $pack_goodsIn = " and rec_id in($cart_value)";
    }
    //ecmoban模板堂 --zhuo end

    $package_row['weight'] = 0;
    $package_row['amount'] = 0;
    $package_row['number'] = 0;

    $packages_row['free_shipping'] = 1;

    /* 计算超值礼包内商品的相关配送参数 */
    $sql = 'SELECT goods_id, goods_number, goods_price FROM ' . $GLOBALS['ecs']->table('cart') . " WHERE extension_code = 'package_buy' AND " . $sess_id . $pack_goodsIn;
    $row = $GLOBALS['db']->getAll($sql);

    if ($row) {
        $packages_row['free_shipping'] = 0;
        $free_shipping_count = 0;

        foreach ($row as $val) {
            // 如果商品全为免运费商品，设置一个标识变量
            $sql = 'SELECT count(*) FROM ' .
                    $GLOBALS['ecs']->table('package_goods') . ' AS pg, ' .
                    $GLOBALS['ecs']->table('goods') . ' AS g ' .
                    "WHERE g.goods_id = pg.goods_id AND g.is_shipping = 0 AND pg.package_id = '" . $val['goods_id'] . "'";
            $shipping_count = $GLOBALS['db']->getOne($sql);

            if ($shipping_count > 0) {
                // 循环计算每个超值礼包商品的重量和数量，注意一个礼包中可能包换若干个同一商品
                $sql = 'SELECT SUM(g.goods_weight * pg.goods_number) AS weight, ' .
                        'SUM(pg.goods_number) AS number, g.freight FROM ' .
                        $GLOBALS['ecs']->table('package_goods') . ' AS pg, ' .
                        $GLOBALS['ecs']->table('goods') . ' AS g ' .
                        "WHERE g.goods_id = pg.goods_id AND g.is_shipping = 0 AND g.freight <> 2 AND pg.package_id = '" . $val['goods_id'] . "'";
                $goods_row = $GLOBALS['db']->getRow($sql);

                $package_row['weight'] += floatval($goods_row['weight']) * $val['goods_number'];
                $package_row['amount'] += floatval($val['goods_price']) * $val['goods_number'];
                $package_row['number'] += intval($goods_row['number']) * $val['goods_number'];
            } else {
                $free_shipping_count++;
            }
        }

        $packages_row['free_shipping'] = $free_shipping_count == count($row) ? 1 : 0;
    }

    /* 获得购物车中非超值礼包商品的总重量 */
    $sql = 'SELECT SUM(g.goods_weight * c.goods_number) AS weight, ' .
            'SUM(c.goods_price * c.goods_number) AS amount, ' .
            'SUM(c.goods_number) AS number, g.freight ' .
            'FROM ' . $GLOBALS['ecs']->table('cart') . ' AS c ' .
            'LEFT JOIN ' . $GLOBALS['ecs']->table('goods') . ' AS g ON g.goods_id = c.goods_id ' .
            "WHERE " . $c_sess .
            "AND rec_type = '$type' AND g.is_shipping = 0 AND g.freight <> 2 AND c.extension_code != 'package_buy' " . $goodsIn;
    $row = $GLOBALS['db']->getRow($sql);

    $packages_row['weight'] = floatval($row['weight']) + $package_row['weight'];
    $packages_row['amount'] = floatval($row['amount']) + $package_row['amount'];
    $packages_row['number'] = intval($row['number']) + $package_row['number'];

    /* 格式化重量 */
    $packages_row['formated_weight'] = formated_weight($packages_row['weight']);

    return $packages_row;
}

/**
 * 添加商品到购物车
 *
 * @access  public
 * @param   integer $goods_id   商品编号
 * @param   integer $num        商品数量
 * @param   array   $spec       规格值对应的id数组
 * @param   integer $parent     基本件
 * @return  boolean
 */
function addto_cart($goods_id, $num = 1, $spec = array(), $parent = 0, $warehouse_id = 0, $area_id = 0, $store_id = 0) { //ecmoban模板堂 --zhuo $warehouse_id
    $GLOBALS['err']->clean();
    $_parent_id = $parent;

    //ecmoban模板堂 --zhuo start
    $leftJoin = '';
    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wg.region_number as wg_number, wag.region_price, wag.region_promote_price, wag.region_number as wag_number, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        $sess = "";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        $sess = real_cart_mac_ip();
    }
    //ecmoban模板堂 --zhuo end

    /* 取得商品信息 */
    $sql = "SELECT wg.w_id, g.goods_name, g.goods_sn, g.is_on_sale, g.is_real, g.user_id as ru_id, g.model_inventory, g.model_attr, " .
            $shop_price .
            "g.market_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, " .
            "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, " .
            " g.promote_start_date, " .
            "g.promote_end_date, g.goods_weight, g.integral, g.extension_code, " .
            "g.goods_number, g.is_alone_sale, g.is_shipping," .
            "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price " .
            " FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
            $leftJoin .
            " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            " WHERE g.goods_id = '$goods_id'" .
            " AND g.is_delete = 0";

    $goods = $GLOBALS['db']->getRow($sql);
    /* 如果是门店一步购物，获取门店库存 */
    if ($store_id > 0) {
        $goods['goods_number'] = $GLOBALS['db']->getOne("SELECT  goods_number FROM" . $GLOBALS['ecs']->table("store_goods") . " WHERE goods_id = '$goods_id' AND store_id = '$store_id'");
    }

    if (empty($goods)) {
        $GLOBALS['err']->add(L('goods_not_exists'), ERR_NOT_EXISTS);

        return false;
    }

    /* 如果是作为配件添加到购物车的，需要先检查购物车里面是否已经有基本件 */
    if ($parent > 0) {
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('cart') .
                " WHERE goods_id='$parent' AND " . $sess_id . " AND extension_code <> 'package_buy'";
        if ($GLOBALS['db']->getOne($sql) == 0) {
            $GLOBALS['err']->add(L('no_basic_goods'), ERR_NO_BASIC_GOODS);

            return false;
        }
    }
    /* 是否正在销售 */
    if ($goods['is_on_sale'] == 0) {
        $GLOBALS['err']->add(L('not_on_sale'), ERR_NOT_ON_SALE);

        return false;
    }

    /* 不是配件时检查是否允许单独销售 */
    if (empty($parent) && $goods['is_alone_sale'] == 0) {
        $GLOBALS['err']->add(L('cannt_alone_sale'), ERR_CANNT_ALONE_SALE);

        return false;
    }

    /* 如果商品有规格则取规格商品信息 配件除外 */

    //ecmoban模板堂 --zhuo start
    if ($store_id > 0) {
        $table_products = "store_products";
        $type_files = " and store_id = '$store_id'";
    } else {
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
    }

    //ecmoban模板堂 --zhuo end

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '$goods_id'" . $type_files . " LIMIT 0, 1";
    $prod = $GLOBALS['db']->getRow($sql);

    if (is_spec($spec) && !empty($prod)) {
        $product_info = get_products_info($goods_id, $spec, $warehouse_id, $area_id, $store_id);
    }
    if (empty($product_info)) {
        $product_info = array('product_number' => 0, 'product_id' => 0);
    }

    //ecmoban模板堂 --zhuo start
    if ($goods['model_inventory'] == 1) {
        $goods['goods_number'] = $goods['wg_number'];
    } elseif ($goods['model_inventory'] == 2) {
        $goods['goods_number'] = $goods['wag_number'];
    }
    //ecmoban模板堂 --zhuo end

    /* 检查：库存 */
    if ($GLOBALS['_CFG']['use_storage'] == 1) {
        if ($store_id > 0) {
            $lang_shortage = L('store_shortage');
        } else {
            $lang_shortage = L('shortage');
        }
        $is_product = 0;
        //商品存在规格 是货品
        if (is_spec($spec) && !empty($prod)) {
            if (!empty($spec)) {
                /* 取规格的货品库存 */
                if ($num > $product_info['product_number']) {
                    $GLOBALS['err']->add(sprintf($lang_shortage, $product_info['product_number']), ERR_OUT_OF_STOCK);

                    return false;
                }
            }
        } else {
            $is_product = 1;
        }

        if ($is_product == 1) {
            //检查：商品购买数量是否大于总库存
            if ($num > $goods['goods_number']) {
                $GLOBALS['err']->add(sprintf(L('shortage'), $goods['goods_number']), ERR_OUT_OF_STOCK);

                return false;
            }
        }
    }

    /* 计算商品的促销价格 */
    $warehouse_area['warehouse_id'] = $warehouse_id;
    $warehouse_area['area_id'] = $area_id;

    if ($GLOBALS['_CFG']['add_shop_price'] == 1) {
        $add_tocart = 1;
    } else {
        $add_tocart = 0;
    }

    $spec_price = spec_price($spec, $goods_id, $warehouse_area);
    $goods_price = get_final_price($goods_id, $num, true, $spec, $warehouse_id, $area_id, 0, 0, $add_tocart);

    $goods['market_price'] += $spec_price;
    $goods_attr = get_goods_attr_info($spec, 'pice', $warehouse_id, $area_id);

    if (!empty($spec)) {
        $goods_attr_id = join(',', $spec);
    }
    /* 初始化要插入购物车的基本件数据 */
    $parent = array(
        'user_id' => $_SESSION['user_id'],
        'session_id' => $sess,
        'goods_id' => $goods_id,
        'goods_sn' => addslashes($goods['goods_sn']),
        'product_id' => $product_info['product_id'],
        'goods_name' => addslashes($goods['goods_name']),
        'market_price' => $goods['market_price'],
        'goods_attr' => addslashes($goods_attr),
        'goods_attr_id' => $goods_attr_id,
        'is_real' => $goods['is_real'],
        'model_attr' => $goods['model_attr'], //ecmoban模板堂 --zhuo 属性方式
        'warehouse_id' => $warehouse_id, //ecmoban模板堂 --zhuo 仓库
        'area_id' => $area_id, //ecmoban模板堂 --zhuo 仓库地区
        'ru_id' => $goods['ru_id'], //ecmoban模板堂 --zhuo 商家ID
        'extension_code' => $goods['extension_code'],
        'is_gift' => 0,
        'is_shipping' => $goods['is_shipping'],
        'rec_type' => CART_GENERAL_GOODS,
        'add_time' => gmtime(),
        'store_id' => $store_id
    );

    /* 如果该配件在添加为基本件的配件时，所设置的“配件价格”比原价低，即此配件在价格上提供了优惠， */
    /* 则按照该配件的优惠价格卖，但是每一个基本件只能购买一个优惠价格的“该配件”，多买的“该配件”不享 */
    /* 受此优惠 */
    $basic_list = array();
    $sql = "SELECT parent_id, goods_price " .
            "FROM " . $GLOBALS['ecs']->table('group_goods') .
            " WHERE goods_id = '$goods_id'" .
            " AND goods_price <= '$goods_price'" .
            " AND parent_id = '$_parent_id'" .
            " ORDER BY goods_price";
    $res = $GLOBALS['db']->query($sql);
    foreach ($res as $row) {
        $basic_list[$row['parent_id']] = $row['goods_price'];
    }

    /* 取得购物车中该商品每个基本件的数量 */
    $basic_count_list = array();
    if ($basic_list) {
        $sql = "SELECT goods_id, SUM(goods_number) AS count " .
                "FROM " . $GLOBALS['ecs']->table('cart') .
                " WHERE " . $sess_id .
                " AND parent_id = 0" .
                " AND extension_code <> 'package_buy' " .
                " AND goods_id " . db_create_in(array_keys($basic_list)) .
                " GROUP BY goods_id";
        $res = $GLOBALS['db']->query($sql);
        foreach ($res as $row) {
            $basic_count_list[$row['goods_id']] = $row['count'];
        }
    }

    /* 取得购物车中该商品每个基本件已有该商品配件数量，计算出每个基本件还能有几个该商品配件 */
    /* 一个基本件对应一个该商品配件 */
    if ($basic_count_list) {
        $sql = "SELECT parent_id, SUM(goods_number) AS count " .
                "FROM " . $GLOBALS['ecs']->table('cart') .
                " WHERE " . $sess_id .
                " AND goods_id = '$goods_id'" .
                " AND extension_code <> 'package_buy' " .
                " AND parent_id " . db_create_in(array_keys($basic_count_list)) .
                " GROUP BY parent_id";
        $res = $GLOBALS['db']->query($sql);
        foreach ($res as $row) {
            $basic_count_list[$row['parent_id']] -= $row['count'];
        }
    }

    /* 循环插入配件 如果是配件则用其添加数量依次为购物车中所有属于其的基本件添加足够数量的该配件 */
    foreach ($basic_list as $parent_id => $fitting_price) {
        /* 如果已全部插入，退出 */
        if ($num <= 0) {
            break;
        }

        /* 如果该基本件不再购物车中，执行下一个 */
        if (!isset($basic_count_list[$parent_id])) {
            continue;
        }

        /* 如果该基本件的配件数量已满，执行下一个基本件 */
        if ($basic_count_list[$parent_id] <= 0) {
            continue;
        }

        /* 作为该基本件的配件插入 */
        $parent['goods_price'] = max($fitting_price, 0) + $spec_price; //允许该配件优惠价格为0
        $parent['goods_number'] = min($num, $basic_count_list[$parent_id]);
        $parent['parent_id'] = $parent_id;

        /* 添加 */
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('cart'), $parent, 'INSERT');

        /* 改变数量 */
        $num -= $parent['goods_number'];
    }

    /* 如果数量不为0，作为基本件插入 */
    if ($num > 0) {
        /* 检查该商品是否已经存在在购物车中 */
        $sql = "SELECT goods_number FROM " . $GLOBALS['ecs']->table('cart') .
                " WHERE " . $sess_id . " AND goods_id = '$goods_id' " .
                " AND parent_id = 0 AND goods_attr = '$goods_attr' " .
                " AND extension_code <> 'package_buy' " .
                " AND rec_type = 'CART_GENERAL_GOODS' AND group_id='' AND warehouse_id = '$warehouse_id' AND store_id = '$store_id'"; //by mike add

        $row = $GLOBALS['db']->getRow($sql);

        if ($row) { //如果购物车已经有此物品，则更新
            $num += $row['goods_number'];
            if (is_spec($spec) && !empty($prod)) {
                $goods_storage = $product_info['product_number'];
            } else {
                $goods_storage = $goods['goods_number'];
            }
            if ($GLOBALS['_CFG']['use_storage'] == 0 || $num <= $goods_storage) {
                $goods_price = get_final_price($goods_id, $num, true, $spec, $warehouse_id, $area_id, 0, 0, $add_tocart); //ecmoban模板堂 --zhuo
                $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') . " SET goods_number = '$num'" .
                        " , goods_price = '$goods_price'" .
                        " , area_id = '$area_id'" . //ecmoban模板堂 --zhuo 更新地区
                        " WHERE " . $sess_id . " AND goods_id = '$goods_id' " .
                        " AND parent_id = 0 AND goods_attr = '$goods_attr' " .
                        " AND extension_code <> 'package_buy' " .
                        " AND warehouse_id = '$warehouse_id' " . //ecmoban模板堂 --zhuo
                        "AND rec_type = 'CART_GENERAL_GOODS' AND group_id = 0 AND store_id = " . $store_id;

                $GLOBALS['db']->query($sql);
            } else {
                $GLOBALS['err']->add(sprintf(L('shortage'), $num), ERR_OUT_OF_STOCK);

                return false;
            }
        } else { //购物车没有此物品，则插入
            $goods_price = get_final_price($goods_id, $num, true, $spec, $warehouse_id, $area_id, 0, 0, $add_tocart); //ecmoban模板堂 --zhuo
            $parent['goods_price'] = max($goods_price, 0);
            $parent['goods_number'] = $num;
            $parent['parent_id'] = 0;
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('cart'), $parent, 'INSERT');
        }
    }

    /* 把赠品删除 */
    /* $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE " .$sess_id. " AND is_gift <> 0";
      $GLOBALS['db']->query($sql);
     *
     */

    return true;
}

/**
 * 添加商品到购物车（配件组合） by mike
 *
 * @access  public
 * @param   integer $goods_id   商品编号
 * @param   integer $num        商品数量
 * @param   array   $spec       规格值对应的id数组
 * @param   integer $parent     基本件
 * @return  boolean
 */
function addto_cart_combo($goods_id, $num = 1, $spec = array(), $parent = 0, $group = '', $warehouse_id = 0, $area_id = 0, $goods_attr = '') { //ecmoban模板堂 --zhuo $warehouse_id
    if (!is_array($goods_attr)) {
        if (!empty($goods_attr)) {
            $goods_attr = explode(',', $goods_attr);
        } else {
            $goods_attr = array();
        }
    }

    $ok_arr = get_insert_group_main($parent, $num, $goods_attr, 0, $group, $warehouse_id, $area_id);

    if ($ok_arr['is_ok'] == 1) { // 商品不存在
        $GLOBALS['err']->add(L('group_goods_not_exists'), ERR_NOT_EXISTS);
        return false;
    }if ($ok_arr['is_ok'] == 2) { // 商品已下架
        $GLOBALS['err']->add(L('group_not_on_sale'), ERR_NOT_ON_SALE);
        return false;
    }if ($ok_arr['is_ok'] == 3 || $ok_arr['is_ok'] == 4) { // 商品缺货
        $GLOBALS['err']->add(sprintf(L('group_shortage')), ERR_OUT_OF_STOCK);
        return false;
    }

    $GLOBALS['err']->clean();
    $_parent_id = $parent;

    //ecmoban模板堂 --zhuo start
    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wg.region_number as wg_number, wag.region_price, wag.region_promote_price, wag.region_number as wag_number, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        $sess = "";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        $sess = real_cart_mac_ip();
    }
    //ecmoban模板堂 --zhuo end

    /* 取得商品信息 */
    $sql = "SELECT wg.w_id, g.goods_name, g.goods_sn, g.is_on_sale, g.is_real, g.user_id as ru_id, g.model_inventory, g.model_attr, " .
            $shop_price .
            "g.market_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, " .
            "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, " .
            " g.promote_start_date, " .
            "g.promote_end_date, g.goods_weight, g.integral, g.extension_code, " .
            "g.goods_number, g.is_alone_sale, g.is_shipping," .
            "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price " .
            " FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
            $leftJoin .
            " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            " WHERE g.goods_id = '$goods_id'" .
            " AND g.is_delete = 0";

    $goods = $GLOBALS['db']->getRow($sql);

    if (empty($goods)) {
        $GLOBALS['err']->add(L('goods_not_exists'), ERR_NOT_EXISTS);

        return false;
    }

    /* 是否正在销售 */
    if ($goods['is_on_sale'] == 0) {
        $GLOBALS['err']->add(L('not_on_sale'), ERR_NOT_ON_SALE);

        return false;
    }

    /* 不是配件时检查是否允许单独销售 */
    if (empty($parent) && $goods['is_alone_sale'] == 0) {
        $GLOBALS['err']->add(L('cannt_alone_sale'), ERR_CANNT_ALONE_SALE);

        return false;
    }

    /* 如果商品有规格则取规格商品信息 配件除外 */

    //ecmoban模板堂 --zhuo start
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
    //ecmoban模板堂 --zhuo end

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '$goods_id'" . $type_files . " LIMIT 0, 1";
    $prod = $GLOBALS['db']->getRow($sql);

    if (is_spec($spec) && !empty($prod)) {
        $product_info = get_products_info($goods_id, $spec, $warehouse_id, $area_id);
    }
    if (empty($product_info)) {
        $product_info = array('product_number' => 0, 'product_id' => 0);
    }

    //ecmoban模板堂 --zhuo start
    if ($goods['model_inventory'] == 1) {
        $goods['goods_number'] = $goods['wg_number'];
    } elseif ($goods['model_inventory'] == 2) {
        $goods['goods_number'] = $goods['wag_number'];
    }
    //ecmoban模板堂 --zhuo end

    /* 检查：库存 */
    if ($GLOBALS['_CFG']['use_storage'] == 1) {
        $is_product = 0;
        //商品存在规格 是货品
        if (is_spec($spec) && !empty($prod)) {
            if (!empty($spec)) {
                /* 取规格的货品库存 */
                if ($num > $product_info['product_number']) {
                    $GLOBALS['err']->add(sprintf(L('shortage'), $product_info['product_number']), ERR_OUT_OF_STOCK);

                    return false;
                }
            }
        } else {
            $is_product = 1;
        }

        if ($is_product == 1) {
            //检查：商品购买数量是否大于总库存
            if ($num > $goods['goods_number']) {
                $GLOBALS['err']->add(sprintf(L('shortage'), $goods['goods_number']), ERR_OUT_OF_STOCK);

                return false;
            }
        }
    }

    /* 计算商品的促销价格 */
    $warehouse_area['warehouse_id'] = $warehouse_id;
    $warehouse_area['area_id'] = $area_id;

    $spec_price = spec_price($spec, $goods_id, $warehouse_area);
    $goods_price = get_final_price($goods_id, $num, true, $spec, $warehouse_id, $area_id);
    $goods['market_price'] += $spec_price;
    $goods_attr = get_goods_attr_info($spec, 'pice', $warehouse_id, $area_id); //ecmoban模板堂 --zhuo
    $goods_attr_id = join(',', $spec);

    /* 初始化要插入购物车的基本件数据 */
    $parent = array(
        'user_id' => $_SESSION['user_id'],
        'session_id' => $sess,
        'goods_id' => $goods_id,
        'goods_sn' => addslashes($goods['goods_sn']),
        'product_id' => $product_info['product_id'],
        'goods_name' => addslashes($goods['goods_name']),
        'market_price' => $goods['market_price'],
        'goods_attr' => addslashes($goods_attr),
        'goods_attr_id' => $goods_attr_id,
        'is_real' => $goods['is_real'],
        'model_attr' => $goods['model_attr'], //ecmoban模板堂 --zhuo 属性方式
        'warehouse_id' => $warehouse_id, //ecmoban模板堂 --zhuo 仓库
        'area_id' => $area_id, //ecmoban模板堂 --zhuo 仓库地区
        'ru_id' => $goods['ru_id'], //ecmoban模板堂 --zhuo 商家ID
        'extension_code' => $goods['extension_code'],
        'is_gift' => 0,
        'model_attr' => $goods['model_attr'],
        'is_shipping' => $goods['is_shipping'],
        'rec_type' => CART_GENERAL_GOODS,
        'add_time' => gmtime(),
        'group_id' => $group
    );

    /* 如果该配件在添加为基本件的配件时，所设置的“配件价格”比原价低，即此配件在价格上提供了优惠， */
    /* 则按照该配件的优惠价格卖，但是每一个基本件只能购买一个优惠价格的“该配件”，多买的“该配件”不享 */
    /* 受此优惠 */
    $basic_list = array();
    $sql = "SELECT parent_id, goods_price " .
            "FROM " . $GLOBALS['ecs']->table('group_goods') .
            " WHERE goods_id = '$goods_id'" .
            //" AND goods_price < '$goods_price'" .
            " AND parent_id = '$_parent_id'" .
            " ORDER BY goods_price";
    $res = $GLOBALS['db']->query($sql);
    foreach ($res as $row) {
        $basic_list[$row['parent_id']] = $row['goods_price'];
    }

    /* 循环插入配件 如果是配件则用其添加数量依次为购物车中所有属于其的基本件添加足够数量的该配件 */
    foreach ($basic_list as $parent_id => $fitting_price) {
        $attr_info = get_goods_attr_info($spec, 'pice', $warehouse_id, $area_id);

        /* 检查该商品是否已经存在在购物车中 */
        $sql = "SELECT goods_number FROM " . $GLOBALS['ecs']->table('cart_combo') .
                " WHERE " . $sess_id . " AND goods_id = '$goods_id' " .
                " AND parent_id = '$parent_id' " . //AND goods_attr = '" .get_goods_attr_info($spec). "' " .
                " AND extension_code <> 'package_buy' " .
                " AND rec_type = 'CART_GENERAL_GOODS' AND group_id='$group'";

        $row = $GLOBALS['db']->getRow($sql);

        if ($row) { //如果购物车已经有此物品，则更新
            $num = 1; //临时保存到数据库，无数量限制
            if (is_spec($spec) && !empty($prod)) {
                $goods_storage = $product_info['product_number'];
            } else {
                $goods_storage = $goods['goods_number'];
            }
            if ($GLOBALS['_CFG']['use_storage'] == 0 || $num <= $goods_storage) {
                $fittAttr_price = max($fitting_price, 0) + $spec_price; //允许该配件优惠价格为0;
                $sql = "UPDATE " . $GLOBALS['ecs']->table('cart_combo') . " SET goods_number = '$num'" .
                        " , goods_price = '$fittAttr_price'" .
                        " , product_id = '" . $product_info['product_id'] . "'" .
                        " , goods_attr = '$attr_info'" .
                        " , goods_attr_id = '$goods_attr_id'" .
                        " , market_price = '" . $goods['market_price'] . "'" .
                        " , warehouse_id = '$warehouse_id'" .
                        " , area_id = '$area_id'" .
                        " WHERE " . $sess_id . " AND goods_id = '$goods_id' " .
                        " AND parent_id = '$parent_id' " .
                        " AND extension_code <> 'package_buy' " .
                        "AND rec_type = 'CART_GENERAL_GOODS' AND group_id='$group'";
                $GLOBALS['db']->query($sql);
            } else {
                $GLOBALS['err']->add(sprintf(L('shortage'), $num), ERR_OUT_OF_STOCK);

                return false;
            }
        } else { //购物车没有此物品，则插入
            /* 作为该基本件的配件插入 */
            $parent['goods_price'] = max($fitting_price, 0) + $spec_price; //允许该配件优惠价格为0
            $parent['goods_number'] = 1; //临时保存到数据库，无数量限制
            $parent['parent_id'] = $parent_id;

            /* 添加 */
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('cart_combo'), $parent, 'INSERT');
        }
    }

    /* 把赠品删除 */
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart_combo') . " WHERE " . $sess_id . " AND is_gift <> 0";
    $GLOBALS['db']->query($sql);

    return true;
}

//首次添加配件时，查看主件是否存在，否则添加主件
function get_insert_group_main($goods_id, $num = 1, $goods_spec = array(), $parent = 0, $group = '', $warehouse_id = 0, $area_id = 0) {
    $ok_arr['is_ok'] = 0;
    $spec = $goods_spec;

    $GLOBALS['err']->clean();
    $_parent_id = $parent;

    //ecmoban模板堂 --zhuo start
    $shop_price = "wg.warehouse_price, wg.warehouse_promote_price, wg.region_number as wg_number, wag.region_price, wag.region_promote_price, wag.region_number as wag_number, g.model_price, g.model_attr, ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
    $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";
    //ecmoban模板堂 --zhuo end
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        $sess = "";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        $sess = real_cart_mac_ip();
    }
    //ecmoban模板堂 --zhuo end

    /* 取得商品信息 */
    $sql = "SELECT wg.w_id, g.goods_name, g.goods_sn, g.is_on_sale, g.is_real, g.user_id as ru_id, g.model_inventory, g.model_attr, " .
            $shop_price .
            "g.market_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) AS org_price, " .
            "IF(g.model_price < 1, g.promote_price, IF(g.model_price < 2, wg.warehouse_promote_price, wag.region_promote_price)) as promote_price, " .
            " g.promote_start_date, " .
            "g.promote_end_date, g.goods_weight, g.integral, g.extension_code, " .
            "g.goods_number, g.is_alone_sale, g.is_shipping," .
            "IFNULL(mp.user_price, IF(g.model_price < 1, g.shop_price, IF(g.model_price < 2, wg.warehouse_price, wag.region_price)) * '$_SESSION[discount]') AS shop_price " .
            " FROM " . $GLOBALS['ecs']->table('goods') . " AS g " .
            $leftJoin .
            " LEFT JOIN " . $GLOBALS['ecs']->table('member_price') . " AS mp " .
            "ON mp.goods_id = g.goods_id AND mp.user_rank = '$_SESSION[user_rank]' " .
            " WHERE g.goods_id = '$goods_id'" .
            " AND g.is_delete = 0";

    $goods = $GLOBALS['db']->getRow($sql);

    if (empty($goods)) {
        $ok_arr['is_ok'] = 1;
        return $ok_arr;
    }

    /* 是否正在销售 */
    if ($goods['is_on_sale'] == 0) {
        $ok_arr['is_ok'] = 2;
        return $ok_arr;
    }

    /* 如果商品有规格则取规格商品信息 */
    //ecmoban模板堂 --zhuo start
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
    //ecmoban模板堂 --zhuo end

    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '$goods_id'" . $type_files . " LIMIT 0, 1";
    $prod = $GLOBALS['db']->getRow($sql);

    if (is_spec($spec) && !empty($prod)) {
        $product_info = get_products_info($goods_id, $spec, $warehouse_id, $area_id);
    }
    if (empty($product_info)) {
        $product_info = array('product_number' => 0, 'product_id' => 0);
    }

    //ecmoban模板堂 --zhuo start
    if ($goods['model_inventory'] == 1) {
        $goods['goods_number'] = $goods['wg_number'];
    } elseif ($goods['model_inventory'] == 2) {
        $goods['goods_number'] = $goods['wag_number'];
    }
    //ecmoban模板堂 --zhuo end

    /* 检查：库存 */
    if ($GLOBALS['_CFG']['use_storage'] == 1) {
        $is_product = 0;
        //商品存在规格 是货品
        if (is_spec($spec) && !empty($prod)) {
            if (!empty($spec)) {
                /* 取规格的货品库存 */
                if ($num > $product_info['product_number']) {
                    $ok_arr['is_ok'] = 3;
                    return $ok_arr;
                }
            }
        } else {
            $is_product = 1;
        }

        if ($is_product == 1) {
            //检查：商品购买数量是否大于总库存
            if ($num > $goods['goods_number']) {
                $ok_arr['is_ok'] = 4;
                return $ok_arr;
            }
        }
    }

    /* 计算商品的促销价格 */
    $warehouse_area['warehouse_id'] = $warehouse_id;
    $warehouse_area['area_id'] = $area_id;

    $spec_price = spec_price($spec, $goods_id, $warehouse_area);
    $goods_price = get_final_price($goods_id, $num, true, $spec, $warehouse_id, $area_id);
    $goods['market_price'] += $spec_price;
    $goods_attr = get_goods_attr_info($spec, 'pice', $warehouse_id, $area_id); //ecmoban模板堂 --zhuo
    $goods_attr_id = join(',', $spec);

    /* 初始化要插入购物车的基本件数据 */
    $parent = array(
        'user_id' => $_SESSION['user_id'],
        'session_id' => $sess,
        'goods_id' => $goods_id,
        'goods_sn' => addslashes($goods['goods_sn']),
        'product_id' => $product_info['product_id'],
        'goods_name' => addslashes($goods['goods_name']),
        'market_price' => $goods['market_price'],
        'goods_attr' => addslashes($goods_attr),
        'goods_attr_id' => $goods_attr_id,
        'is_real' => $goods['is_real'],
        'model_attr' => $goods['model_attr'], //ecmoban模板堂 --zhuo 属性方式
        'warehouse_id' => $warehouse_id, //ecmoban模板堂 --zhuo 仓库
        'area_id' => $area_id, //ecmoban模板堂 --zhuo 仓库地区
        'ru_id' => $goods['ru_id'], //ecmoban模板堂 --zhuo 商家ID
        'extension_code' => $goods['extension_code'],
        'is_gift' => 0,
        'is_shipping' => $goods['is_shipping'],
        'rec_type' => CART_GENERAL_GOODS,
        'add_time' => gmtime(),
        'group_id' => $group
    );

    $attr_info = get_goods_attr_info($spec, 'pice', $warehouse_id, $area_id);

    /* 检查该套餐主件商品是否已经存在在购物车中 */
    $sql = "SELECT goods_number FROM " . $GLOBALS['ecs']->table('cart_combo') .
            " WHERE " . $sess_id . " AND goods_id = '$goods_id' " .
            " AND parent_id = 0 " .
            " AND extension_code <> 'package_buy' " .
            " AND rec_type = 'CART_GENERAL_GOODS' AND group_id = '$group' AND warehouse_id = '$warehouse_id'"; //by mike add

    $row = $GLOBALS['db']->getRow($sql);

    if ($row) {
        $sql = "UPDATE " . $GLOBALS['ecs']->table('cart_combo') . " SET goods_number = '$num'" .
                " , goods_price = '$goods_price'" .
                " , product_id = '" . $product_info['product_id'] . "'" .
                " , goods_attr = '$attr_info'" .
                " , goods_attr_id = '$goods_attr_id'" .
                " , market_price = '" . $goods['market_price'] . "'" .
                " , warehouse_id = '$warehouse_id'" .
                " , area_id = '$area_id'" .
                " WHERE " . $sess_id . " AND goods_id = '$goods_id' " .
                " AND parent_id = 0 " .
                " AND extension_code <> 'package_buy' " .
                "AND rec_type = 'CART_GENERAL_GOODS' AND group_id='$group'";
        $GLOBALS['db']->query($sql);
    } else {
        $parent['goods_price'] = max($goods_price, 0);
        $parent['goods_number'] = $num;
        $parent['parent_id'] = 0;
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('cart_combo'), $parent, 'INSERT');
    }
}

/**
 * 获取商品的原价、配件价、库存（配件组合） by mike
 * 返回数组
 */
function get_combo_goods_info($goods_id, $num = 1, $spec = array(), $parent = 0, $warehouse_area) {
    $result = array();

    /* 取得商品信息 */
    $sql = "SELECT goods_number FROM " . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '$goods_id' AND is_delete = 0";
    $goods = $GLOBALS['db']->getRow($sql);

    /* 如果商品有规格则取规格商品信息 配件除外 */
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('products') . " WHERE goods_id = '$goods_id' LIMIT 0, 1";
    $prod = $GLOBALS['db']->getRow($sql);

    if (is_spec($spec) && !empty($prod)) {
        $product_info = get_products_info($goods_id, $spec);
    }
    if (empty($product_info)) {
        $product_info = array('product_number' => '', 'product_id' => 0);
    }

    //商品库存
    $result['stock'] = $goods['goods_number'];

    //商品存在规格 是货品 检查该货品库存
    if (is_spec($spec) && !empty($prod)) {
        if (!empty($spec)) {
            /* 取规格的货品库存 */
            $result['stock'] = $product_info['product_number'];
        }
    }

    /* 如果该配件在添加为基本件的配件时，所设置的“配件价格”比原价低，即此配件在价格上提供了优惠， */
    $sql = "SELECT parent_id, goods_price " .
            "FROM " . $GLOBALS['ecs']->table('group_goods') .
            " WHERE goods_id = '$goods_id'" .
            " AND parent_id = '$parent'" .
            " ORDER BY goods_price";
    $res = $GLOBALS['db']->query($sql);
    foreach ($res as $row) {
        $result['fittings_price'] = $row['goods_price'];
    }

    /* 计算商品的促销价格 */
    $result['fittings_price'] = (isset($result['fittings_price'])) ? $result['fittings_price'] : get_final_price($goods_id, $num, true, $spec);
    $result['spec_price'] = spec_price($spec, $goods_id, $warehouse_area); //属性价格
    $result['goods_price'] = get_final_price($goods_id, $num, true, $spec);

    return $result;
}

/**
 * 清空购物车
 * @param   int     $type   类型：默认普通商品
 */
function clear_cart($type = CART_GENERAL_GOODS, $cart_value = '') {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }

    $goodsIn = '';
    if (!empty($cart_value)) {
        $goodsIn = " and rec_id in($cart_value)";
    }
    //ecmoban模板堂 --zhuo end

    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') .
            " WHERE " . $sess_id . " AND rec_type = '$type'" . $goodsIn;
    $GLOBALS['db']->query($sql);

    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " user_id = '" . real_cart_mac_ip() . "' ";
    }

    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart_user_info') . " WHERE " . $sess_id;
    $GLOBALS['db']->query($sql);

    unset($_SESSION['cart_value']);
}

/**
 * 获得指定的商品属性
 *
 * @access      public
 * @param       array       $arr        规格、属性ID数组
 * @param       type        $type       设置返回结果类型：pice，显示价格，默认；no，不显示价格
 *
 * @return      string
 */
function get_goods_attr_info($arr, $type = 'pice', $warehouse_id = 0, $area_id = 0) {
    $attr = '';

    if (!empty($arr)) {
        $fmt = "%s:%s[%s] \n";

        //ecmoban模板堂 --zhuo satrt
        $leftJoin = '';

        $leftJoin .= " left join " . $GLOBALS['ecs']->table('goods') . " as g on g.goods_id = ga.goods_id";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_attr') . " as wap on ga.goods_id = wap.goods_id and wap.warehouse_id = '$warehouse_id' and ga.goods_attr_id = wap.goods_attr_id ";
        $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_attr') . " as wa on ga.goods_id = wa.goods_id and wa.area_id = '$area_id' and ga.goods_attr_id = wa.goods_attr_id ";
        //ecmoban模板堂 --zhuo end

        $sql = "SELECT ga.goods_attr_id, a.attr_name, ga.attr_value, " .
                " IF(g.model_attr < 1, ga.attr_price, IF(g.model_attr < 2, wap.attr_price, wa.attr_price)) as attr_price " .
                "FROM " . $GLOBALS['ecs']->table('goods_attr') . " AS ga " .
                $leftJoin .
                " left join " . $GLOBALS['ecs']->table('attribute') . " AS a " . "on a.attr_id = ga.attr_id " .
                "WHERE " . db_create_in($arr, 'ga.goods_attr_id');

        $res = $GLOBALS['db']->query($sql);

        foreach ($res as $row) {

            if ($GLOBALS['_CFG']['goods_attr_price'] == 1) {
                $attr_price = 0;
            } else {
                $attr_price = round(floatval($row['attr_price']), 2);
                $attr_price = price_format($attr_price, false); //ecmoban模板堂 --zhuo
            }
            $row['attr_name'] = str_replace('<em>', '', $row['attr_name']);
            $row['attr_name'] = str_replace('</em>', '', $row['attr_name']);
            $attr .= sprintf($fmt, $row['attr_name'], $row['attr_value'], $attr_price);
        }

        $attr = str_replace('[0]', '', $attr);
    }
    return $attr;
}

/**
 * 取得用户信息
 * @param   int     $user_id    用户id
 * @return  array   用户信息
 */
function user_info($user_id) {
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('users') .
            " WHERE user_id = '$user_id'";
    $user = $GLOBALS['db']->getRow($sql);

    unset($user['question']);
    unset($user['answer']);

    /* 格式化帐户余额 */
    if ($user) {
//        if ($user['user_money'] < 0)
//        {
//            $user['user_money'] = 0;
//        }
        $user['formated_user_money'] = price_format($user['user_money'], false);
        $user['formated_frozen_money'] = price_format($user['frozen_money'], false);
    }

    return $user;
}

/**
 * 修改用户
 * @param   int     $user_id   订单id
 * @param   array   $user      key => value
 * @return  bool
 */
function update_user($user_id, $user) {
    return $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('users'), $user, 'UPDATE', "user_id = '$user_id'");
}

/**
 * 取得用户地址列表
 * @param   int     $user_id    用户id
 * @return  array
 */
function address_list($user_id) {
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('user_address') .
            " WHERE user_id = '$user_id'";

    return $GLOBALS['db']->getAll($sql);
}

/**
 * 取得用户地址信息
 * @param   int     $address_id     地址id
 * @return  array
 */
function address_info($address_id) {
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('user_address') .
            " WHERE address_id = '$address_id'";

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 取得用户当前可用红包
 * @param   int     $user_id        用户id
 * @param   float   $goods_amount   订单商品金额
 * @return  array   红包数组
 */
function user_bonus($user_id, $goods_amount = 0, $cart_value = 0, $num = 10, $start = 1) {
    $where = '';
    if (!empty($cart_value)) {
        $where = "AND c.rec_id in($cart_value)";
    }
    $sql = "SELECT g.user_id FROM " . $GLOBALS['ecs']->table('cart') . " as c," . $GLOBALS['ecs']->table('goods') . " as g" . " WHERE  c.goods_id = g.goods_id $where";
    $goods_list = $GLOBALS['db']->getAll($sql);

    $where = "";
    $goods_user = '';
    if ($goods_list) {
        foreach ($goods_list as $key => $row) {
            $goods_user .= $row['user_id'] . ',';
        }
    }

    if (!empty($goods_user)) {
        $goods_user = substr($goods_user, 0, -1);
        $goods_user = explode(',', $goods_user);
        $goods_user = array_unique($goods_user);
        $goods_user = implode(',', $goods_user);
        $where = " AND IF(t.usebonus_type > 0, t.usebonus_type = 1, t.user_id in($goods_user)) ";
    }

    $day = local_getdate();
    $today = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);

    $sqlCount = "SELECT count(*) ";
    $sql = "SELECT t.type_id, t.type_name, t.type_money, t.user_id, t.usebonus_type, t.use_start_date, t.use_end_date, b.bonus_id ";
    $where = "FROM " . $GLOBALS['ecs']->table('bonus_type') . " AS t," .
            $GLOBALS['ecs']->table('user_bonus') . " AS b " .
            "WHERE t.type_id = b.bonus_type_id " .
            "AND t.use_start_date <= '$today' " .
            "AND t.use_end_date >= '$today' " .
            "AND t.min_goods_amount <= '$goods_amount' " .
            "AND b.user_id<>0 " .
            "AND b.user_id = '$user_id' " .
            "AND b.order_id = 0" . $where;

    $sql .= $where;
    $sqlCount .= $where;
    $count = $GLOBALS['db']->getRow($sqlCount);
    if ($num == 0) {
        $count = $GLOBALS['db']->getRow($sqlCount);
        return $count['count(*)'];
    }
    $res = $GLOBALS['db']->selectLimit($sql, $num, $start);
    return array('list' => $res, 'conut' => $count['count(*)']);
}

/**
 * 取得红包信息
 * @param   int     $bonus_id   红包id
 * @param   string  $bonus_sn   红包序列号
 * @param   array   红包信息
 */
function bonus_info($bonus_id, $bonus_sn = '', $cart_value = 0) {
    $where = '';
    if ($cart_value != 0 || !empty($cart_value)) {
        $sql = "SELECT g.user_id FROM " . $GLOBALS['ecs']->table('cart') . " as c," . $GLOBALS['ecs']->table('goods') . " as g" . " WHERE  c.goods_id = g.goods_id AND c.rec_id in($cart_value)";
        $goods_list = $GLOBALS['db']->getAll($sql);

        $where = "";
        $goods_user = '';
        if ($goods_list) {
            foreach ($goods_list as $key => $row) {
                $goods_user .= $row['user_id'] . ',';
            }
        }

        if (!empty($goods_user)) {
            $goods_user = substr($goods_user, 0, -1);
            $goods_user = explode(',', $goods_user);
            $goods_user = array_unique($goods_user);
            $goods_user = implode(',', $goods_user);
            $where = " AND IF(t.usebonus_type > 0, t.usebonus_type = 1, t.user_id in($goods_user)) ";
        }
    }

    $sql = "SELECT t.*, t.user_id as admin_id, b.* " .
            "FROM " . $GLOBALS['ecs']->table('bonus_type') . " AS t," .
            $GLOBALS['ecs']->table('user_bonus') . " AS b " .
            "WHERE t.type_id = b.bonus_type_id " . $where;
    if ($bonus_id > 0) {
        $sql .= "AND b.bonus_id = '$bonus_id'";
    } else {
        $sql .= "AND b.bonus_sn = '$bonus_sn'";
    }

    return $GLOBALS['db']->getRow($sql);
}

/**
 * 检查红包是否已使用
 * @param   int $bonus_id   红包id
 * @return  bool
 */
function bonus_used($bonus_id) {
    $sql = "SELECT order_id FROM " . $GLOBALS['ecs']->table('user_bonus') .
            " WHERE bonus_id = '$bonus_id'";

    return $GLOBALS['db']->getOne($sql) > 0;
}

/**
 * 设置红包为已使用
 * @param   int     $bonus_id   红包id
 * @param   int     $order_id   订单id
 * @return  bool
 */
function use_bonus($bonus_id, $order_id) {
    $sql = "UPDATE " . $GLOBALS['ecs']->table('user_bonus') .
            " SET order_id = '$order_id', used_time = '" . gmtime() . "' " .
            "WHERE bonus_id = '$bonus_id' LIMIT 1";

    return $GLOBALS['db']->query($sql);
}

/**
 * 设置红包为未使用
 * @param   int     $bonus_id   红包id
 * @param   int     $order_id   订单id
 * @return  bool
 */
function unuse_bonus($bonus_id) {
    $sql = "UPDATE " . $GLOBALS['ecs']->table('user_bonus') .
            " SET order_id = 0, used_time = 0 " .
            "WHERE bonus_id = '$bonus_id' LIMIT 1";

    return $GLOBALS['db']->query($sql);
}

/**
 * 计算积分的价值（能抵多少钱）
 * @param   int     $integral   积分
 * @return  float   积分价值
 */
function value_of_integral($integral) {
    $scale = floatval($GLOBALS['_CFG']['integral_scale']);

    return $scale > 0 ? round(($integral / 100) * $scale, 2) : 0;
}

/**
 * 计算指定的金额需要多少积分
 *
 * @access  public
 * @param   integer $value  金额
 * @return  void
 */
function integral_of_value($value) {
    $scale = floatval($GLOBALS['_CFG']['integral_scale']);

    return $scale > 0 ? round($value / $scale * 100) : 0;
}

/**
 * 订单退款
 * @param   array   $order          订单
 * @param   int     $refund_type    退款方式 1 到帐户余额 2 到退款申请（先到余额，再申请提款） 3 不处理
 * @param   string  $refund_note    退款说明
 * @param   float   $refund_amount  退款金额（如果为0，取订单已付款金额）
 * @return  bool
 */
function order_refund($order, $refund_type, $refund_note, $refund_amount = 0) {
    /* 检查参数 */
    $user_id = $order['user_id'];
    if ($user_id == 0 && $refund_type == 1) {
        die('anonymous, cannot return to account balance');
    }

    $amount = $refund_amount > 0 ? $refund_amount : $order['money_paid'];
    if ($amount <= 0) {
        return true;
    }

    if (!in_array($refund_type, array(1, 2, 3))) {
        die('invalid params');
    }

    /* 备注信息 */
    if ($refund_note) {
        $change_desc = $refund_note;
    } else {
        include_once(LANG_PATH . C('shop.lang') . '/admin/order.php');
        $change_desc = sprintf(L('order_refund'), $order['order_sn']);
    }

    /* 处理退款 */
    if (1 == $refund_type) {
        log_account_change($user_id, $amount, 0, 0, 0, $change_desc);

        return true;
    } elseif (2 == $refund_type) {
        /* 如果非匿名，退回余额 */
        if ($user_id > 0) {
            log_account_change($user_id, $amount, 0, 0, 0, $change_desc);
        }

        /* user_account 表增加提款申请记录 */
        $account = array(
            'user_id' => $user_id,
            'amount' => (-1) * $amount,
            'add_time' => gmtime(),
            'user_note' => $refund_note,
            'process_type' => SURPLUS_RETURN,
            'admin_user' => $_SESSION['admin_name'],
            'admin_note' => sprintf(L('order_refund'), $order['order_sn']),
            'is_paid' => 0
        );
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('user_account'), $account, 'INSERT');

        return true;
    } else {
        return true;
    }
}

/**
 * 获得购物车中的商品
 *
 * @access  public
 * @return  array
 */
function get_cart_goods($cart_value = '', $type = 0, $favourable_list = array()) {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }

    $goodsIn = '';
    if (!empty($cart_value)) {
        $goodsIn = " and rec_id in($cart_value)";
    }
    //ecmoban模板堂 --zhuo end

    /* 初始化 */
    $goods_list = array();
    $total = array(
        'goods_price' => 0, // 本店售价合计（有格式）
        'market_price' => 0, // 市场售价合计（有格式）
        'saving' => 0, // 节省金额（有格式）
        'save_rate' => 0, // 节省百分比
        'goods_amount' => 0, // 本店售价合计（无格式）
    );

    /* 循环、统计 */
    $sql = "SELECT *, IF(parent_id, parent_id, goods_id) AS pid " .
            " FROM " . $GLOBALS['ecs']->table('cart') .
            " WHERE " . $sess_id . " AND rec_type = '" . CART_GENERAL_GOODS . "'" .
            $goodsIn .
            " ORDER BY rec_id DESC";
    $res = $GLOBALS['db']->query($sql);

    /* 用于统计购物车中实体商品和虚拟商品的个数 */
    $virtual_goods_count = 0;
    $real_goods_count = 0;
    $total['subtotal_dis_amount'] = 0;
    $total['subtotal_discount_amount'] = 0;

    if ($GLOBALS['_CFG']['add_shop_price'] == 1) {
        $add_tocart = 1;
    } else {
        $add_tocart = 0;
    }

    foreach ($res as $row) {
        /* 判断购物车商品价格是否与目前售价一致，如果不同则返回购物车价格失效 */
        $currency_format = !empty($GLOBALS['_CFG']['currency_format']) ? explode('%', $GLOBALS['_CFG']['currency_format']) : '';
        $attr_id = !empty($row['goods_attr_id']) ? explode(',', $row['goods_attr_id']) : '';

        if (count($currency_format) > 1) {
            $goods_price = trim(get_final_price($row['goods_id'], $row['goods_number'], true, $attr_id, $row['warehouse_id'], $row['area_id'], 0, 0, $add_tocart), $currency_format[0]);
            $cart_price = trim($row['goods_price'], $currency_format[0]);
        } else {
            $goods_price = get_final_price($row['goods_id'], $row['goods_number'], true, $attr_id, $row['warehouse_id'], $row['area_id'], 0, 0, $add_tocart);
            $cart_price = $row['goods_price'];
        }

        $goods_price = floatval($goods_price);
        $cart_price = floatval($cart_price);

        if ($goods_price != $cart_price && empty($row['is_gift']) && !isset($row['group_id'])) {
            $row['is_invalid'] = 1; //价格已过期
        } else {
            $row['is_invalid'] = 0; //价格未过期
        }

        if ($row['is_invalid'] && $row['rec_type'] == 0 && empty($row['is_gift']) && $row['extension_code'] != 'package_buy') {
            if (isset($_SESSION['flow_type']) && $_SESSION['flow_type'] == 0) {

                get_update_cart_price($goods_price, $row['rec_id']);
                $row['goods_price'] = $goods_price;
            }
        }
        //ecmoban模板堂 --zhuo start 商品金额促销
        $row['goods_amount'] = $row['goods_price'] * $row['goods_number'];
        $goods_con = get_con_goods_amount($row['goods_amount'], $row['goods_id'], 0, 0, $row['parent_id']);

        $goods_con['amount'] = explode(',', $goods_con['amount']);
        $row['amount'] = min($goods_con['amount']);

        $total['goods_price'] += $row['amount'];
        $row['subtotal'] = price_format($row['amount'], false);
        $row['dis_amount'] = $row['goods_amount'] - $row['amount'];
        $row['dis_amount'] = number_format($row['dis_amount'], 2, '.', '');
        $row['discount_amount'] = price_format($row['dis_amount'], false);
        //ecmoban模板堂 --zhuo end 商品金额促销

        $total['subtotal_dis_amount'] += $row['dis_amount'];
        $total['subtotal_discount_amount'] = price_format($total['subtotal_dis_amount'], false);

        //$total['goods_price']  += $row['goods_price'] * $row['goods_number'];
        $total['market_price'] += $row['market_price'] * $row['goods_number'];

        //$row['subtotal']     = price_format($row['goods_price'] * $row['goods_number'], false);
        $row['goods_price'] = price_format($row['goods_price'], false);
        $row['market_price'] = price_format($row['market_price'], false);

        $row['url'] = build_uri('goods', array('gid' => $row['goods_id']), $row['goods_name']);

        //ecmoban模板堂 --zhuo
        $row['region_name'] = $GLOBALS['db']->getOne("select region_name from " . $GLOBALS['ecs']->table('region_warehouse') . " where region_id = '" . $row['warehouse_id'] . "'");

        $sql = "select count(*) from " . $GLOBALS['ecs']->table('cart') . " where parent_id = '" . $row['goods_id'] . "'";
        $row['parent_num'] = $GLOBALS['db']->getOne($sql);

        //ecmoban模板堂 --zhuo start 限购
        $xiangouInfo = get_purchasing_goods_info($row['goods_id']);

        if ($xiangouInfo['is_xiangou'] == 1) {
            $user_id = !empty($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
            $start_date = $xiangouInfo['xiangou_start_date'];
            $end_date = $xiangouInfo['xiangou_end_date'];
            $orderGoods = get_for_purchasing_goods($start_date, $end_date, $row['goods_id'], $user_id);
            $goods_number = $xiangouInfo['xiangou_num'] - $orderGoods['goods_number'];
            $row['xiangounum'] = $goods_number;
        }
        //ecmoban模板堂 --zhuo end 限购

        /* 统计实体商品和虚拟商品的个数 */
        if ($row['is_real']) {
            $real_goods_count++;
        } else {
            $virtual_goods_count++;
        }

        /* 查询规格 */
        if (trim($row['goods_attr']) != '') {
            $row['goods_attr'] = addslashes($row['goods_attr']);
            $sql = "SELECT attr_value FROM " . $GLOBALS['ecs']->table('goods_attr') . " WHERE goods_attr_id " .
                    db_create_in($row['goods_attr']);
            $attr_list = $GLOBALS['db']->getCol($sql);
            foreach ($attr_list AS $attr) {
                $row['goods_name'] .= ' [' . $attr . '] ';
            }
        }
        /* 增加是否在购物车里显示商品图 */
        if (($GLOBALS['_CFG']['show_goods_in_cart'] == "2" || $GLOBALS['_CFG']['show_goods_in_cart'] == "3") && $row['extension_code'] != 'package_buy') {
            $goods_thumb = $GLOBALS['db']->getOne("SELECT `goods_thumb` FROM " . $GLOBALS['ecs']->table('goods') . " WHERE `goods_id`='{$row['goods_id']}'");
            $row['goods_thumb'] = get_image_path($goods_thumb);
        }

        //ecmoban模板堂 --zhuo start
        if ($warehouse_id) {
            $leftJoin = " left join " . $GLOBALS['ecs']->table('warehouse_goods') . " as wg on g.goods_id = wg.goods_id and wg.region_id = '$warehouse_id' ";
            $leftJoin .= " left join " . $GLOBALS['ecs']->table('warehouse_area_goods') . " as wag on g.goods_id = wag.goods_id and wag.region_id = '$area_id' ";

            $sql = "SELECT IF(g.model_price < 1, g.goods_number, IF(g.model_price < 2, wg.region_number, wag.region_number)) AS goods_number, g.user_id, g.model_attr FROM " .
                    $GLOBALS['ecs']->table('goods') . " AS g " . $leftJoin .
                    " WHERE g.goods_id = '" . $row['goods_id'] . "' LIMIT 0, 1";

            $goodsInfo = $GLOBALS['db']->getRow($sql);

            $products = get_warehouse_id_attr_number($row['goods_id'], $row['goods_attr_id'], $goodsInfo['user_id'], $warehouse_id, $area_id);
            $attr_number = $products['product_number'];

            if ($goodsInfo['model_attr'] == 1) {
                $table_products = "products_warehouse";
                $type_files = " and warehouse_id = '$warehouse_id'";
            } elseif ($goodsInfo['model_attr'] == 2) {
                $table_products = "products_area";
                $type_files = " and area_id = '$area_id'";
            } else {
                $table_products = "products";
                $type_files = "";
            }

            $sql = "SELECT * FROM " . $GLOBALS['ecs']->table($table_products) . " WHERE goods_id = '" . $row['goods_id'] . "'" . $type_files . " LIMIT 0, 1";
            $prod = $GLOBALS['db']->getRow($sql);

            if (empty($prod)) { //当商品没有属性库存时
                $attr_number = $goodsInfo['goods_number'];
            }

            $attr_number = !empty($attr_number) ? $attr_number : 0;
            $row['attr_number'] = $attr_number;
        } else {
            $row['attr_number'] = $row['goods_number'];
        }
        //ecmoban模板堂 --zhuo end
        $goods_list[] = $row;
    }

    $total['goods_amount'] = $total['goods_price'];

    $total['saving'] = price_format($total['market_price'] - $total['goods_price'], false);
    if ($total['market_price'] > 0) {
        $total['save_rate'] = $total['market_price'] ? round(($total['market_price'] - $total['goods_price']) *
                        100 / $total['market_price']) . '%' : 0;
    }
    $total['goods_price'] = price_format($total['goods_price'], false);
    $total['market_price'] = price_format($total['market_price'], false);
    $total['real_goods_count'] = $real_goods_count;
    $total['virtual_goods_count'] = $virtual_goods_count;
    //取出配件
    $cart_parts = array();
    foreach ($goods_list as $key => $val) {
        if ($val['parent_id'] > 0) {
            $cart_parts[] = $val;
            unset($goods_list[$key]);
        }
    }
    //按店铺分类商品
    foreach ($goods_list as $k) {
        $ru_id[] = $k['ru_id'];
    }
    if (count($ru_id) > 0) {
        $sql = "SELECT user_id, shoprz_brandName, rz_shopName, shopNameSuffix FROM {pre}merchants_shop_information WHERE user_id in (" . implode(',', $ru_id) . ")";
        $data = $GLOBALS['db']->getAll($sql);
        $data[count($data)] = array(
            'user_id' => 0,
            'shoprz_brandName' => '',
            'shopNameSuffix' => '自营店',
            'rz_shopName' => '自营店'
        );
        foreach ($data as $key => $val) {
            $shop_goods_id = array();
            $goods_list_dsh[$key]['ru_id'] = $val['user_id'];
            $goods_list_dsh[$key]['ru_name'] = get_shop_name($val['user_id'], 1);
            foreach ($goods_list as $k => $list) {
                if ($val['user_id'] == $list['ru_id']) {
                    $goods_list_dsh[$key]['goods_list'][] = $list;
                    $goods_list_dsh[$key]['shop_goods_list'].=$list['goods_id'] . ',';
                    $goods_list_dsh[$key]['amount']+=$list['goods_amount'];
                    $shop_goods_id[] = $list['goods_id'];
                }
            }

            //获取该店铺下的商品有无配件
            $sql = "SELECT goods_id FROM " . $GLOBALS['ecs']->table('group_goods') .
                    "WHERE parent_id in ('" . implode(',', $shop_goods_id) . "')";
            $count = $GLOBALS['db']->getOne($sql);
            $goods_list_dsh[$key]['fitting'] = count($count) > 0 ? 1 : 0;
            $goods_list_dsh[$key]['fitting_goods_array'] = $count;
            $goods_list_dsh[$key]['shop_goods_list'] = substr($goods_list_dsh[$key]['shop_goods_list'], 0, strlen($goods_list_dsh[$key]['shop_goods_list']) - 1);
            //该店铺是否存在优惠卷
            $bonus = $GLOBALS['db']->getOne("SELECT count(user_id) FROM {pre}bonus_type WHERE user_id=" . $val['user_id'] . " AND send_end_date>" . time());
            $goods_list_dsh[$key]['bonus'] = $bonus > 0 ? 1 : 0;
            $favlist = array();
            foreach ($favourable_list as $list) {

                $error = 0;
                if ($val['user_id'] == $list['user_id']) {
                    if (strpos($list['user_rank'], $_SESSION['user_rank']) >= 0) {
                        if ((int) $list['act_range'] == 0) {

                            $error = 1;
                        }
                        if ((int) $list['act_range'] == 1) {//分类
                            $arr = explode(',', $list['act_range_ext']);
                            if (!empty($goods_list_dsh[$key]['goods_list'])) {
                                foreach ($goods_list_dsh[$key]['goods_list'] as $fv_c) {
                                    $cat = get_goods_info($fv_c['goods_id']);
                                    //取得分类下所有子分类
                                    $child = '';
                                    foreach ($arr as $arrkey) {
                                        $all = get_child_tree('11');
                                        if ($all) {
                                            foreach ($all as $allkey) {
                                                $child .=$allkey['id'] . ',';
                                                if (count($allkey['cat_id']) > 0) {
                                                    foreach ($allkey['cat_id'] as $nextkey) {
                                                        $child .=$nextkey['id'] . ',';
                                                        if (count($nextkey['cat_id']) > 0) {
                                                            foreach ($nextkey['cat_id'] as $butkey) {
                                                                $child .=$butkey['id'] . ',';
                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $child = explode(',', $child);
                                    if (in_array($cat['cat_id'], $child)) {
                                        $error = 1;
                                    }
                                }
                            }
                        }
                        if ((int) $list['act_range'] == 2) {//品牌
                            $arr = explode(',', $list['act_range_ext']);
                            if (!empty($goods_list_dsh[$key]['goods_list'])) {
                                foreach ($goods_list_dsh[$key]['goods_list'] as $fv_b) {
                                    $brand = get_goods_info($fv_b['goods_id']);
                                    if (in_array($brand['brand_id'], $arr)) {
                                        $error = 1;
                                    };
                                }
                            }
                        }
                        if ((int) $list['act_range'] == 3) {//商品
                            $arr = explode(',', $list['act_range_ext']);
                            if (!empty($goods_list_dsh[$key]['goods_list'])) {
                                foreach ($goods_list_dsh[$key]['goods_list'] as $fv_g) {
                                    if (in_array($fv_g['goods_id'], $arr)) {
                                        $error = 1;
                                    };
                                }
                            }
                        }
                        if ($error) {
                            $favlist[] = array(
                                'act_id' => $list['act_id'],
                                'act_name' => $list['act_name'],
                                'act_type' => $list['act_type'],
                                'min_amount' => $list['min_amount'],
                                'max_amount' => $list['max_amount'],
                                'userfav_type' => $list['userfav_type'],
                            );
                        }
                    }
                }
            }

            $favlist = count($favlist) > 0 ? $favlist : '';
            $goods_list_dsh[$key]['favourable'] = $favlist;
            if (isset($favlist[0]['act_id'])) {
                $goods_list_dsh[$key]['act_id'] = $favlist[0]['act_id'];
            }
            if (count($goods_list_dsh[$key]['goods_list']) <= 0) {
                unset($goods_list_dsh[$key]);
            }
        }
    }
    if ($type == 1) {
        $goods_list = get_cart_goods_ru_list($goods_list, $type);
        $goods_list = get_cart_ru_goods_list($goods_list);
    }
    if (count($goods_list_dsh) > 0) {
        foreach ($goods_list_dsh as $key => $val) {
            $goods_list_dsh[$key]['num'] = count($val['goods_list']);
        }
    }

    if (!empty($cart_parts)) {
        foreach ($goods_list_dsh as $dsh => $key) {
            $num = count($key['goods_list']);
            foreach ($key['goods_list'] as $k => $val) {
                //配件展示在主商品下
                foreach ($cart_parts as $cart => $parts) {
                    if ($val['goods_id'] == $parts['parent_id']) {
                        $goods_list_dsh[$dsh]['goods_list'][$num] = $parts;
                        $sql = 'UPDATE ' . $GLOBALS['ecs']->table('cart') . ' SET ' .
                                "ru_id = '$val[user_id]' " .
                                "WHERE rec_id = '$parts[rec_id]'";
                        $GLOBALS['db']->query($sql);
                        unset($cart_parts[$cart]);
                        $num++;
                    }
                }
            }
        }
    }

    return array('goods_list' => $goods_list_dsh, 'total' => $total, 'goods_list_old' => $goods_list);
}

/*
 * 更新商品最新价格
 */

function get_update_cart_price($goods_price = 0, $rec_id = 0) {
    $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') . " SET goods_price = '$goods_price' WHERE rec_id = '$rec_id'";
    $GLOBALS['db']->query($sql);
}

/**
 * 区分商家商品
 */
function get_cart_ru_goods_list($goods_list, $cart_value = '', $consignee = '', $store_id = 0) {

    if (!empty($_SESSION['user_id'])) {
        $sess = $_SESSION['user_id'];
    } else {
        $sess = real_cart_mac_ip();
    }
    //配送方式选择
    $point_id = isset($_SESSION['flow_consignee']['point_id']) ? intval($_SESSION['flow_consignee']['point_id']) : 0;
    $consignee_district_id = isset($_SESSION['flow_consignee']['district']) ? intval($_SESSION['flow_consignee']['district']) : 0;

    $arr = array();

    foreach ($goods_list as $key => $row) {
        $shipping_type = isset($_SESSION['merchants_shipping'][$key]['shipping_type']) ? intval($_SESSION['merchants_shipping'][$key]['shipping_type']) : 0;
        $ru_name = get_shop_name($key, 1);
        $arr[$key]['ru_id'] = $key;
        $arr[$key]['shipping_type'] = $shipping_type;
        $arr[$key]['ru_name'] = $ru_name;
        $arr[$key]['url'] = build_uri('merchants_store', array('urid' => $key), $ru_name);

        if ($cart_value) {
            $arr[$key]['shipping'] = get_ru_shippng_info($row, $cart_value, $key, $consignee);

            if (!empty($arr[$key]['shipping'])) {
                $arr[$key]['tmp_shipping_id'] = isset($arr[$key]['shipping'][0]['shipping_id']) ? $arr[$key]['shipping'][0]['shipping_id'] : 0; //默认选中第一个配送方式
                foreach ($arr[$key]['shipping'] as $kk => $vv) {
                    if ($vv['default'] == 1) {
                        $arr[$key]['tmp_shipping_id'] = $vv['shipping_id'];
                        continue;
                    }
                }
            }
        }
        //自营有自提点--key=ru_id
        if ($key == 0 && $consignee_district_id > 0) {
            $self_point = get_self_point($consignee_district_id, $point_id, 1);

            if (!empty($self_point)) {
                $arr[$key]['self_point'] = $self_point;
            }
        }
        /* 获取门店信息 by kong 20160726 start */
        if ($store_id > 0) {
            $sql = "SELECT o.id,o.stores_name,o.stores_address,o.stores_opening_hours,o.stores_tel,o.stores_traffic_line,p.region_name as province ,"
                    . "c.region_name as city ,d.region_name as district,o.stores_img FROM " . $GLOBALS['ecs']->table("offline_store") . " AS o "
                    . "LEFT JOIN " . $GLOBALS['ecs']->table("region") . " AS p ON p.region_id = o.province "
                    . "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS c ON c.region_id = o.city "
                    . "LEFT JOIN " . $GLOBALS['ecs']->table('region') . " AS d ON d.region_id = o.district "
                    . "WHERE o.id = '$store_id'  LIMIT 1";
            $arr[$key]['offline_store'] = $GLOBALS['db']->getRow($sql);
        }
        /* 获取门店信息 by kong 20160726 end */
        $arr[$key]['goods_list'] = $row;
    }
    return array_values($arr);
}

/*
 * 查询商家默认配送方式
 */

function get_ru_shippng_info($cart_goods, $cart_value, $ru_id, $consignee = '') {

    //分离商家信息by wu start
    $cart_value_arr = array();
    foreach ($cart_goods as $cgk => $cgv) {
        if ($cgv['ru_id'] != $ru_id) {
            unset($cart_goods[$cgk]);
        } else {
            $cart_value_list = explode(',', $cart_value);
            if (in_array($cgv['rec_id'], $cart_value_list)) {
                $cart_value_arr[] = $cgv['rec_id'];
            }
        }
    }

    $cart_value = implode(',', $cart_value_arr);
    //分离商家信息by wu end

    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }

    /* 取得购物类型 */
    $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

    $order = flow_order_info();
    $seller_shipping = get_seller_shipping_type($ru_id);
    $shipping_id = $seller_shipping['shipping_id'];

    $consignee = isset($_SESSION['flow_consignee']) ? $_SESSION['flow_consignee'] : $consignee;
    $region = array($consignee['country'], $consignee['province'], $consignee['city'], $consignee['district'], $consignee['street']);

    $cart_weight_price = cart_weight_price($flow_type, $cart_value); //ecmoban模板堂 --zhuo
    $insure_disabled = true;
    $cod_disabled = true;

    // 查看购物车中是否全为免运费商品，若是则把运费赋为零
    $sql = 'SELECT count(*) FROM ' . $GLOBALS['ecs']->table('cart') . " WHERE " . $sess_id . " AND `extension_code` != 'package_buy' AND `is_shipping` = 0 AND ru_id='" . $ru_id . "'";
    $shipping_count = $GLOBALS['db']->getOne($sql);

    $sql = 'SELECT s.shipping_id, s.shipping_code, s.shipping_name, ' .
            's.shipping_desc, s.insure, s.support_cod, a.configure ' .
            'FROM ' . $GLOBALS['ecs']->table('shipping') . ' AS s, ' .
            $GLOBALS['ecs']->table('shipping_area') . ' AS a, ' .
            $GLOBALS['ecs']->table('area_region') . ' AS r ' .
            'WHERE r.region_id ' . db_create_in($region) .
            " AND r.shipping_area_id = a.shipping_area_id AND a.shipping_id = s.shipping_id AND s.enabled = 1 AND a.ru_id = '$ru_id' ORDER BY s.shipping_order"; //by wu
    $shipping_list = $GLOBALS['db']->getAll($sql);

    foreach ($shipping_list as $key => $val) {
        if ($GLOBALS['_CFG']['freight_model'] == 0) {

            $shipping_cfg = unserialize_config($val['configure']);
            $configure = unserialize($val['configure']);

            /* 商品单独设置运费价格 start */
            if ($cart_goods) {
                if (count($cart_goods) == 1) {

                    $cart_goods = array_values($cart_goods);

                    if (!empty($cart_goods[0]['freight']) && $cart_goods[0]['is_shipping'] == 0) {

                        if ($cart_goods[0]['freight'] == 1) {
                            $configure[1]['value'] = $cart_goods[0]['shipping_fee'] * $cart_goods[0]['goods_number'];
                        } else {

                            $trow = get_goods_transport($cart_goods[0]['tid']);

                            $transport = array('top_area_id', 'area_id', 'tid', 'ru_id', 'sprice');
                            $transport_where = " AND ru_id = '" . $cart_goods[0]['ru_id'] . "' AND tid = '" . $cart_goods[0]['tid'] . "'";
                            $goods_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $consignee['city'], $transport, $transport_where, 'goods_transport_extend', 'area_id');

                            $ship_transport = array('tid', 'ru_id', 'shipping_fee');
                            $ship_transport_where = " AND ru_id = '" . $cart_goods[0]['ru_id'] . "' AND tid = '" . $cart_goods[0]['tid'] . "'";
                            $goods_ship_transport = $GLOBALS['ecs']->get_select_find_in_set(2, $val['shipping_id'], $ship_transport, $ship_transport_where, 'goods_transport_express', 'shipping_id');

                            if ($trow['type'] == 1) {
                                $configure[1]['value'] = $goods_transport['sprice'] * $cart_goods[0]['goods_number'] + $goods_ship_transport['shipping_fee'] * $cart_goods[0]['goods_number'];
                            } else {
                                $configure[1]['value'] = $goods_transport['sprice'] + $goods_ship_transport['shipping_fee'];
                            }
                        }
                    }
                } else {
                    $order_transpor = get_order_transport($cart_goods, $consignee, $val['shipping_id'], $val['shipping_code']);

                    if ($order_transpor['freight']) {
                        $configure[1]['value'] += $order_transpor['sprice']; /* 有配送按配送区域计算运费 */
                    } else {
                        $configure[1]['value'] = $order_transpor['sprice'];
                    }
                }
            }
            /* 商品单独设置运费价格 end */

            $shipping_fee = ($shipping_count == 0 AND $cart_weight_price['free_shipping'] == 1) ? 0 : shipping_fee($val['shipping_code'], $configure, $cart_weight_price['weight'], $cart_weight_price['amount'], $cart_weight_price['number']);

            $shipping_list[$key]['free_money'] = price_format($shipping_cfg['free_money'], false);

        } elseif ($GLOBALS['_CFG']['freight_model'] == 1) {
            $goods_region = array(
                'country' => $region[0],
                'province' => $region[1],
                'city' => $region[2],
                'district' => $region[3],
                'street' => $region[4]
            );

            $shippingFee = get_goods_order_shipping_fee($cart_goods, $goods_region, $val['shipping_id']);

            $shipping_fee = ($shipping_count == 0 AND $cart_weight_price['free_shipping'] == 1) ? 0 : $shippingFee['shipping_fee'];

            $shippingFee['free_money'] = isset($shippingFee['free_money']) ? $shippingFee['free_money'] : 0;
            $shipping_list[$key]['free_money'] = price_format($shippingFee['free_money'], false);
        }

        $shipping_list[$key]['shipping_id'] = $val['shipping_id'];
        $shipping_list[$key]['shipping_name'] = $val['shipping_name'];
        $shipping_list[$key]['shipping_code'] = $val['shipping_code'];
        $shipping_list[$key]['format_shipping_fee'] = price_format($shipping_fee, false);
        $shipping_list[$key]['shipping_fee'] = $shipping_fee;
        $shipping_list[$key]['insure_formated'] = strpos($val['insure'], '%') === false ? price_format($val['insure'], false) : $val['insure'];

        /* 当前的配送方式是否支持保价 */
        if ($val['shipping_id'] == $order['shipping_id']) {
            $insure_disabled = ($val['insure'] == 0);
            $cod_disabled = ($val['support_cod'] == 0);
        }

        // 兼容过滤ecjia配送方式
        if (substr($val['shipping_code'], 0, 5) == 'ship_') {
            unset($shipping_list[$key]);
        }

        //默认配送方式
        $shipping_list[$key]['default'] = 0;
        if ($shipping_id == $val['shipping_id']) {
            $shipping_list[$key]['default'] = 1;
        }

        $shipping_list[$key]['insure_disabled'] = $insure_disabled;
        $shipping_list[$key]['cod_disabled'] = $cod_disabled;
    }

    //去掉重复配送方式 by wu start
    $shipping_type = array();
    foreach ($shipping_list as $key => $val) {
        @$shipping_type[$val['shipping_code']][] = $key;
    }
    foreach ($shipping_type as $key => $val) {
        if (count($val) > 1) {
            for ($i = 1; $i < count($val); $i++) {
                unset($shipping_list[$val[$i]]);
            }
        }
    }
    //去掉重复配送方式 by wu end

    return $shipping_list;
}

//查询购买N件商品
function get_buy_cart_goods_number($type = CART_GENERAL_GOODS, $cart_value = '', $ru_type = 0) {
    if ($type == CART_PRESALE_GOODS) {
        $where = " g.is_on_sale = 0 AND g.is_delete = 0 AND ";
    } else {
        $where = " g.is_on_sale = 1 AND g.is_delete = 0 AND ";
    }

    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }

    $goodsIn = '';
    if (!empty($cart_value)) {
        $goodsIn = " and c.rec_id in($cart_value)";
    }
    //ecmoban模板堂 --zhuo end

    $sql = "SELECT SUM(c.goods_number) FROM " . $GLOBALS['ecs']->table('cart') .
            " AS c LEFT JOIN " . $GLOBALS['ecs']->table('goods') .
            " AS g ON c.goods_id = g.goods_id WHERE $where " . $c_sess .
            "AND rec_type = '$type'" . $goodsIn;

    return $GLOBALS['db']->getOne($sql);
}

//提交订单配送方式 --ecmoban模板堂 --zhuo
function get_order_post_shipping($shipping, $ru_id) {

    $shipping_list = array();
    if ($shipping) {
        $shipping_id = '';
        $shipping_name = '';
        foreach ($shipping as $k1 => $v1) {

            $shippingInfo = shipping_info($v1);

            foreach ($ru_id as $k2 => $v2) {
                if ($k1 == $k2) {
                    $shipping_id .= $v2 . "|" . $v1 . ",";  //商家ID + 配送ID
                    $shipping_name .= $v2 . "|" . $shippingInfo['shipping_name'] . ",";  //商家ID + 配送名称
                }
            }
        }

        $shipping_id = substr($shipping_id, 0, -1);
        $shipping_name = substr($shipping_name, 0, -1);
        $shipping_list = array('shipping_id' => $shipping_id, 'shipping_name' => $shipping_name);
    }
    return $shipping_list;
}

/**
 * 取得收货人信息
 * @param   int     $user_id    用户编号
 * @return  array
 */
function get_consignee($user_id) {
    if (isset($_SESSION['flow_consignee']) && !empty($_SESSION['flow_consignee']) && isset($_SESSION['flow_consignee']['address_id'])) {
        /* 如果存在session，则直接返回session中的收货人信息 */
        return $_SESSION['flow_consignee'];
    } else {
        /* 如果不存在，则取得用户的默认收货人信息 */
        $arr = array();

        if ($user_id > 0) {
            /* 取默认地址 */
            $sql = "SELECT ua.*" .
                    " FROM " . $GLOBALS['ecs']->table('user_address') . "AS ua, " . $GLOBALS['ecs']->table('users') . ' AS u ' .
                    " WHERE u.user_id='$user_id' AND ua.address_id = u.address_id";

            $arr = $GLOBALS['db']->getRow($sql);
        }
        return $arr;
    }
}

/**
 * 查询购物车（订单id为0）或订单中是否有实体商品
 * @param   int     $order_id   订单id
 * @param   int     $flow_type  购物流程类型
 * @return  bool
 */
function exist_real_goods($order_id = 0, $flow_type = CART_GENERAL_GOODS, $cart_value = '') {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    if ($order_id <= 0) {
        $where = '';
        if ($cart_value) {
            $where .= " AND rec_id IN($cart_value)";
        }

        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('cart') .
                " WHERE " . $sess_id . " AND is_real = 1 " .
                "AND rec_type = '$flow_type' $where";
    } else {
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_goods') .
                " WHERE order_id = '$order_id' AND is_real = 1";
    }

    return $GLOBALS['db']->getOne($sql) > 0;
}

/**
 * 检查收货人信息是否完整
 * @param   array   $consignee  收货人信息
 * @param   int     $flow_type  购物流程类型
 * @return  bool    true 完整 false 不完整
 */
function check_consignee_info($consignee, $flow_type) {
    if (exist_real_goods(0, $flow_type)) {
        /* 如果存在实体商品 */
        $res = !empty($consignee['consignee']) &&
                //!empty($consignee['country']) &&
                //!empty($consignee['email']) &&
                (!empty($consignee['tel']) || !empty($consignee['mobile']));

        if ($res) {
            if (empty($consignee['province'])) {
                /* 没有设置省份，检查当前国家下面有没有设置省份 */
                $pro = get_regions(1, $consignee['country']);
                $res = empty($pro);
            } elseif (empty($consignee['city'])) {
                /* 没有设置城市，检查当前省下面有没有城市 */
                $city = get_regions(2, $consignee['province']);
                $res = empty($city);
            }
            /* elseif (empty($consignee['district']))
              {
              $dist = get_regions(3, $consignee['city']);
              $res = empty($dist);
              } */
        }

        return $res;
    } else {
        /* 如果不存在实体商品 */
        return !empty($consignee['consignee']) &&
                //!empty($consignee['email']) &&
                (!empty($consignee['tel']) || !empty($consignee['mobile']));
    }
}

/**
 * 获得上一次用户采用的支付和配送方式
 *
 * @access  public
 * @return  void
 */
function last_shipping_and_payment() {
    $sql = "SELECT shipping_id, pay_id " .
            " FROM " . $GLOBALS['ecs']->table('order_info') .
            " WHERE user_id = '$_SESSION[user_id]' " .
            " ORDER BY order_id DESC LIMIT 1";
    $row = $GLOBALS['db']->getRow($sql);

    if (empty($row)) {
        /* 如果获得是一个空数组，则返回默认值 */
        $row = array('shipping_id' => 0, 'pay_id' => 0);
    }

    return $row;
}

/**
 * 取得当前用户应该得到的红包总额
 */
function get_total_bonus() {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    $day = getdate();
    $today = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);

    /* 按商品发的红包 */
    $sql = "SELECT SUM(c.goods_number * t.type_money)" .
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, "
            . $GLOBALS['ecs']->table('bonus_type') . " AS t, "
            . $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE " . $c_sess .
            "AND c.is_gift = 0 " .
            "AND c.goods_id = g.goods_id " .
            "AND g.bonus_type_id = t.type_id " .
            "AND t.send_type = '" . SEND_BY_GOODS . "' " .
            "AND t.send_start_date <= '$today' " .
            "AND t.send_end_date >= '$today' " .
            "AND c.rec_type = '" . CART_GENERAL_GOODS . "'";
    $goods_total = floatval($GLOBALS['db']->getOne($sql));

    /* 取得购物车中非赠品总金额 */
    $sql = "SELECT SUM(goods_price * goods_number) " .
            "FROM " . $GLOBALS['ecs']->table('cart') .
            " WHERE " . $sess_id .
            " AND is_gift = 0 " .
            " AND rec_type = '" . CART_GENERAL_GOODS . "'";
    $amount = floatval($GLOBALS['db']->getOne($sql));

    /* 按订单发的红包 */
    $sql = "SELECT FLOOR('$amount' / min_amount) * type_money " .
            "FROM " . $GLOBALS['ecs']->table('bonus_type') .
            " WHERE send_type = '" . SEND_BY_ORDER . "' " .
            " AND send_start_date <= '$today' " .
            "AND send_end_date >= '$today' " .
            "AND min_amount > 0 ";
    $order_total = floatval($GLOBALS['db']->getOne($sql));

    return $goods_total + $order_total;
}

/**
 * 处理红包（下订单时设为使用，取消（无效，退货）订单时设为未使用
 * @param   int     $bonus_id   红包编号
 * @param   int     $order_id   订单号
 * @param   int     $is_used    是否使用了
 */
function change_user_bonus($bonus_id, $order_id, $is_used = true) {
    if ($is_used) {
        $sql = 'UPDATE ' . $GLOBALS['ecs']->table('user_bonus') . ' SET ' .
                'used_time = ' . gmtime() . ', ' .
                "order_id = '$order_id' " .
                "WHERE bonus_id = '$bonus_id'";
    } else {
        $sql = 'UPDATE ' . $GLOBALS['ecs']->table('user_bonus') . ' SET ' .
                'used_time = 0, ' .
                'order_id = 0 ' .
                "WHERE bonus_id = '$bonus_id'";
    }
    $GLOBALS['db']->query($sql);
}

/**
 * 获得订单信息
 *
 * @access  private
 * @return  array
 */
function flow_order_info() {
    $order = isset($_SESSION['flow_order']) ? $_SESSION['flow_order'] : array();

    /* 初始化配送和支付方式 */
    if (!isset($order['shipping_id']) || !isset($order['pay_id'])) {
        /* 如果还没有设置配送和支付 */
        if ($_SESSION['user_id'] > 0) {
            /* 用户已经登录了，则获得上次使用的配送和支付 */
            $arr = last_shipping_and_payment();

            if (!isset($order['shipping_id'])) {
                $order['shipping_id'] = $arr['shipping_id'];
            }
            if (!isset($order['pay_id'])) {
                $order['pay_id'] = $arr['pay_id'];
            }
        } else {
            if (!isset($order['shipping_id'])) {
                $order['shipping_id'] = 0;
            }
            if (!isset($order['pay_id'])) {
                $order['pay_id'] = 0;
            }
        }
    }

    if (!isset($order['pack_id'])) {
        $order['pack_id'] = 0;  // 初始化包装
    }
    if (!isset($order['card_id'])) {
        $order['card_id'] = 0;  // 初始化贺卡
    }
    if (!isset($order['bonus'])) {
        $order['bonus'] = 0;    // 初始化红包
    }
    if (!isset($order['integral'])) {
        $order['integral'] = 0; // 初始化积分
    }
    if (!isset($order['surplus'])) {
        $order['surplus'] = 0;  // 初始化余额
    }
    if (!isset($order['cou_id'])) {
        $order['cou_id'] = 0;  // 初始化优惠券
    }
    /* 扩展信息 */
    if (isset($_SESSION['flow_type']) && intval($_SESSION['flow_type']) != CART_GENERAL_GOODS) {
        $order['extension_code'] = $_SESSION['extension_code'];
        $order['extension_id'] = $_SESSION['extension_id'];
    }

    return $order;
}

/**
 * 合并订单
 * @param   string  $from_order_sn  从订单号
 * @param   string  $to_order_sn    主订单号
 * @return  成功返回true，失败返回错误信息
 */
function merge_order($from_order_sn, $to_order_sn) {
    /* 订单号不能为空 */
    if (trim($from_order_sn) == '' || trim($to_order_sn) == '') {
        return L('order_sn_not_null');
    }

    /* 订单号不能相同 */
    if ($from_order_sn == $to_order_sn) {
        return L('two_order_sn_same');
    }

    /* 取得订单信息 */
    $from_order = order_info(0, $from_order_sn);
    $to_order = order_info(0, $to_order_sn);

    /* 检查订单是否存在 */
    if (!$from_order) {
        return sprintf(L('order_not_exist'), $from_order_sn);
    } elseif (!$to_order) {
        return sprintf(L('order_not_exist'), $to_order_sn);
    }

    /* 检查合并的订单是否为普通订单，非普通订单不允许合并 */
    if ($from_order['extension_code'] != '' || $to_order['extension_code'] != 0) {
        return L('merge_invalid_order');
    }

    /* 检查订单状态是否是已确认或未确认、未付款、未发货 */
    if ($from_order['order_status'] != OS_UNCONFIRMED && $from_order['order_status'] != OS_CONFIRMED) {
        return sprintf(L('os_not_unconfirmed_or_confirmed'), $from_order_sn);
    } elseif ($from_order['pay_status'] != PS_UNPAYED) {
        return sprintf(L('ps_not_unpayed'), $from_order_sn);
    } elseif ($from_order['shipping_status'] != SS_UNSHIPPED) {
        return sprintf(L('ss_not_unshipped'), $from_order_sn);
    }

    if ($to_order['order_status'] != OS_UNCONFIRMED && $to_order['order_status'] != OS_CONFIRMED) {
        return sprintf(L('os_not_unconfirmed_or_confirmed'), $to_order_sn);
    } elseif ($to_order['pay_status'] != PS_UNPAYED) {
        return sprintf(L('ps_not_unpayed'), $to_order_sn);
    } elseif ($to_order['shipping_status'] != SS_UNSHIPPED) {
        return sprintf(L('ss_not_unshipped'), $to_order_sn);
    }

    /* 检查订单用户是否相同 */
    if ($from_order['user_id'] != $to_order['user_id']) {
        return L('order_user_not_same');
    }

    /* 合并订单 */
    $order = $to_order;
    $order['order_id'] = '';
    $order['add_time'] = gmtime();

    // 合并商品总额
    $order['goods_amount'] += $from_order['goods_amount'];

    // 合并折扣
    $order['discount'] += $from_order['discount'];

    if ($order['shipping_id'] > 0) {
        // 重新计算配送费用
        $weight_price = order_weight_price($to_order['order_id']);
        $from_weight_price = order_weight_price($from_order['order_id']);
        $weight_price['weight'] += $from_weight_price['weight'];
        $weight_price['amount'] += $from_weight_price['amount'];
        $weight_price['number'] += $from_weight_price['number'];

        $region_id_list = array($order['country'], $order['province'], $order['city'], $order['district']);
        $shipping_area = shipping_area_info($order['shipping_id'], $region_id_list);

        $order['shipping_fee'] = shipping_fee($shipping_area['shipping_code'], unserialize($shipping_area['configure']), $weight_price['weight'], $weight_price['amount'], $weight_price['number']);

        // 如果保价了，重新计算保价费
        if ($order['insure_fee'] > 0) {
            $order['insure_fee'] = shipping_insure_fee($shipping_area['shipping_code'], $order['goods_amount'], $shipping_area['insure']);
        }
    }

    // 重新计算包装费、贺卡费
    if ($order['pack_id'] > 0) {
        $pack = pack_info($order['pack_id']);
        $order['pack_fee'] = $pack['free_money'] > $order['goods_amount'] ? $pack['pack_fee'] : 0;
    }
    if ($order['card_id'] > 0) {
        $card = card_info($order['card_id']);
        $order['card_fee'] = $card['free_money'] > $order['goods_amount'] ? $card['card_fee'] : 0;
    }

    // 红包不变，合并积分、余额、已付款金额
    $order['integral'] += $from_order['integral'];
    $order['integral_money'] = value_of_integral($order['integral']);
    $order['surplus'] += $from_order['surplus'];
    $order['money_paid'] += $from_order['money_paid'];

    // 计算应付款金额（不包括支付费用）
    $order['order_amount'] = $order['goods_amount'] - $order['discount'] + $order['shipping_fee'] + $order['insure_fee'] + $order['pack_fee'] + $order['card_fee'] - $order['bonus'] - $order['integral_money'] - $order['surplus'] - $order['money_paid'];

    // 重新计算支付费
    if ($order['pay_id'] > 0) {
        // 货到付款手续费
        $cod_fee = $shipping_area ? $shipping_area['pay_fee'] : 0;
        $order['pay_fee'] = pay_fee($order['pay_id'], $order['order_amount'], $cod_fee);

        // 应付款金额加上支付费
        $order['order_amount'] += $order['pay_fee'];
    }

    /* 插入订单表 */
    do {
        $order['order_sn'] = get_order_sn();
        if ($GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('order_info'), addslashes_deep($order), 'INSERT')) {
            break;
        } else {
            if ($GLOBALS['db']->errno() != 1062) {
                die($GLOBALS['db']->errorMsg());
            }
        }
    } while (true); // 防止订单号重复

    /* 订单号 */
    $order_id = $GLOBALS['db']->insert_id();

    /* 更新订单商品 */
    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_goods') .
            " SET order_id = '$order_id' " .
            "WHERE order_id " . db_create_in(array($from_order['order_id'], $to_order['order_id']));
    $GLOBALS['db']->query($sql);

    /* 插入支付日志 */
    insert_pay_log($order_id, $order['order_amount'], PAY_ORDER);

    /* 删除原订单 */
    $sql = 'DELETE FROM ' . $GLOBALS['ecs']->table('order_info') .
            " WHERE order_id " . db_create_in(array($from_order['order_id'], $to_order['order_id']));
    $GLOBALS['db']->query($sql);

    /* 删除原订单支付日志 */
    $sql = 'DELETE FROM ' . $GLOBALS['ecs']->table('pay_log') .
            " WHERE order_id " . db_create_in(array($from_order['order_id'], $to_order['order_id']));
    $GLOBALS['db']->query($sql);

    /* 返还 from_order 的红包，因为只使用 to_order 的红包 */
    if ($from_order['bonus_id'] > 0) {
        unuse_bonus($from_order['bonus_id']);
    }

    /* 返回成功 */
    return true;
}

/**
 * 查询配送区域属于哪个办事处管辖
 * @param   array   $regions    配送区域（1、2、3、4级按顺序）
 * @return  int     办事处id，可能为0
 */
function get_agency_by_regions($regions) {
    if (!is_array($regions) || empty($regions)) {
        return 0;
    }

    $arr = array();
    $sql = "SELECT region_id, agency_id " .
            "FROM " . $GLOBALS['ecs']->table('region') .
            " WHERE region_id " . db_create_in($regions) .
            " AND region_id > 0 AND agency_id > 0";
    $res = $GLOBALS['db']->query($sql);
    foreach ($res as $row) {
        $arr[$row['region_id']] = $row['agency_id'];
    }
    if (empty($arr)) {
        return 0;
    }

    $agency_id = 0;
    for ($i = count($regions) - 1; $i >= 0; $i--) {
        if (isset($arr[$regions[$i]])) {
            return $arr[$regions[$i]];
        }
    }
}

/**
 * 获取配送插件的实例
 * @param   int   $shipping_id    配送插件ID
 * @return  object     配送插件对象实例
 */
function &get_shipping_object($shipping_id) {
    $shipping = shipping_info($shipping_id);
    if (!$shipping) {
        $object = new stdClass();
        return $object;
    }

    $file_path = ADDONS_PATH . 'shipping/' . $shipping['shipping_code'] . '.php';

    include_once($file_path);

    $object = new $shipping['shipping_code'];
    return $object;
}

/**
 * 改变订单中商品库存
 * @param   int     $order_id   订单号
 * @param   bool    $is_dec     是否减少库存
 * @param   bool    $storage     减库存的时机，1，下订单时；0，发货时；
 */
function change_order_goods_storage($order_id, $is_dec = true, $storage = 0, $use_storage = 0, $admin_id = 0) { //ecmoban模板堂 --zhuo
    /* 查询订单商品信息 */
    switch ($storage) {
        case 0 :
            $sql = "SELECT goods_id, SUM(send_number) AS num, MAX(extension_code) AS extension_code, product_id, warehouse_id, area_id FROM " . $GLOBALS['ecs']->table('order_goods') .
                    " WHERE order_id = '$order_id' AND is_real = 1 GROUP BY goods_id, product_id";
            break;

        case 1 :
            $sql = "SELECT goods_id, SUM(goods_number) AS num, MAX(extension_code) AS extension_code, product_id, warehouse_id, area_id FROM " . $GLOBALS['ecs']->table('order_goods') .
                    " WHERE order_id = '$order_id' AND is_real = 1 GROUP BY goods_id, product_id";
            break;
    }

    $res = $GLOBALS['db']->query($sql);
    foreach ($res as $row) {
        if ($row['extension_code'] != "package_buy") {
            if ($is_dec) {
                change_goods_storage($row['goods_id'], $row['product_id'], - $row['num'], $row['warehouse_id'], $row['area_id'], $order_id, $use_storage, $admin_id);
            } else {
                change_goods_storage($row['goods_id'], $row['product_id'], $row['num'], $row['warehouse_id'], $row['area_id'], $order_id, $use_storage, $admin_id);
            }
            $GLOBALS['db']->query($sql);
        } else {
            $sql = "SELECT goods_id, goods_number" .
                    " FROM " . $GLOBALS['ecs']->table('package_goods') .
                    " WHERE package_id = '" . $row['goods_id'] . "'";
            $res_goods = $GLOBALS['db']->query($sql);
            foreach ($res_goods as $row_goods) {
                $sql = "SELECT is_real" .
                        " FROM " . $GLOBALS['ecs']->table('goods') .
                        " WHERE goods_id = '" . $row_goods['goods_id'] . "'";
                $real_goods = $GLOBALS['db']->query($sql);
                $is_goods = $GLOBALS['db']->getRow($sql);
                //$is_goods = $GLOBALS['db']->fetchRow($real_goods);

                if ($is_dec) {
                    change_goods_storage($row_goods['goods_id'], $row['product_id'], - ($row['num'] * $row_goods['goods_number']), $row['warehouse_id'], $row['area_id'], $order_id, $use_storage, $admin_id);
                } elseif ($is_goods['is_real']) {
                    change_goods_storage($row_goods['goods_id'], $row['product_id'], ($row['num'] * $row_goods['goods_number']), $row['warehouse_id'], $row['area_id'], $order_id, $use_storage, $admin_id);
                }
            }
        }
    }
}

/**
 * 商品库存增与减 货品库存增与减
 *
 * @param   int    $good_id         商品ID
 * @param   int    $product_id      货品ID
 * @param   int    $number          增减数量，默认0；
 *
 * @return  bool               true，成功；false，失败；
 */
function change_goods_storage($good_id, $product_id, $number = 0, $warehouse_id = 0, $area_id = 0, $order_id, $use_storage, $admin_id) { //ecmoban模板堂 --zhuo
    if ($number == 0) {
        return true; // 值为0即不做、增减操作，返回true
    }

    if (empty($good_id) || empty($number)) {
        return false;
    }

    $number = ($number > 0) ? '+ ' . $number : $number;

    //ecmoban模板堂 --zhuo start
    $sql = "select model_inventory, model_attr from " . $GLOBALS['ecs']->table('goods') . " where goods_id = '$good_id'";
    $goods = $GLOBALS['db']->getRow($sql);
    //ecmoban模板堂 --zhuo end

    /* 处理货品库存 */
    $products_query = true;
    $abs_number = abs($number);
    if (!empty($product_id)) {
        //ecmoban模板堂 --zhuo start
        if ($goods['model_attr'] == 1) {
            $table_products = "products_warehouse";
        } elseif ($goods['model_attr'] == 2) {
            $table_products = "products_area";
        } else {
            $table_products = "products";
        }
        //ecmoban模板堂 --zhuo end

        if ($number < 0) {
            $set_update = "IF(product_number >= $abs_number, product_number $number, 0)";
        } else {
            $set_update = "product_number $number";
        }

        $sql = "UPDATE " . $GLOBALS['ecs']->table($table_products) . "
                SET product_number = $set_update
                WHERE goods_id = '$good_id'
                AND product_id = '$product_id'
                LIMIT 1";

        $products_query = $GLOBALS['db']->query($sql);
    } else {

        if ($number < 0) {
            if ($goods['model_inventory'] == 1 || $goods['model_inventory'] == 2) {
                $set_update = "IF(region_number >= $abs_number, region_number $number, 0)";
            } else {
                $set_update = "IF(goods_number >= $abs_number, goods_number $number, 0)";
            }
        } else {
            if ($goods['model_inventory'] == 1 || $goods['model_inventory'] == 2) {
                $set_update = "region_number $number";
            } else {
                $set_update = "goods_number $number";
            }
        }

        /* 处理商品库存 */ //ecmoban模板堂 --zhuo
        if ($goods['model_inventory'] == 1) {
            $sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_goods') .
                    " SET  region_number = $set_update
                        WHERE goods_id = '$good_id' and region_id = '$warehouse_id'
                        LIMIT 1";
        } elseif ($goods['model_inventory'] == 2) {

            $sql = "UPDATE " . $GLOBALS['ecs']->table('warehouse_area_goods') .
                    " SET  region_number = $set_update
                        WHERE goods_id = '$good_id' and region_id = '$area_id'
                        LIMIT 1";
        } else {

            $sql = "UPDATE " . $GLOBALS['ecs']->table('goods') .
                    " SET  goods_number = $set_update
                        WHERE goods_id = '$good_id'
                        LIMIT 1";
        }

        $query = $GLOBALS['db']->query($sql);
    }

    $logs_other = array(
        'goods_id' => $good_id,
        'order_id' => $order_id,
        'use_storage' => $use_storage,
        'admin_id' => $admin_id,
        'number' => $number,
        'model_inventory' => $goods['model_inventory'],
        'model_attr' => $goods['model_attr'],
        'product_id' => $product_id,
        'warehouse_id' => $warehouse_id,
        'area_id' => $area_id,
        'add_time' => gmtime()
    );

    $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('goods_inventory_logs'), $logs_other, 'INSERT');

    if ($query && $products_query) {
        return true;
    } else {
        return false;
    }
}

/**
 * 取得支付方式id列表
 * @param   bool    $is_cod 是否货到付款
 * @return  array
 */
function payment_id_list($is_cod) {
    $sql = "SELECT pay_id FROM " . $GLOBALS['ecs']->table('payment');
    if ($is_cod) {
        $sql .= " WHERE is_cod = 1";
    } else {
        $sql .= " WHERE is_cod = 0";
    }

    return $GLOBALS['db']->getCol($sql);
}

/**
 * 生成查询订单的sql
 * @param   string  $type   类型
 * @param   string  $alias  order表的别名（包括.例如 o.）
 * @return  string
 */
function order_query_sql($type = 'finished', $alias = '') {
    /* 已完成订单 */
    if ($type == 'finished') {
        return " AND {$alias}order_status " . db_create_in(array(OS_CONFIRMED, OS_SPLITED)) .
                " AND {$alias}shipping_status " . db_create_in(array(SS_SHIPPED, SS_RECEIVED)) .
                " AND {$alias}pay_status " . db_create_in(array(PS_PAYED, PS_PAYING)) . " ";
    }
    /* 待发货订单 */ elseif ($type == 'await_ship') {
        return " AND   {$alias}order_status " .
                db_create_in(array(OS_CONFIRMED, OS_SPLITED, OS_SPLITING_PART)) .
                " AND   {$alias}shipping_status " .
                db_create_in(array(SS_UNSHIPPED, SS_PREPARING, SS_SHIPPED_ING)) .
                " AND ( {$alias}pay_status " . db_create_in(array(PS_PAYED, PS_PAYING)) . " OR {$alias}pay_id " . db_create_in(payment_id_list(true)) . ") ";
    }
    /* 待付款订单 */ elseif ($type == 'await_pay') {
        return " AND   {$alias}order_status " . db_create_in(array(OS_CONFIRMED, OS_SPLITED)) .
                " AND   {$alias}pay_status = '" . PS_UNPAYED . "'" .
                " AND ( {$alias}shipping_status " . db_create_in(array(SS_SHIPPED, SS_RECEIVED)) . " OR {$alias}pay_id " . db_create_in(payment_id_list(false)) . ") ";
    }
    /* 未确认订单 */ elseif ($type == 'unconfirmed') {
        return " AND {$alias}order_status = '" . OS_UNCONFIRMED . "' ";
    }
    /* 未处理订单：用户可操作 */ elseif ($type == 'unprocessed') {
        return " AND {$alias}order_status " . db_create_in(array(OS_UNCONFIRMED, OS_CONFIRMED)) .
                " AND {$alias}shipping_status = '" . SS_UNSHIPPED . "'" .
                " AND {$alias}pay_status = '" . PS_UNPAYED . "' ";
    }
    /* 未付款未发货订单：管理员可操作 */ elseif ($type == 'unpay_unship') {
        return " AND {$alias}order_status " . db_create_in(array(OS_UNCONFIRMED, OS_CONFIRMED)) .
                " AND {$alias}shipping_status " . db_create_in(array(SS_UNSHIPPED, SS_PREPARING)) .
                " AND {$alias}pay_status = '" . PS_UNPAYED . "' ";
    }
    /* 已发货订单：不论是否付款 */ elseif ($type == 'shipped') {
        return " AND {$alias}order_status = '" . OS_CONFIRMED . "'" .
                " AND {$alias}shipping_status " . db_create_in(array(SS_SHIPPED, SS_RECEIVED)) . " ";
    } else {
        die('函数 order_query_sql 参数错误');
    }
}

/**
 * 生成查询订单总金额的字段
 * @param   string  $alias  order表的别名（包括.例如 o.）
 * @return  string
 */
function order_amount_field($alias = '', $ru_id = 0) {
    return "   {$alias}goods_amount + {$alias}tax + {$alias}shipping_fee" .
            " + {$alias}insure_fee + {$alias}pay_fee + {$alias}pack_fee" .
            " + {$alias}card_fee ";
}

/**
 * 生成计算应付款金额的字段
 * @param   string  $alias  order表的别名（包括.例如 o.）
 * @return  string
 */
function order_due_field($alias = '') {
    return order_amount_field($alias) .
            " - {$alias}money_paid - {$alias}surplus - {$alias}integral_money" .
            " - {$alias}bonus - {$alias}discount ";
}

/**
 * 计算折扣：根据购物车和优惠活动
 * @return  float   折扣
 * $type 0-默认 1-分单
 * $use_type 购物流程显示 0， 分单使用 1
 */
function compute_discount($type = 0, $newInfo = array(), $use_type = 0, $ru_id = 0) {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    /* 查询优惠活动 */
    $now = gmtime();
    $user_rank = ',' . $_SESSION['user_rank'] . ',';
    $sql = "SELECT *" .
            "FROM " . $GLOBALS['ecs']->table('favourable_activity') .
            " WHERE start_time <= '$now'" .
            " AND end_time >= '$now'" .
            " AND review_status = 3 " .
            " AND CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'" .
            " AND act_type " . db_create_in(array(FAT_DISCOUNT, FAT_PRICE));
    $favourable_list = $GLOBALS['db']->getAll($sql);

    if (!$favourable_list) {
        return 0;
    }

    if ($type == 0 || $type == 3) {

        $where = '';
        if ($type == 3) {
            if (!empty($newInfo)) {
                $where = " AND c.rec_id in(" . $newInfo . ")";
            }
        }

        /* 查询购物车商品 */
        $sql = "SELECT c.goods_id, c.goods_price * c.goods_number AS subtotal, g.cat_id, g.brand_id, c.ru_id " .
                "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, " . $GLOBALS['ecs']->table('goods') . " AS g " .
                "WHERE c.goods_id = g.goods_id " .
                "AND " . $c_sess .
                "AND c.parent_id = 0 " .
                "AND c.is_gift = 0 " .
                "AND rec_type = '" . CART_GENERAL_GOODS . "'" . $where;
        $goods_list = $GLOBALS['db']->getAll($sql);
    } elseif ($type == 2) {
        $goods_list = array();

        foreach ($newInfo as $key => $row) {
            $order_goods = $GLOBALS['db']->getRow("SELECT cat_id, brand_id FROM" . $GLOBALS['ecs']->table('goods') . " WHERE goods_id = '" . $row['goods_id'] . "'");
            $goods_list[$key]['goods_id'] = $row['goods_id'];
            $goods_list[$key]['cat_id'] = $order_goods['cat_id'];
            $goods_list[$key]['brand_id'] = $order_goods['brand_id'];
            $goods_list[$key]['ru_id'] = $row['ru_id'];
            $goods_list[$key]['subtotal'] = $row['goods_price'] * $row['goods_number'];
        }
    }

    if (!$goods_list) {
        return 0;
    }

    /* 初始化折扣 */
    $discount = 0;
    $favourable_name = array();

    /* 循环计算每个优惠活动的折扣 */
    foreach ($favourable_list as $favourable) {
        $total_amount = 0;
        if ($favourable['act_range'] == FAR_ALL) {
            foreach ($goods_list as $goods) {
                //ecmoban模板堂 --zhuo start
                if ($use_type == 1) {
                    if ($favourable['user_id'] == $goods['ru_id']) {
                        $total_amount += $goods['subtotal'];
                    }
                } else {
                    if ($favourable['userFav_type'] == 1) {
                        $total_amount += $goods['subtotal'];
                    } else {
                        if ($favourable['user_id'] == $goods['ru_id']) {
                            $total_amount += $goods['subtotal'];
                        }
                    }
                }
                //ecmoban模板堂 --zhuo end
            }
        } elseif ($favourable['act_range'] == FAR_CATEGORY) {
            /* 找出分类id的子分类id */
            $id_list = array();
            $raw_id_list = explode(',', $favourable['act_range_ext']);
            foreach ($raw_id_list as $id) {
                $cat_keys = get_array_keys_cat(intval($id));

                $id_list = array_merge($id_list, $cat_keys);
            }
            $ids = join(',', array_unique($id_list));

            foreach ($goods_list as $goods) {
                if (strpos(',' . $ids . ',', ',' . $goods['cat_id'] . ',') !== false) {
                    //ecmoban模板堂 --zhuo start
                    if ($use_type == 1) {
                        if ($favourable['user_id'] == $goods['ru_id'] && $favourable['userFav_type'] == 0) {
                            $total_amount += $goods['subtotal'];
                        }
                    } else {
                        if ($favourable['userFav_type'] == 1) {
                            $total_amount += $goods['subtotal'];
                        } else {
                            if ($favourable['user_id'] == $goods['ru_id']) {
                                $total_amount += $goods['subtotal'];
                            }
                        }
                    }
                    //ecmoban模板堂 --zhuo end
                }
            }
        } elseif ($favourable['act_range'] == FAR_BRAND) {
            foreach ($goods_list as $goods) {
                if (strpos(',' . $favourable['act_range_ext'] . ',', ',' . $goods['brand_id'] . ',') !== false) {
                    //ecmoban模板堂 --zhuo start
                    if ($use_type == 1) {
                        if ($favourable['user_id'] == $goods['ru_id']) {
                            $total_amount += $goods['subtotal'];
                        }
                    } else {
                        if ($favourable['userFav_type'] == 1) {
                            $total_amount += $goods['subtotal'];
                        } else {
                            if ($favourable['user_id'] == $goods['ru_id']) {
                                $total_amount += $goods['subtotal'];
                            }
                        }
                    }
                    //ecmoban模板堂 --zhuo end
                }
            }
        } elseif ($favourable['act_range'] == FAR_GOODS) {
            foreach ($goods_list as $goods) {
                if (strpos(',' . $favourable['act_range_ext'] . ',', ',' . $goods['goods_id'] . ',') !== false) {
                    //ecmoban模板堂 --zhuo start
                    if ($use_type == 1) {
                        if ($favourable['user_id'] == $goods['ru_id']) {
                            $total_amount += $goods['subtotal'];
                        }
                    } else {
                        if ($favourable['userFav_type'] == 1) {
                            $total_amount += $goods['subtotal'];
                        } else {
                            if ($favourable['user_id'] == $goods['ru_id']) {
                                $total_amount += $goods['subtotal'];
                            }
                        }
                    }
                    //ecmoban模板堂 --zhuo end
                }
            }
        } else {
            continue;
        }

        /* 如果金额满足条件，累计折扣 */
        if ($total_amount > 0 && $total_amount >= $favourable['min_amount'] && ($total_amount <= $favourable['max_amount'] || $favourable['max_amount'] == 0)) {
            if ($favourable['act_type'] == FAT_DISCOUNT) {
                $discount += $total_amount * (1 - $favourable['act_type_ext'] / 100);

                $favourable_name[] = $favourable['act_name'];
            } elseif ($favourable['act_type'] == FAT_PRICE) {
                $discount += $favourable['act_type_ext'];

                $favourable_name[] = $favourable['act_name'];
            }
        }
    }

    return array('discount' => $discount, 'name' => $favourable_name);
}

/**
 * 取得购物车该赠送的积分数
 * @return  int     积分数
 */
function get_give_integral($goods = array(), $cart_value) {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    $where = '';
    if (!empty($cart_value)) {
        $where = " AND c.rec_id in($cart_value)";
    }

    $sql = "SELECT SUM(c.goods_number * IF(g.give_integral > -1, g.give_integral, c.goods_price))" .
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, " .
            $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE c.goods_id = g.goods_id " .
            "AND " . $c_sess .
            "AND c.goods_id > 0 " .
            "AND c.parent_id = 0 " .
            "AND c.rec_type = 0 " .
            "AND c.is_gift = 0" . $where;

    return intval($GLOBALS['db']->getOne($sql));
}

/**
 * 取得某订单应该赠送的积分数
 * @param   array   $order  订单
 * @return  int     积分数
 */
function integral_to_give($order) {
    /* 判断是否团购 */
    if ($order['extension_code'] == 'group_buy') {
        include_once(BASE_PATH . 'helpers/goods_helper.php');
        $group_buy = group_buy_info(intval($order['extension_id']));

        return array('custom_points' => $group_buy['gift_integral'], 'rank_points' => $order['goods_amount']);
    } else {
        $sql = "SELECT SUM(og.goods_number * IF(g.give_integral > -1, g.give_integral, og.goods_price)) AS custom_points, SUM(og.goods_number * IF(g.rank_integral > -1, g.rank_integral, og.goods_price)) AS rank_points " .
                "FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og, " .
                $GLOBALS['ecs']->table('goods') . " AS g " .
                "WHERE og.goods_id = g.goods_id " .
                "AND og.order_id = '$order[order_id]' " .
                "AND og.goods_id > 0 " .
                "AND og.parent_id = 0 " .
                "AND og.is_gift = 0 AND og.extension_code != 'package_buy'";

        return $GLOBALS['db']->getRow($sql);
    }
}

/**
 * 发红包：发货时发红包
 * @param   int     $order_id   订单号
 * @return  bool
 */
function send_order_bonus($order_id) {
    /* 取得订单应该发放的红包 */
    $bonus_list = order_bonus($order_id);

    /* 如果有红包，统计并发送 */
    if ($bonus_list) {
        /* 用户信息 */
        $sql = "SELECT u.user_id, u.user_name, u.email " .
                "FROM " . $GLOBALS['ecs']->table('order_info') . " AS o, " .
                $GLOBALS['ecs']->table('users') . " AS u " .
                "WHERE o.order_id = '$order_id' " .
                "AND o.user_id = u.user_id ";
        $user = $GLOBALS['db']->getRow($sql);

        /* 统计 */
        $count = 0;
        $money = '';
        foreach ($bonus_list AS $bonus) {
            $count += $bonus['number'];
            $money .= price_format($bonus['type_money']) . ' [' . $bonus['number'] . '], ';

            /* 修改用户红包 */
            $sql = "INSERT INTO " . $GLOBALS['ecs']->table('user_bonus') . " (bonus_type_id, user_id) " .
                    "VALUES('$bonus[type_id]', '$user[user_id]')";
            for ($i = 0; $i < $bonus['number']; $i++) {
                if (!$GLOBALS['db']->query($sql)) {
                    return $GLOBALS['db']->errorMsg();
                }
            }
        }

        /* 如果有红包，发送邮件 */
        if ($count > 0) {
            $tpl = get_mail_template('send_bonus');
            $GLOBALS['smarty']->assign('user_name', $user['user_name']);
            $GLOBALS['smarty']->assign('count', $count);
            $GLOBALS['smarty']->assign('money', $money);
            $GLOBALS['smarty']->assign('shop_name', $GLOBALS['_CFG']['shop_name']);
            $GLOBALS['smarty']->assign('send_date', local_date($GLOBALS['_CFG']['date_format']));
            $GLOBALS['smarty']->assign('sent_date', local_date($GLOBALS['_CFG']['date_format']));
            $content = $GLOBALS['smarty']->fetch('str:' . $tpl['template_content']);
            send_mail($user['user_name'], $user['email'], $tpl['template_subject'], $content, $tpl['is_html']);
        }
    }

    return true;
}

/**
 * 返回订单发放的红包
 * @param   int     $order_id   订单id
 */
function return_order_bonus($order_id) {
    /* 取得订单应该发放的红包 */
    $bonus_list = order_bonus($order_id);

    /* 删除 */
    if ($bonus_list) {
        /* 取得订单信息 */
        $order = order_info($order_id);
        $user_id = $order['user_id'];

        foreach ($bonus_list AS $bonus) {
            $sql = "DELETE FROM " . $GLOBALS['ecs']->table('user_bonus') .
                    " WHERE bonus_type_id = '$bonus[type_id]' " .
                    "AND user_id = '$user_id' " .
                    "AND order_id = '0' LIMIT " . $bonus['number'];
            $GLOBALS['db']->query($sql);
        }
    }
}

/**
 * 取得订单应该发放的红包
 * @param   int     $order_id   订单id
 * @return  array
 */
function order_bonus($order_id) {
    /* 查询按商品发的红包 */
    $day = getdate();
    $today = local_mktime(23, 59, 59, $day['mon'], $day['mday'], $day['year']);

    $sql = "SELECT b.type_id, b.type_money, SUM(o.goods_number) AS number " .
            "FROM " . $GLOBALS['ecs']->table('order_goods') . " AS o, " .
            $GLOBALS['ecs']->table('goods') . " AS g, " .
            $GLOBALS['ecs']->table('bonus_type') . " AS b " .
            " WHERE o.order_id = '$order_id' " .
            " AND o.is_gift = 0 " .
            " AND o.goods_id = g.goods_id " .
            " AND g.bonus_type_id = b.type_id " .
            " AND b.send_type = '" . SEND_BY_GOODS . "' " .
            " AND b.send_start_date <= '$today' " .
            " AND b.send_end_date >= '$today' " .
            " GROUP BY b.type_id ";
    $list = $GLOBALS['db']->getAll($sql);

    /* 查询定单中非赠品总金额 */
    $amount = order_amount($order_id, false);

    /* 查询订单日期 */
    $sql = "SELECT add_time " .
            " FROM " . $GLOBALS['ecs']->table('order_info') .
            " WHERE order_id = '$order_id' LIMIT 1";
    $order_time = $GLOBALS['db']->getOne($sql);

    /* 查询按订单发的红包 */
    $sql = "SELECT type_id, type_money, IFNULL(FLOOR('$amount' / min_amount), 1) AS number " .
            "FROM " . $GLOBALS['ecs']->table('bonus_type') .
            "WHERE send_type = '" . SEND_BY_ORDER . "' " .
            "AND send_start_date <= '$order_time' " .
            "AND send_end_date >= '$order_time' ";
    $list = array_merge($list, $GLOBALS['db']->getAll($sql));

    return $list;
}

/**
 * 计算购物车中的商品能享受红包支付的总额
 * @return  float   享受红包支付的总额
 */
function compute_discount_amount($cart_value = '') {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $c_sess = " c.user_id = '" . $_SESSION['user_id'] . "' ";
    } else {
        $c_sess = " c.session_id = '" . real_cart_mac_ip() . "' ";
    }
    //ecmoban模板堂 --zhuo end

    /* 查询优惠活动 */
    $now = gmtime();
    $user_rank = ',' . $_SESSION['user_rank'] . ',';
    $sql = "SELECT *" .
            "FROM " . $GLOBALS['ecs']->table('favourable_activity') .
            " WHERE start_time <= '$now'" .
            " AND end_time >= '$now'" .
            " AND review_status = 3 " .
            " AND CONCAT(',', user_rank, ',') LIKE '%" . $user_rank . "%'" .
            " AND act_type " . db_create_in(array(FAT_DISCOUNT, FAT_PRICE));
    $favourable_list = $GLOBALS['db']->getAll($sql);
    if (!$favourable_list) {
        return 0;
    }

    $where = '';
    if (!empty($cart_value)) {
        $where = " AND c.rec_id in(" . $cart_value . ")";
    }

    /* 查询购物车商品 */
    $sql = "SELECT c.goods_id, c.goods_price * c.goods_number AS subtotal, g.cat_id, g.brand_id, c.ru_id " .
            "FROM " . $GLOBALS['ecs']->table('cart') . " AS c, " . $GLOBALS['ecs']->table('goods') . " AS g " .
            "WHERE c.goods_id = g.goods_id " .
            "AND " . $c_sess .
            "AND c.parent_id = 0 " .
            "AND c.is_gift = 0 " .
            "AND rec_type = '" . CART_GENERAL_GOODS . "'" . $where;
    $goods_list = $GLOBALS['db']->getAll($sql);
    if (!$goods_list) {
        return 0;
    }

    /* 初始化折扣 */
    $discount = 0;
    $favourable_name = array();

    /* 循环计算每个优惠活动的折扣 */
    foreach ($favourable_list as $favourable) {
        $total_amount = 0;
        if ($favourable['act_range'] == FAR_ALL) {
            foreach ($goods_list as $goods) {
                //ecmoban模板堂 --zhuo start
                if ($favourable['userFav_type'] == 1) {
                    $total_amount += $goods['subtotal'];
                } else {
                    if ($favourable['user_id'] == $goods['ru_id']) {
                        $total_amount += $goods['subtotal'];
                    }
                }
                //ecmoban模板堂 --zhuo end
            }
        } elseif ($favourable['act_range'] == FAR_CATEGORY) {
            /* 找出分类id的子分类id */
            $id_list = array();
            $raw_id_list = explode(',', $favourable['act_range_ext']);
            foreach ($raw_id_list as $id) {
                $id_list = array_merge($id_list, array_keys(cat_list($id, 0)));
            }
            $ids = join(',', array_unique($id_list));

            foreach ($goods_list as $goods) {
                if (strpos(',' . $ids . ',', ',' . $goods['cat_id'] . ',') !== false) {
                    //ecmoban模板堂 --zhuo start
                    if ($favourable['userFav_type'] == 1) {
                        $total_amount += $goods['subtotal'];
                    } else {
                        if ($favourable['user_id'] == $goods['ru_id']) {
                            $total_amount += $goods['subtotal'];
                        }
                    }
                    //ecmoban模板堂 --zhuo end
                }
            }
        } elseif ($favourable['act_range'] == FAR_BRAND) {
            foreach ($goods_list as $goods) {
                if (strpos(',' . $favourable['act_range_ext'] . ',', ',' . $goods['brand_id'] . ',') !== false) {
                    //ecmoban模板堂 --zhuo start
                    if ($favourable['userFav_type'] == 1) {
                        $total_amount += $goods['subtotal'];
                    } else {
                        if ($favourable['user_id'] == $goods['ru_id']) {
                            $total_amount += $goods['subtotal'];
                        }
                    }
                    //ecmoban模板堂 --zhuo end
                }
            }
        } elseif ($favourable['act_range'] == FAR_GOODS) {
            foreach ($goods_list as $goods) {
                if (strpos(',' . $favourable['act_range_ext'] . ',', ',' . $goods['goods_id'] . ',') !== false) {
                    //ecmoban模板堂 --zhuo start
                    if ($favourable['userFav_type'] == 1) {
                        $total_amount += $goods['subtotal'];
                    } else {
                        if ($favourable['user_id'] == $goods['ru_id']) {
                            $total_amount += $goods['subtotal'];
                        }
                    }
                    //ecmoban模板堂 --zhuo end
                }
            }
        } else {
            continue;
        }
        if ($total_amount > 0 && $total_amount >= $favourable['min_amount'] && ($total_amount <= $favourable['max_amount'] || $favourable['max_amount'] == 0)) {
            if ($favourable['act_type'] == FAT_DISCOUNT) {
                $discount += $total_amount * (1 - $favourable['act_type_ext'] / 100);
            } elseif ($favourable['act_type'] == FAT_PRICE) {
                $discount += $favourable['act_type_ext'];
            }
        }
    }


    return $discount;
}

/**
 * 添加礼包到购物车
 *
 * @access  public
 * @param   integer $package_id   礼包编号
 * @param   integer $num          礼包数量
 * @return  boolean
 */
function add_package_to_cart($package_id, $num = 1, $warehouse_id, $area_id) {
    //ecmoban模板堂 --zhuo start
    if (!empty($_SESSION['user_id'])) {
        $sess_id = " user_id = '" . $_SESSION['user_id'] . "' ";
        $sess = "";
    } else {
        $sess_id = " session_id = '" . real_cart_mac_ip() . "' ";
        $sess = real_cart_mac_ip();
    }
    //ecmoban模板堂 --zhuo end

    $GLOBALS['err']->clean();

    /* 取得礼包信息 */
    $package = get_package_info($package_id);

    if (empty($package)) {
        $GLOBALS['err']->add(L('goods_not_exists'), ERR_NOT_EXISTS);

        return false;
    }

    /* 是否正在销售 */
    if ($package['is_on_sale'] == 0) {
        $GLOBALS['err']->add(L('not_on_sale'), ERR_NOT_ON_SALE);

        return false;
    }

    /* 现有库存是否还能凑齐一个礼包 */
    if ($GLOBALS['_CFG']['use_storage'] == '1' && judge_package_stock($package_id)) {
        $GLOBALS['err']->add(sprintf(L('shortage'), 1), ERR_OUT_OF_STOCK);

        return false;
    }

    /* 检查库存 */
//    if ($GLOBALS['_CFG']['use_storage'] == 1 && $num > $package['goods_number'])
//    {
//        $num = $goods['goods_number'];
//        $GLOBALS['err']->add(sprintf(L('shortage'), $num), ERR_OUT_OF_STOCK);
//
//        return false;
//    }

    /* 初始化要插入购物车的基本件数据 */
    $parent = array(
        'user_id' => $_SESSION['user_id'],
        'session_id' => $sess,
        'goods_id' => $package_id,
        'goods_sn' => '',
        'goods_name' => addslashes($package['package_name']),
        'market_price' => $package['market_package'],
        'goods_price' => $package['package_price'],
        'goods_number' => $num,
        'goods_attr' => '',
        'goods_attr_id' => '',
        'warehouse_id' => $warehouse_id, //ecmoban模板堂 --zhuo 仓库
        'area_id' => $area_id, //ecmoban模板堂 --zhuo 仓库地区
        'ru_id' => $package['user_id'],
        'is_real' => $package['is_real'],
        'extension_code' => 'package_buy',
        'is_gift' => 0,
        'rec_type' => CART_GENERAL_GOODS,
        'add_time' => gmtime()
    );

    /* 如果数量不为0，作为基本件插入 */
    if ($num > 0) {
        /* 检查该商品是否已经存在在购物车中 */
        $sql = "SELECT goods_number FROM " . $GLOBALS['ecs']->table('cart') .
                " WHERE " . $sess_id . " AND goods_id = '" . $package_id . "' " .
                " AND parent_id = 0 AND extension_code = 'package_buy' " .
                " AND rec_type = '" . CART_GENERAL_GOODS . "'";

        $row = $GLOBALS['db']->getRow($sql);

        if ($row) { //如果购物车已经有此物品，则更新
            $num += $row['goods_number'];
            if ($GLOBALS['_CFG']['use_storage'] == 0 || $num > 0) {
                $sql = "UPDATE " . $GLOBALS['ecs']->table('cart') . " SET goods_number = '" . $num . "'" .
                        " WHERE " . $sess_id . " AND goods_id = '$package_id' " .
                        " AND parent_id = 0 AND extension_code = 'package_buy' " .
                        " AND rec_type = '" . CART_GENERAL_GOODS . "'";
                $GLOBALS['db']->query($sql);
            } else {
                $GLOBALS['err']->add(sprintf(L('shortage'), $num), ERR_OUT_OF_STOCK);
                return false;
            }
        } else { //购物车没有此物品，则插入
            $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('cart'), $parent, 'INSERT');
        }
    }

    /* 把赠品删除 */
    $sql = "DELETE FROM " . $GLOBALS['ecs']->table('cart') . " WHERE " . $sess_id . " AND is_gift <> 0";
    $GLOBALS['db']->query($sql);

    return true;
}

/**
 * 得到新发货单号
 * @return  string
 */
function get_delivery_sn() {
    /* 选择一个随机的方案 */
    mt_srand((double) microtime() * 1000000);

    return date('YmdHi') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

/**
 * 检查礼包内商品的库存
 * @return  boolen
 */
function judge_package_stock($package_id, $package_num = 1) {
    $sql = "SELECT goods_id, product_id, goods_number
            FROM " . $GLOBALS['ecs']->table('package_goods') . "
            WHERE package_id = '" . $package_id . "'";
    $row = $GLOBALS['db']->getAll($sql);
    if (empty($row)) {
        return true;
    }

    /* 分离货品与商品 */
    $goods = array('product_ids' => '', 'goods_ids' => '');
    foreach ($row as $value) {
        if ($value['product_id'] > 0) {
            $goods['product_ids'] .= ',' . $value['product_id'];
            continue;
        }

        $goods['goods_ids'] .= ',' . $value['goods_id'];
    }

    $model_attr = get_table_date("goods", "goods_id = '$goods_id'", array('model_attr'), 2);

    //ecmoban模板堂 --zhuo start
    if ($model_attr == 1) {
        $table_products = "products_warehouse";
    } elseif ($model_attr == 2) {
        $table_products = "products_area";
    } else {
        $table_products = "products";
    }
    //ecmoban模板堂 --zhuo end

    /* 检查货品库存 */
    if ($goods['product_ids'] != '') {
        $sql = "SELECT p.product_id
                FROM " . $GLOBALS['ecs']->table($table_products) . " AS p, " . $GLOBALS['ecs']->table('package_goods') . " AS pg
                WHERE pg.product_id = p.product_id
                AND pg.package_id = '$package_id'
                AND pg.goods_number * $package_num > p.product_number
                AND p.product_id IN (" . trim($goods['product_ids'], ',') . ")";
        $row = $GLOBALS['db']->getAll($sql);

        if (!empty($row)) {
            return true;
        }
    }

    //ecmoban模板堂 --zhuo start
    $model_inventory = get_table_date("goods", "goods_id = '$goods_id'", array('model_inventory'), 2);
    if ($model_inventory == 1) {
        $table_products = "warehouse_goods";
        $goods_number = "g.region_number";
    } elseif ($model_inventory == 2) {
        $table_products = "warehouse_area_goods";
        $goods_number = "g.region_number";
    } else {
        $table_products = "goods";
        $goods_number = "g.goods_number";
    }
    //ecmoban模板堂 --zhuo end

    /* 检查商品库存 */
    if ($goods['goods_ids'] != '') {
        $sql = "SELECT g.goods_id
                FROM " . $GLOBALS['ecs']->table($table_products) . "AS g, " . $GLOBALS['ecs']->table('package_goods') . " AS pg
                WHERE pg.goods_id = g.goods_id
                AND pg.goods_number * $package_num > " . $goods_number . "
                AND pg.package_id = '" . $package_id . "'
                AND pg.goods_id IN (" . trim($goods['goods_ids'], ',') . ")";
        $row = $GLOBALS['db']->getAll($sql);

        if (!empty($row)) {
            return true;
        }
    }

    return false;
}

/**
 *  by　　Leah
 * @param type $shipping_config
 * @return type
 */
function free_price($shipping_config) {

    $shipping_config = unserialize($shipping_config);

    $arr = array();

    if (is_array($shipping_config)) {

        foreach ($shipping_config as $key => $value) {

            foreach ($value as $k => $v) {

                $arr['configure'][$value['name']] = $value['value'];
            }
        }
    }
    return $arr;
}

/**
 * 相同商品退换货单 by leah
 * @param type $ret_id
 * @param type $order_sn
 */
function return_order_info_byId($order_id, $refound = true) {


    if (!$refound) {
        //获得唯一一个订单下 申请了全部退换货的退换货订单
        $sql = " SELECT count(*) FROM " . $GLOBALS['ecs']->table('order_return') . " WHERE order_id=" . $order_id . " AND refound_status = 0";
        $res = $GLOBALS['db']->getOne($sql);
    } else {

        $sql = " SELECT * FROM " . $GLOBALS['ecs']->table('order_return') . " WHERE order_id=" . $order_id;
        $res = $GLOBALS['db']->getAll($sql);
    }



    return $res;
}

/**
 * 退货单信息
 * by  leah
 */
function return_order_info($ret_id, $order_sn = '') {

    $ret_id = intval($ret_id);
    if ($ret_id > 0) {
        $sql = 'SELECT og.extension_code FROM ' . $GLOBALS['ecs']->table('order_return') . ' o'
                . ' LEFT JOIN ' . $GLOBALS['ecs']->table('order_goods') . ' og ON og.rec_id = o.rec_id'
                . " WHERE o.ret_id = '$ret_id'";

        $res = $GLOBALS['db']->getRow($sql);
        $field = '';
        $where = '';
        if ($res['extension_code'] == 'package_buy') {
            $field = 'g.activity_thumb as goods_thumb , g.act_name as goods_name,';
            $where = " LEFT JOIN " . $GLOBALS['ecs']->table('goods_activity') . " as g ON g.act_id=r.goods_id ";
        } else {
            $field = 'g.goods_thumb , g.goods_name ,g.shop_price ,';
            $where = " LEFT JOIN " . $GLOBALS['ecs']->table('goods') . " as g ON g.goods_id=r.goods_id ";
        }

        $sql = "SELECT r.* ," . $field . "  o.order_sn ,o.add_time ,  d.delivery_sn , d.update_time , d.how_oos ,d.shipping_fee, d.insure_fee ," .
                " rg.return_number " .
                "  FROM" . $GLOBALS['ecs']->table('order_return') .
                " as r LEFT JOIN  " . $GLOBALS['ecs']->table('goods_attr') . " as ga ON r.goods_id = ga.goods_id " .
                $where .
                " LEFT JOIN " . $GLOBALS['ecs']->table('return_goods') . " as rg ON r.rec_id=rg.rec_id " .
                " LEFT JOIN " . $GLOBALS['ecs']->table('order_info') . " as o ON o.order_id = r.order_id" .
                " LEFT JOIN " . $GLOBALS['ecs']->table('delivery_order') . " as d ON d.order_id = o.order_id " .
                " WHERE r.ret_id = '$ret_id'";
    } else {
        $sql = "SELECT *  FROM " . $GLOBALS['ecs']->table('order_return') .
                " WHERE order_sn = '$order_sn'";
    }
    $lang_ff = L('ff');
    $lang_rf = L('rf');
    $order = $GLOBALS['db']->getRow($sql);
    if (isset($res['extension_code']) && $res['extension_code'] == 'package_buy') {
        $order['extension_code'] = 'package_buy';
    }
    if (empty($order)) {
        show_message('找不到退换单', '退换货列表', url('index'));
    }
    $order['apply_time'] = local_date($GLOBALS['_CFG']['time_format'], $order['apply_time']);
    $order['formated_update_time'] = local_date($GLOBALS['_CFG']['time_format'], $order['update_time']);
    $order['formated_add_time'] = local_date($GLOBALS['_CFG']['time_format'], $order['add_time']);
    $order['insure_yn'] = empty($order['insure_fee']) ? 0 : 1;
    $order['should_return1'] = $order['should_return'];
    $order['should_return'] = price_format($order['should_return'], false);
    $order['return_status1'] = $order['return_status'];
    $order['return_status'] = $lang_rf[$order['return_status']];
    $order['refound_status1'] = $order['refound_status'];
    $order['shop_price'] = price_format($order['shop_price'], false);
    $order['refound_status'] = $lang_ff[$order['refound_status']];
    /* 对发货号处理 */
    if (!empty($order['out_invoice_no'])) {
        $shipping_code = $GLOBALS['db']->GetOne("SELECT shipping_code FROM " . $GLOBALS['ecs']->table('shipping') . " WHERE shipping_id = '$order[out_shipping_name]'");
        $plugin = ADDONS_PATH . 'shipping/' . $shipping_code . '.php';
        if (file_exists($plugin)) {
            include_once($plugin);
            $shipping = new $shipping_code;
            $order['out_invoice_no_btn'] = $shipping->query($order['out_invoice_no']);
        }
    }
    if (!empty($order['back_invoice_no'])) {
        $shipping_code = $GLOBALS['db']->GetOne("SELECT shipping_code FROM " . $GLOBALS['ecs']->table('shipping') . " WHERE shipping_id = '$order[back_shipping_name]'");
        $plugin = ADDONS_PATH . 'shipping/' . $shipping_code . '.php';
        if (file_exists($plugin)) {
            include_once($plugin);
            $shipping = new $shipping_code;
            $order['back_invoice_no_btn'] = $shipping->query($order['back_invoice_no']);
        }
    }
    $order['address_detail'] = get_consignee_info($order['order_id'], $order['address']);
    $order['address_detail'] = str_replace('[', '', str_replace(']', '', $order['address_detail']));
    $order['goods_thumb'] = get_image_path($order['goods_thumb']);
    $sql = "SELECT cause_name " .
            'FROM ' . $GLOBALS['ecs']->table('return_cause') . " WHERE cause_id=( SELECT parent_id FROM  " . $GLOBALS['ecs']->table('return_cause') . " WHERE cause_id = '" . $order['cause_id'] . "')";
    $parent = $GLOBALS['db']->getOne($sql);
    $sql = "SELECT c.cause_name " .
            'FROM ' . $GLOBALS['ecs']->table('return_cause') . " AS c " .
            "LEFT JOIN " . $GLOBALS['ecs']->table('return_cause') . " AS s ON s.parent_id=c.cause_id WHERE c.cause_id=" . $order['cause_id'];
    $child = $GLOBALS['db']->getOne($sql);
    if ($parent) {
        $order['return_cause'] = $parent . "-" . $child;
    } else {
        $order['return_cause'] = $child;
    }
    if ($order['back_shipping_name']) {

        $order['back_shipp_shipping'] = get_shipping_name($order['back_shipping_name']);
    }
    if ($order['out_shipping_name']) {

        $order['out_shipp_shipping'] = get_shipping_name($order['out_shipping_name']);
    }
    //下单，商品单价
    $goods_price = $GLOBALS['db']->getOne("SELECT goods_price FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '$order[order_id]' AND goods_id = '$order[goods_id]'");
    $order['goods_price'] = price_format($goods_price, false);
    // 取得退换货商品客户上传图片凭证
    $sql = "select img_file from " . $GLOBALS['ecs']->table('return_images') . " where user_id = '" . $order['user_id'] . "' and rec_id = '" . $order['rec_id'] . "' order by id desc";
    if ($getImage = $GLOBALS['db']->getCol($sql)) {
        foreach ($getImage as $key => $val) {
            $getImage[$key] = get_image_path($val);
        }
    }
    $order['img_list'] = $getImage;

    $order['img_count'] = count($order['img_list']);

    return $order;
}

/**
 * 获得快递名称 by leah
 * @param type $shipping_id
 * @return type
 */
function get_shipping_name($shipping_id) {

    $sql = "SELECT shipping_name FROM " . $GLOBALS['ecs']->table('shipping') . " WHERE shipping_id =" . $shipping_id;

    $shipping_name = $GLOBALS['db']->getOne($sql);

    return $shipping_name;
}

/**
 * 获得退换货商品
 * by  Leah
 */
function get_return_goods($ret_id) {

    $ret_id = intval($ret_id);
    $sql = "SELECT rg.* FROM " . $GLOBALS['ecs']->table('return_goods') .
            " as rg  LEFT JOIN " . $GLOBALS['ecs']->table('order_return') . "as r ON rg.rec_id = r.rec_id " .
            " WHERE r.ret_id = " . $ret_id;

    $res = $GLOBALS['db']->query($sql);
    foreach ($res as $row) {

        $row['refound'] = price_format($row['refound'], false);

        $goods_list[] = $row;
    }
    return $goods_list;
}

/**
 * 取的退换货表单里的商品
 * by Leah
 * @param type $rec_id
 * @return type
 */
function get_return_order_goods($rec_id) {

    $sql = "select * FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE rec_id =" . $rec_id;
    $goods_list = $GLOBALS['db']->getAll($sql);

    return $goods_list;
}

/**
 * 取的订单上商品中的某一商品
 * by　Leah
 * @param type $rec_id
 */
function get_return_order_goods1($rec_id) {

    $sql = "select * FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE rec_id =" . $rec_id;
    $goods_list = $GLOBALS['db']->getRow($sql);

    return $goods_list;
}

/**
 * 计算退款金额
 * by Leah
 * @param type $order_id
 * @param type $rec_id
 * @param type $num
 * @return type
 */
function get_return_refound($order_id, $rec_id, $num) {


    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE rec_id = " . $rec_id;
    $res = $GLOBALS['db']->getRow($sql);

    $refound = ($num * $res['goods_price']);

    //判断是否存在红包和积分

    return $refound;
}

/**
 * 取得用户退换货商品
 * by  leah
 */
function return_order($order_id = 0) {
    if (!empty($order_id) && !is_int($order_id)) {
        die(json_encode(array('error' => 1, 'content' => '订单号不存在')));
    }

    $where = '';
    if ($order_id > 0) {
        $where = ' AND o.order_id = ' . $order_id;
    }

    $goodsSql = "(SELECT CONCAT(goods_thumb, ',', goods_name) FROM " . $GLOBALS['ecs']->table('goods') . ' WHERE goods_id = o.goods_id)';
    $actSql = "(SELECT CONCAT(activity_thumb, ',', act_name)  FROM " . $GLOBALS['ecs']->table('goods_activity') . ' WHERE act_id = o.goods_id) ';
    $sql = "SELECT o.ret_id , o.rec_id, o.goods_id , o.order_sn, o.order_id, o.apply_time , o.should_return, o.return_status , o.refound_status, o.return_type, o.return_sn, rg.return_number, IF(og.extension_code = 'package_buy', " . $actSql . ", " . $goodsSql . ") AS goods_info " .
            " FROM " . $GLOBALS['ecs']->table('order_return') . " AS o" .
            " LEFT JOIN " . $GLOBALS['ecs']->table('order_goods') . " AS og ON og.rec_id = o.rec_id" .
            " LEFT JOIN " . $GLOBALS['ecs']->table('return_goods') . " AS rg ON rg.ret_id = o.ret_id" .
            " WHERE o.user_id = '" . $_SESSION['user_id'] . "' " . $where . " order by o.ret_id DESC";
    $res = $GLOBALS['db']->query($sql);

    foreach ($res as $row) {
        //获取商品图片  名称
        $goodsInfo = explode(',', $row['goods_info']);
        $row['goods_thumb'] = $goodsInfo[0];
        $row['goods_name'] = $goodsInfo[1];

        $row['apply_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['apply_time']);
        $row['should_return'] = price_format($row['should_return'], false);
        if ($row['return_status'] == 0 && $row['refound_status'] == 0) {
            //  提交退换货后的状态 由用户寄回
            @$row['order_status'] .= "<span>" . L('user_return') . "</span>";
            $row['refound_cancel_url'] = url('user/refound/cancel', array('ret_id' => $row['ret_id'], 'u' => 2));
        } elseif ($row['return_status'] == 1) {
            //退换商品收到
            @$row['order_status'] .= "<span>" . L('get_goods') . "</span>";
        } elseif ($row['return_status'] == 2) {
            //换货商品寄出 （分单）
            @$row['order_status'] .= "<span>" . L('send_alone') . "</span>";
        } elseif ($row['return_status'] == 3) {
            //换货商品寄出
            @$row['order_status'] .= "<span>" . L('send') . "</span>";
        } elseif ($row['return_status'] == 4) {
            //完成
            @$row['order_status'] .= "<span>" . L('complete') . "</span>";
        } else {
            //其他
        }

        //维修-退款-换货状态
        $lang_ff = L('ff');
        if ($row['return_type'] == 0) {
            if ($row['return_status'] == 4) {
                $row['reimburse_status'] = $lang_ff[FF_MAINTENANCE];
            } else {
                $row['reimburse_status'] = $lang_ff[FF_NOMAINTENANCE];
            }
        } else if ($row['return_type'] == 1) {
            if ($row['refound_status'] == 1) {
                $row['reimburse_status'] = $lang_ff[FF_REFOUND];
            } else {
                $row['reimburse_status'] = $lang_ff[NOFF_REFOUND];
            }
        } else if ($row['return_type'] == 2) {
            if ($row['return_status'] == 4) {
                $row['reimburse_status'] = $lang_ff[FF_EXCHANGE];
            } else {
                $row['reimburse_status'] = $lang_ff[NOFF_REFOUND];
            }
        }
        //商品信息
        $row['goods_thumb'] = get_image_path($row['goods_thumb']);
        $row['goods_url'] = url('goods/index/index', array('id' => $row['goods_id'], 'u' => 2));
        $row['refound_detail_url'] = url('user/refound/detail', array('ret_id' => $row['ret_id'], 'u' => 2));

        $goods_list[] = $row;
    }

    return $goods_list;
}

/**
 * by leah
 * 获得退换货操作log
 * @param type $ret_id
 */
function get_return_action($ret_id) {

    $act_list = array();
    $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('return_action') . " WHERE ret_id = '" . $ret_id . "'  ORDER BY log_time DESC,ret_id DESC";

    $res = $GLOBALS['db']->query($sql);
    $lang_rf = L('rf');
    $lang_ff = L('ff');
    foreach ($res as $row) {
        $row['return_status'] = $lang_rf[$row['return_status']];
        $row['refound_status'] = $lang_ff[$row['refound_status']];
        $row['action_time'] = local_date($GLOBALS['_CFG']['time_format'], $row['log_time']);

        $act_list[] = $row;
    }
    return $act_list;
}

/**
 *  获取订单里某个商品 信息 BY  Leah
 * @param type $rec_id
 * @return type
 */
function rec_goods($rec_id) {

    $sql = "SELECT rec_id, goods_id, goods_name, goods_sn, market_price, goods_number, " .
            "goods_price, goods_attr, is_real, parent_id, is_gift, " .
            "goods_price * goods_number AS subtotal, extension_code " .
            "FROM " . $GLOBALS['ecs']->table('order_goods') .
            " WHERE rec_id = '$rec_id'";
    $res = $GLOBALS['db']->getRow($sql);
    if ($res['extension_code'] == 'package_buy') {
        $res['package_goods_list'] = get_package_goods($res['goods_id']);
    }
    $res['market_price'] = price_format($res['market_price'], false);
    $res['goods_price1'] = $res['goods_price'];
    $res['goods_price'] = price_format($res['goods_price'], false);
    $res['subtotal'] = price_format($res['subtotal'], false);

    $sql = "select goods_img, goods_thumb, user_id from " . $GLOBALS['ecs']->table('goods') . " where goods_id = '" . $res['goods_id'] . "'";
    $goods = $GLOBALS['db']->getRow($sql);

    $data = array('shoprz_brandName', 'shop_class_keyWords', 'shopNameSuffix');
    $shop_info = get_table_date('merchants_shop_information', "user_id = '" . $goods['user_id'] . "'", $data);
    $res['user_name'] = $shop_info['shoprz_brandName'] . $shop_info['shopNameSuffix'];

    $sql = "select * from " . $GLOBALS['ecs']->table('seller_shopinfo') . " where ru_id='" . $goods['user_id'] . "'";
    $basic_info = $GLOBALS['db']->getRow($sql);

    $res['kf_type'] = $basic_info['kf_type'];
    $res['kf_ww'] = $basic_info['kf_ww'];
    $res['kf_qq'] = $basic_info['kf_qq'];

    /* 修正商品图片 */
    $res['goods_img'] = get_image_path($goods['goods_img']);
    $res['goods_thumb'] = get_image_path($goods['goods_thumb']);

    return $res;
}

/**
 * by Leah
 * @param type $rec_id
 * @return intb
 */
function get_is_refound($rec_id) {
    $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_return') . " WHERE rec_id=" . $rec_id;
    $is_refound = 0;
    $count = $GLOBALS['db']->getRow($sql);
    $count = (int) $count['COUNT(*)'];
    if ($count > 0) {
        $is_refound = 1;
    }

    return $is_refound;
}

/**
 * 获取退换货信息
 * @param type $rec_id
 * @return intb
 */
function get_refound($rec_id) {
    $sql = "SELECT ret_id FROM " . $GLOBALS['ecs']->table('order_return') . " WHERE rec_id=" . $rec_id;
    $ret_id = $GLOBALS['db']->getOne($sql);

    return $ret_id;
}

/**
 * 处理退换货
 * by  Leah
 */
function order_refund1($order, $refund_type, $refound_amount, $refund_note, $refund_amount = 0) {
    /* 检查参数 */
    $user_id = $order['user_id'];
    if ($user_id == 0 && $refund_type == 1) {
        die('anonymous, cannot return to account balance');
    }
    $amount = $refound_amount > 0 ? $refound_amount : $order['money_paid'];

    if ($amount <= 0) {
        return true;
    }

    if (!in_array($refund_type, array(1, 2, 3))) {
        die('invalid params');
    }

    /* 备注信息 */
    if ($refund_note) {
        $change_desc = $refund_note;
    } else {
        include_once(LANG_PATH . C('shop.lang') . '/admin/order.php');
        $change_desc = sprintf(L('order_refund'), $order['order_sn']);
    }

    /* 处理退款 */
    if (1 == $refund_type) {

        log_account_change($user_id, $amount, 0, 0, 0, $change_desc);

        return true;
    } elseif (2 == $refund_type) {


        return true;
    } elseif (22222 == $refund_type) {
        /* 如果非匿名，退回余额 */
        if ($user_id > 0) {
            log_account_change($user_id, $amount, 0, 0, 0, $change_desc);
        }

        /* user_account 表增加提款申请记录 */
        $account = array(
            'user_id' => $user_id,
            'amount' => (-1) * $amount,
            'add_time' => gmtime(),
            'user_note' => $refund_note,
            'process_type' => SURPLUS_RETURN,
            'admin_user' => $_SESSION['admin_name'],
            'admin_note' => sprintf(L('order_refund'), $order['order_sn']),
            'is_paid' => 0
        );
//        print_r( $account );
        $GLOBALS['db']->autoExecute($GLOBALS['ecs']->table('user_account'), $account, 'INSERT');

        return true;
    } else {
        return true;
    }
}

/**
 * 退换货 用户积分退还
 * by Leah
 */
function return_surplus_integral_bonus($user_id, $goods_price, $return_goods_price) {

    $sql = " SELECT pay_points  FROM " . $GLOBALS['ecs']->table('users') . " WHERE user_id=" . $user_id;
    $pay = $GLOBALS['db']->getOne($sql);

    $pay = $pay - $goods_price + $return_goods_price;

    if ($pay > 0) {
        $sql = "UPDATE " . $GLOBALS['ecs']->table('users') . " SET pay_points =" . $pay;

        $GLOBALS['db']->query($sql);
    }
}

/**
 * 付款更新众筹信息
 */
function update_zc_project($order_id = 0) {

    //取得订单信息
    $sql = " SELECT user_id, is_zc_order, zc_goods_id FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$order_id' ";
    $order_info = $GLOBALS['db']->getRow($sql);
    $user_id = $order_info['user_id'];
    $is_zc_order = $order_info['is_zc_order'];
    $zc_goods_id = $order_info['zc_goods_id'];

    if ($is_zc_order == 1 && $zc_goods_id > 0) {
        //获取众筹商品信息
        $sql = " select * from " . $GLOBALS['ecs']->table('zc_goods') . " where id = '$zc_goods_id' ";
        $zc_goods_info = $GLOBALS['db']->getRow($sql);
        $pid = $zc_goods_info['pid'];
        $goods_price = $zc_goods_info['price'];

        //增加众筹商品支持的用户数量
        $sql = " UPDATE " . $GLOBALS['ecs']->table('zc_goods') . " SET backer_num = backer_num+1 WHERE id = '$zc_goods_id' ";
        $GLOBALS['db']->query($sql);

        //增加众筹商品支持的用户id
        $sql = "SELECT backer_list FROM " . $GLOBALS['ecs']->table('zc_goods') . " WHERE id = '$zc_goods_id'";
        $backer_list = $GLOBALS['db']->getOne($sql);
        if (empty($backer_list)) {
            $backer_list = $user_id;
        } else {
            $backer_list = $backer_list . ',' . $user_id;
        }
        $sql = "UPDATE " . $GLOBALS['ecs']->table('zc_goods') . " SET backer_list='$backer_list' WHERE id = '$zc_goods_id'";
        $GLOBALS['db']->query($sql);

        //增加众筹项目的支持用户总数量、增加众筹项目总金额
        $sql = "UPDATE " . $GLOBALS['ecs']->table('zc_project') . " SET join_num=join_num+1, join_money=join_money+$goods_price WHERE id = '$pid'";
        $GLOBALS['db']->query($sql);
    }
}

/**
 * 根据门店ID获取门店
 */
function getStoresName($id) {
    $sql = "SELECT stores_name FROM " . $GLOBALS['ecs']->table('offline_store') . " WHERE id = " . $id;
    $res = $GLOBALS['db']->getRow($sql);
    return $res['stores_name'];
}

/**
 * 获得虚拟商品的卡号密码 by wu
 */
function get_virtual_goods_info($rec_id = 0) {
    include_once(ROOT_PATH . 'includes/lib_code.php');
    $sql = " SELECT vc.* FROM " . $GLOBALS['ecs']->table('order_goods') . " AS og " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('order_info') . " AS oi ON oi.order_id = og.order_id " .
            " LEFT JOIN " . $GLOBALS['ecs']->table('virtual_card') . " AS vc ON vc.order_sn = oi.order_sn " .
            " WHERE og.goods_id = vc.goods_id AND vc.is_saled = 1  AND og.rec_id = '$rec_id' ";
    $virtual_info = $GLOBALS['db']->getRow($sql);
    if ($virtual_info) {
        $virtual_info['card_sn'] = decrypt($virtual_info['card_sn']);
        $virtual_info['card_password'] = decrypt($virtual_info['card_password']);
        $virtual_info['end_date'] = local_date($GLOBALS['_CFG']['date_format'], $virtual_info['end_date']);
    }
    return $virtual_info;
}

?>

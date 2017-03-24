<?php
defined('IN_ECTOUCH') or die('Deny Access');

/**
 * 取得返回信息地址
 * @param   string $code 支付方式代码
 */
function return_url($code)
{
    return __URL__ . '/respond.php?code=' . $code;
}

/**
 * 取得返回信息地址
 * @param   string $code 支付方式代码
 */
function notify_url($code)
{
    return __URL__ . '/app/notify/' . $code . '.php';
}

/**
 *  取得某支付方式信息
 * @param  string $code 支付方式代码
 */
function get_payment($code)
{
    $sql = 'SELECT * FROM ' . $GLOBALS['ecs']->table('payment') .
        " WHERE pay_code = '$code' AND enabled = '1'";
    $payment = $GLOBALS['db']->getRow($sql);

    if ($payment) {
        $config_list = unserialize($payment['pay_config']);

        foreach ($config_list AS $config) {
            $payment[$config['name']] = $config['value'];
        }
    }

    return $payment;
}


/**
 *  通过订单sn取得订单ID
 * @param  string $order_sn 订单sn
 * @param  blob $voucher 是否为会员充值
 */
function get_order_id_by_sn($order_sn, $voucher = 'false')
{
    if ($voucher == 'true') {
        if (is_numeric($order_sn)) {
            return $GLOBALS['db']->getOne("SELECT log_id FROM " . $GLOBALS['ecs']->table('pay_log') . " WHERE order_id=" . $order_sn . ' AND order_type=1');
        } else {
            return "";
        }
    } else {
        if (is_numeric($order_sn)) {
            $sql = 'SELECT order_id FROM ' . $GLOBALS['ecs']->table('order_info') . " WHERE order_sn = '$order_sn'";
            $order_id = $GLOBALS['db']->getOne($sql);
        }
        if (!empty($order_id)) {
            $pay_log_id = $GLOBALS['db']->getOne("SELECT log_id FROM " . $GLOBALS['ecs']->table('pay_log') . " WHERE order_id='" . $order_id . "'");
            return $pay_log_id;
        } else {
            return "";
        }
    }
}

/**
 *  通过订单ID取得订单商品名称
 * @param  string $order_id 订单ID
 */
function get_goods_name_by_id($order_id)
{
    $sql = 'SELECT goods_name FROM ' . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '$order_id'";
    $goods_name = $GLOBALS['db']->getCol($sql);
    return implode(',', $goods_name);
}

/**
 * 检查支付的金额是否与订单相符
 *
 * @access  public
 * @param   string $log_id 支付编号
 * @param   float $money 支付接口返回的金额
 * @return  true
 */
function check_money($log_id, $money)
{
    if (is_numeric($log_id)) {
        $sql = 'SELECT order_amount FROM ' . $GLOBALS['ecs']->table('pay_log') .
            " WHERE log_id = '$log_id'";
        $amount = $GLOBALS['db']->getOne($sql);
    } else {
        return false;
    }
    if ($money == $amount) {
        return true;
    } else {
        return false;
    }
}

/**
 * 修改订单的支付状态
 *
 * @access  public
 * @param   string $log_id 支付编号
 * @param   integer $pay_status 状态
 * @param   string $note 备注
 * @return  void
 */
function order_paid($log_id, $pay_status = PS_PAYED, $note = '', $module_name = '')
{
    // 模块订单处理接口
    $module_path = BASE_PATH . 'http/' . $module_name;
    if (!empty($module_name) && is_dir($module_path)) {
        $handler = 'order_paid_' . $module_name;
        if(require_cache($module_path . '/helpers/' . $handler . '.php')){
            return $handler($log_id, $pay_status = PS_PAYED, $note = '');
        }
    }
    /* 取得支付编号 */
    $log_id = intval($log_id);
    if ($log_id > 0) {
        /* 取得要修改的支付记录信息 */
        $sql = "SELECT * FROM " . $GLOBALS['ecs']->table('pay_log') .
            " WHERE log_id = '$log_id'";
        $pay_log = $GLOBALS['db']->getRow($sql);
        if ($pay_log && $pay_log['is_paid'] == 0) {
            /* 修改此次支付操作的状态为已付款 */
            $sql = 'UPDATE ' . $GLOBALS['ecs']->table('pay_log') .
                " SET is_paid = '1' WHERE log_id = '$log_id'";
            $GLOBALS['db']->query($sql);

            /* 根据记录类型做相应处理 */
            if ($pay_log['order_type'] == PAY_ORDER) {
                /* 取得订单信息 */
                $sql = 'SELECT main_order_id, order_id, user_id, order_sn, consignee, address, tel, mobile, shipping_id, pay_status, extension_code, extension_id, goods_amount, is_zc_order, zc_goods_id, ' .
                        'shipping_fee, insure_fee, pay_fee, tax, pack_fee, card_fee, surplus, money_paid, integral_money, bonus, order_amount, discount '.
                        'FROM ' . $GLOBALS['ecs']->table('order_info') .
                       " WHERE order_id = '$pay_log[order_id]'";
                $order = $GLOBALS['db']->getRow($sql);
                $main_order_id = $order['main_order_id'];
                $order_id = $order['order_id'];
                $order_sn = $order['order_sn'];

				/* 众筹状态的更改 */
				$is_zc_order = $order['is_zc_order'];
				$zc_goods_id = $order['zc_goods_id'];
				if($is_zc_order==1 && $zc_goods_id>0)
				{
					//获取众筹商品信息
					$sql = " select * from ".$GLOBALS['ecs']->table('zc_goods')." where id = '$zc_goods_id' ";
					$zc_goods_info = $GLOBALS['db']->getRow($sql);
					$pid = $zc_goods_info['pid'];
					$goods_price = $zc_goods_info['price'];

					//增加众筹商品支持的用户数量
					$sql = " UPDATE ".$GLOBALS['ecs']->table('zc_goods')." SET backer_num = backer_num+1 WHERE id = '$zc_goods_id' ";
					$GLOBALS['db']->query($sql);

					//增加众筹商品支持的用户id
					$sql = "SELECT backer_list FROM ".$GLOBALS['ecs']->table('zc_goods')." WHERE id = '$zc_goods_id'";
					$backer_list = $GLOBALS['db']->getOne($sql);
					if(empty($backer_list))
					{
						$backer_list = $user_id;
					}
					else
					{
						$backer_list = $backer_list.','.$user_id;
					}
					$sql = "UPDATE ".$GLOBALS['ecs']->table('zc_goods')." SET backer_list='$backer_list' WHERE id = '$zc_goods_id'";
					$GLOBALS['db']->query($sql);

					//增加众筹项目的支持用户总数量、增加众筹项目总金额
					$sql = "UPDATE ".$GLOBALS['ecs']->table('zc_project')." SET join_num=join_num+1, join_money=join_money+$goods_price WHERE id = '$pid'";
					$GLOBALS['db']->query($sql);
				}
				/* 众筹状态的更改 */


                //预售首先支付定金--无需分单
                if($order['extension_code'] == 'presale'){
                    $money_paid = $order['money_paid'] + $order['order_amount'];

                    if($order['pay_status'] == 0){
                        /* 修改订单状态为已部分付款 */
                        $order_amount= $order['goods_amount']
                            + $order['shipping_fee']
                            + $order['insure_fee']
                            + $order['pay_fee']
                            + $order['tax']
                            - $order['money_paid']
                            - $order['order_amount'];
                        $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                            " SET order_status = '" . OS_CONFIRMED . "', " .
                            " confirm_time = '" . gmtime() . "', " .
                            " pay_status = '". PS_PAYED_PART ."', " .
                            " pay_time = '".gmtime()."', " .
                            " money_paid = '$money_paid'," .
                            " order_amount = '$order_amount' ".
                            "WHERE order_id = '$order_id'";
                        $GLOBALS['db']->query($sql);

                        /* 记录订单操作记录 */
                        order_action($order_sn, OS_CONFIRMED, SS_UNSHIPPED, PS_PAYED_PART, $note, L('buyer'));
                        //更新pay_log
                        update_pay_log($order_id);
                    }else{
                        $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                            " SET pay_status = '". PS_PAYED ."', " .
                            " pay_time = '".gmtime()."', " .
                            " money_paid = '$money_paid'," .
                            " order_amount = 0 ".
                            "WHERE order_id = '$order_id'";
                        $GLOBALS['db']->query($sql);

                        /* 记录订单操作记录 */
                        order_action($order_sn, OS_CONFIRMED, SS_UNSHIPPED, PS_PAYED, $note, L('buyer'));
                    }
                }else{
                    //
                    /* 修改订单状态为已付款 */
                    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                        " SET order_status = '" . OS_CONFIRMED . "', " .
                        " confirm_time = '" . gmtime() . "', " .
                        " pay_status = '$pay_status', " .
                        " pay_time = '" . gmtime() . "', " .
                        " money_paid = order_amount," .
                        " order_amount = 0 " .
                        "WHERE order_id = '$order_id'";
                    $GLOBALS['db']->query($sql);

                    /* 记录订单操作记录 */
                    order_action($order_sn, OS_CONFIRMED, SS_UNSHIPPED, $pay_status, $note, $GLOBALS['_LANG']['buyer']);
                }
                /* 修改子订单状态为已付款 by wanganlin */
                $sql = "SELECT order_id FROM ". $GLOBALS['ecs']->table('order_info') ." WHERE main_order_id = '$order_id'";
                $child_order_id_arr = $GLOBALS['db']->getAll($sql);
                if($main_order_id == 0 && count($child_order_id_arr) > 0){
                    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                    " SET order_status = '" . OS_CONFIRMED . "', " .
                    " confirm_time = '" . gmtime() . "', " .
                    " pay_status = '$pay_status', " .
                    " pay_time = '".gmtime()."', " .
                    " money_paid = order_amount," .
                    " order_amount = 0 ".
                    "WHERE main_order_id = '$order_id'";
                    $GLOBALS['db']->query($sql);

                    $sql = 'SELECT order_sn ' . 'FROM ' . $GLOBALS['ecs']->table('order_info') .
                        " WHERE main_order_id = '$order_id'";
                    $order_res = $GLOBALS['db']->getAll($sql);
                    foreach ($order_res AS $row)
                    {
                        /* 记录订单操作记录 */
                        order_action($row['order_sn'], OS_CONFIRMED, SS_UNSHIPPED, $pay_status, $note, L('buyer'));
                    }
                }

                /* 如果需要，发短信 */
                $sql = "SELECT ru_id FROM " . $GLOBALS['ecs']->table('order_goods') . " WHERE order_id = '$order_id' LIMIT 1";
                $ru_id = $GLOBALS['db']->getOne($sql);
                if ($ru_id == 0) {
                    $sms_shop_mobile = $GLOBALS['_CFG']['sms_shop_mobile'];
                } else {
                    $sql = "SELECT mobile FROM " . $GLOBALS['ecs']->table('seller_shopinfo') . " WHERE ru_id = '$ru_id'";
                    $sms_shop_mobile = $GLOBALS['db']->getOne($sql);
                }

                if ($GLOBALS['_CFG']['sms_order_payed'] == '1' && $sms_shop_mobile != '') {
                    // 组装数据
                    $message = array(
                        'consignee' => $order['consignee'],
                        'order_mobile' => $order['mobile']
                    );
                    send_sms($sms_shop_mobile, 'sms_order_payed', $message);
                }

                /* 如果安装微信通,订单支付成功消息提醒 限微信支付 */
                if(is_dir(APP_WECHAT_PATH) && !empty($pay_log['openid'])) {
                    $pushData = array(
                        'keyword1' => array('value' => $order_sn),
                        'keyword2' => array('value' => '已付款'),
                        'keyword3' => array('value' => date('Y-m-d H:i:s', gmtime())),
                        'keyword4' => array('value' => $GLOBALS['_CFG']['shop_name']),
                        'keyword5' => array('value' => $pay_log['order_amount'])
                    );
                    $order_url = __HOST__ . url('user/order/detail',array('order_id'=> $order_id));
                    $url = str_replace('app/notify/wxpay.php', '', $order_url);

                    push_template('OPENTM204987032', $pushData, $url, $order['user_id']);
                }

                /* 对虚拟商品的支持 */
                $virtual_goods = get_virtual_goods($order_id);
                if (!empty($virtual_goods)) {
                    $msg = '';
                    if (!virtual_goods_ship($virtual_goods, $msg, $order_sn, true)) {
                        $GLOBALS['_LANG']['pay_success'] .= '<div style="color:red;">' . $msg . '</div>' . $GLOBALS['_LANG']['virtual_goods_ship_fail'];
                    }

                    /* 如果订单没有配送方式，自动完成发货操作 */
                    if ($order['shipping_id'] == -1) {
                        /* 将订单标识为已发货状态，并记录发货记录 */
                        $sql = 'UPDATE ' . $GLOBALS['ecs']->table('order_info') .
                            " SET shipping_status = '" . SS_SHIPPED . "', shipping_time = '" . gmtime() . "'" .
                            " WHERE order_id = '$order_id'";
                        $GLOBALS['db']->query($sql);

                        /* 记录订单操作记录 */
                        order_action($order_sn, OS_CONFIRMED, SS_SHIPPED, $pay_status, $note, $GLOBALS['_LANG']['buyer']);
                        $integral = integral_to_give($order);
                        log_account_change($order['user_id'], 0, 0, intval($integral['rank_points']), intval($integral['custom_points']), sprintf($GLOBALS['_LANG']['order_gift_integral'], $order['order_sn']));
                    }
                }

            } elseif ($pay_log['order_type'] == PAY_SURPLUS) {
                $sql = 'SELECT `id` FROM ' . $GLOBALS['ecs']->table('user_account') . " WHERE `id` = '$pay_log[order_id]' AND `is_paid` = 1  LIMIT 1";
                $res_id = $GLOBALS['db']->getOne($sql);
                if (empty($res_id)) {
                    /* 更新会员预付款的到款状态 */
                    $sql = 'UPDATE ' . $GLOBALS['ecs']->table('user_account') .
                        " SET paid_time = '" . gmtime() . "', is_paid = 1" .
                        " WHERE id = '$pay_log[order_id]' LIMIT 1";
                    $GLOBALS['db']->query($sql);

                    /* 取得添加预付款的用户以及金额 */
                    $sql = "SELECT user_id, amount FROM " . $GLOBALS['ecs']->table('user_account') .
                        " WHERE id = '$pay_log[order_id]'";
                    $arr = $GLOBALS['db']->getRow($sql);

                    /* 修改会员帐户金额 */
                    $_LANG = array();
                    include_once(LANG_PATH  . $GLOBALS['_CFG']['lang'] . '/user.php');
                    log_account_change($arr['user_id'], $arr['amount'], 0, 0, 0, $_LANG['surplus_type_0'], ACT_SAVING);
                }
            }
            //支付类型为购买成为分销商start
            elseif($pay_log['order_type'] == PAY_REGISTERED){
                $sql = "SELECT id FROM ".$GLOBALS['ecs']->table('drp_shop')." WHERE user_id='$pay_log[order_id]' AND `isbuy` = 1  LIMIT 1";
                $res_id=$GLOBALS['db']->getOne($sql);
                if(empty($res_id)){
                    $time=gmtime();
                    $sql = "INSERT INTO ". $GLOBALS['ecs']->table('drp_shop') ."(user_id,create_time, isbuy) VALUES "."('".$pay_log[order_id]."','".$time."', '1')";
                    $GLOBALS['db']->query($sql);

                    /* 如果安装微信通,订单支付成功购买成为分销商消息提醒 限微信支付 */
                    if(is_dir(APP_WECHAT_PATH) && !empty($pay_log['openid'])) {
                        //模板消息  by wanglu
                        $shop = dao('drp_shop')->field('shop_name, mobile, create_time')->where($where)->find();
                        $pushData = array(
                            'keyword1'=> array('value'=>$shop['shop_name']),
                            'keyword2'=> array('value'=>$shop['mobile']),
                            'keyword3'=> array('value'=>date('Y-m-d', $shop['create_time']))
                        );
                        $drp_url = __HOST__ . url('drp/index/index');
                        $url = str_replace('app/notify/wxpay.php', '', $drp_url);
                        push_template('OPENTM207126233', $pushData, $url, $order['user_id']);
                    }
                }
            }
           //支付类型为购买成为分销商end
         } else {
            /* 取得已发货的虚拟商品信息 */
            $post_virtual_goods = get_virtual_goods($pay_log['order_id'], true);

            /* 有已发货的虚拟商品 */
            if (!empty($post_virtual_goods)) {
                $msg = '';
                /* 检查两次刷新时间有无超过12小时 */
                $sql = 'SELECT pay_time, order_sn FROM ' . $GLOBALS['ecs']->table('order_info') . " WHERE order_id = '$pay_log[order_id]'";
                $row = $GLOBALS['db']->getRow($sql);
                $intval_time = gmtime() - $row['pay_time'];
                if ($intval_time >= 0 && $intval_time < 3600 * 12) {
                    $virtual_card = array();
                    foreach ($post_virtual_goods as $code => $goods_list) {
                        /* 只处理虚拟卡 */
                        if ($code == 'virtual_card') {
                            foreach ($goods_list as $goods) {
                                if ($info = virtual_card_result($row['order_sn'], $goods)) {
                                    $virtual_card[] = array('goods_id' => $goods['goods_id'], 'goods_name' => $goods['goods_name'], 'info' => $info);
                                }
                            }

                            $GLOBALS['smarty']->assign('virtual_card', $virtual_card);
                        }
                    }
                } else {
                    $msg = '<div>' . $GLOBALS['_LANG']['please_view_order_detail'] . '</div>';
                }

                $GLOBALS['_LANG']['pay_success'] .= $msg;
            }

            /* 取得未发货虚拟商品 */
            $virtual_goods = get_virtual_goods($pay_log['order_id'], false);
            if (!empty($virtual_goods)) {
                $GLOBALS['_LANG']['pay_success'] .= '<br />' . $GLOBALS['_LANG']['virtual_goods_ship_fail'];
            }
        }
    }
}

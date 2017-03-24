<?php
namespace app\http\user\controllers;

use app\http\base\controllers\Frontend;

class Order extends Frontend
{

    public $user_id;
    // 用户id
    /**
     * 构造，加载文件语言包和helper文件
     */
    public function __construct()
    {
        parent::__construct();
        $this->user_id = $_SESSION['user_id'];
        $this->actionchecklogin();
        L(require (LANG_PATH  . C('shop.lang') . '/user.php'));
        L(require (LANG_PATH  . C('shop.lang') . '/flow.php'));

        $files = array(
            'order',
            'clips',
            'payment',
            'transaction'
        );
        $this->load_helper($files);
        //是否显示拼团信息
        $this->assign('team', is_dir(APP_TEAM_PATH) ? 1 : 0);
    }

    /**
     * 订单列表
     */
    public function actionIndex()
    {
        $size = 10;
        $page = I('page', 1, 'intval');
        $status = I('status', 0, 'intval');
        if(IS_POST){
            $order_list = get_user_orders($this->user_id, $size, $page, $status);

            exit(json_encode(array('order_list'=>$order_list['list'], 'totalPage'=>$order_list['totalpage'])));
        }

        $this->assign('status', $status);
//        $this->assign('page_title', '订单列表');
        $this->assign('page_title', L('order_list_lnk'));
        $this->display();
    }

    /**
     * 查看订单详情
     */
    public function actionDetail()
    {
        $order_id = I('order_id', 0, 'intval');
        $noTime = gmtime();
        $date = array(
            'order_status',
            'shipping_status',
            'pay_status',
            'shipping_time',
            'auto_delivery_time'
        );
        $orderInfo = get_table_date('order_info', "order_id = '$order_id' and user_id = '$this->user_id'", $date);

        if ($GLOBALS['_CFG']['open_delivery_time'] == 1) {
            if ($orderInfo['order_status'] == 5 && $orderInfo['shipping_status'] == 1 && $orderInfo['pay_status'] == 2) { // 发货状态
                $delivery_time = $orderInfo['shipping_time'] + 24 * 3600 * $orderInfo['auto_delivery_time'];
                if ($noTime > $delivery_time) { // 自动确认发货操作
                    $sql = "update {pre}order_info set order_status = 5, shipping_status = 2, pay_status = 2 where order_id = '$order_id'";
                    $this->db->query($sql);
                }
            }
        }
        /* 订单详情 */
        $order = get_order_detail($order_id, $this->user_id);
        if ($order === false) {
            $this->err->show(L('back_home_lnk'), './');
            exit();
        }
        /*获取订单门店信息  start*/
        $sql = "SELECT id, store_id,pick_code  FROM".$this->ecs->table("store_order")." WHERE order_id = '$order_id'";
        $stores = $this->db->getRow($sql);
        $order['store_id'] = $stores['store_id'];
        $order['pick_code'] = $stores['pick_code'];
        if($order['store_id'] > 0){
            $sql = "SELECT o.*,p.region_name as province,c.region_name as city,d.region_name as district FROM".$this->ecs->table('offline_store')." AS o "
                . "LEFT JOIN ".$this->ecs->table('region')." AS p ON p.region_id = o.province "
                . "LEFT JOIN ".$this->ecs->table('region')." AS c ON c.region_id = o.city "
                . "LEFT JOIN ".$this->ecs->table('region')." AS d ON d.region_id = o.district WHERE o.id = '".$order['store_id']."'";
            $offline_store = $this->db->getRow($sql);
            $this->assign('offline_store',$offline_store);
            $this->assign('store_id', $stores['id']);
        }
        //订单店铺
        $ru_id = $this->db->getRow("SELECT ru_id FROM ".$this->ecs->table('order_goods')." WHERE order_id = ".$order['order_id']);
        if($ru_id){
            $order['shop_name'] = get_shop_name($ru_id['ru_id'], 1); //店铺名称
            $order['shopUrl'] = url('store/index/index', array('id'=>$ru_id));
        }

        /* 是否显示添加到购物车 */
        if ($order['extension_code'] != 'group_buy' && $order['extension_code'] != 'exchange_goods') {
            $this->assign('allow_to_cart', 1);
        }

        /* 订单商品 */
        $goods_list = order_goods($order_id);
        $goods_count = 0;
        $package_goods_count = 0;
        $package_list_total = 0;
        foreach ($goods_list as $key => $value) {
            $goods_list[$key]['market_price'] = price_format($value['market_price'], false);
            $goods_list[$key]['goods_price'] = price_format($value['goods_price'], false);
            $goods_list[$key]['subtotal'] = price_format($value['subtotal'], false);
            	/* 虚拟商品卡密 by wu */
            if($value['is_real'] == 0)
            {
                $goods_list[$key]['virtual_info'] = get_virtual_goods_info($value['rec_id']);
            }
            //礼包统计
            if($value['extension_code'] == 'package_buy'){
                $package_goods_count++;
                foreach($value['package_goods_list'] as $package_goods_val){
                    $package_list_total  += $package_goods_val['rank_price'] * $package_goods_val['goods_number'];
                }
                $goods_list[$key]['package_list_total'] = $package_list_total;
                $goods_list[$key]['package_list_saving'] = $value['subtotal'] - $package_list_total;
                $goods_list[$key]['format_package_list_total'] = price_format($goods_list[$key]['package_list_total']);
                $goods_list[$key]['format_package_list_saving'] = price_format($goods_list[$key]['package_list_saving']);
            }else{
                $goods_count++;
            }
        }
        /* 设置能否修改使用余额数 */
        if ($order['order_amount'] > 0) {
            if ($order['order_status'] == OS_UNCONFIRMED || $order['order_status'] == OS_CONFIRMED)
            {
                $user = user_info($order['user_id']);
                if ($user['user_money'] + $user['credit_line'] > 0) {
                    $this->assign('allow_edit_surplus', 1);
                    $this->assign('max_surplus', sprintf(L('max_surplus'), $user['user_money']));
                }
            }
        }
        /* 未发货，未付款时允许更换支付方式 */
        if ($order['order_amount'] > 0 && ($order['pay_status'] == PS_UNPAYED || $order['pay_status'] == PS_PAYED_PART) && $order['shipping_status'] == SS_UNSHIPPED)
        {
            $payment_list = available_payment_list(false, 0, true);
            /* 过滤掉当前支付方式和余额支付方式 */
            if (is_array($payment_list)) {
                foreach ($payment_list as $key => $payment) {
                    // ecmoban模板堂 --will start
                    // pc端去除ecjia的支付方式
                    if (substr($payment['pay_code'], 0, 4) == 'pay_') {
                        unset($payment_list[$key]);
                        continue;
                    }
                    // 如果不是微信浏览器访问并且不是微信会员 则不显示微信支付
                    if ($payment ['pay_code'] == 'wxpay' && (!is_wechat_browser() || empty($_SESSION['openid']))) {
                        unset($payment_list [$key]);
                    }
                    // ecmoban模板堂 --will end
                    if ($payment['pay_id'] == $order['pay_id'] || $payment['pay_code'] == 'balance')
                    {
                        unset($payment_list[$key]);
                    }
                }
            }
            $this->assign('payment_list', $payment_list);
        }
        /* 订单 支付 配送 状态语言项 */
        $os = L('os');
        $ps = L('ps');
        $ss = L('ss');
        if ($order['order_status'] == OS_UNCONFIRMED) {
            $order['handler'] = "<span class=\"box-flex text-right\"></span><a class=\"btn-default box-flex\" type=\"button\" href=\"" . url('user/order/cancel', array('order_id' => $order['order_id'])) . "\" onclick=\"if (!confirm('" . L('confirm_cancel') . "')) return false;\">" . L('cancel') . "</a>";
        } else if ($order['order_status'] == OS_SPLITED) {
            /* 对配送状态的处理 */
            if ($order['shipping_status'] == SS_SHIPPED) {
                @$order['handler'] = "<span class=\"box-flex text-right\"></span><a class=\"btn-submit\" href=\"" . url('user/order/affirmreceived', array('order_id' => $order['order_id'])) . "\" onclick=\"if (!confirm('" . L('confirm_received') . "')) return false;\">" . L('received') . "</a>";
            } elseif ($order['shipping_status'] == SS_RECEIVED) {
                @$order['handler'] = '<span class="order-checkout-text box">' . L('ss_received') . '</span>';;
            } else {
                if ($order['pay_status'] == PS_UNPAYED) {
                    @$order['handler'] = "<span class=\"box-flex text-right\"></span><a class=\"btn-submit\" href=\"" . url('user/order/detail', array('order_id' => $order['order_id'])) . '" >' . L('pay_money') . '</a>';
                } else {
                    $order['handler'] = '<span class="order-checkout-text box">' . $ss[$order['shipping_status']] . '</span>';
                }
            }
        } else {
            if ($order['order_status'] == OS_CONFIRMED && $order['pay_status'] == PS_UNPAYED) {
                $order['handler'] = "<span class='box-flex'></span><a class='btn-default box-flex' type='button' >" . $ps[$order['pay_status']] . "</a>";
            } elseif ($order['pay_status'] == PS_PAYED_PART) {
                if($order['extension_code'] == 'presale' ){
                    $result = presale_settle_status($order['extension_id']);
                    if($result['settle_status'] == 1 ){
                        $order['msg'] ='尾款支付时间:';
                        $start_time=trim($result['start_time']);
                        $end_time=trim($result['end_time']);
                        @$order['handler'] = "<span class=box-flex text-right>" .$start_time.'至'.$end_time. '</span>';

                    }
                    if($result['settle_status'] == 0){
                        $order['msg'] ='尾款支付时间:';
                        $start_time=trim($result['start_time']);
                        $end_time=trim($result['end_time']);
                        $order['hidden_pay_button'] = 1;
                        @$order['handler'] = "<span class=box-flex text-right>".$start_time.'至'.$end_time.'</span>';

                    }
                    if($result['settle_status'] == -1){
                        $order['hidden_pay_button'] = 1;
                        $order['msg'] = "超出尾款支付时间";
                        $end_time=trim($result['end_time']);
                        @$order['handler'] = "<span class='box-flex text-right'>".$end_time ."</span>";
                    }
                }
            } else {
                $order['handler'] = $order['handler'] = '<span class="order-checkout-text box">' . $os[$order['order_status']] . '</span>';
            }
        }

        $order['order_status'] = $os[$order[order_status]];
        $order['pay_status'] = $ps[$order[pay_status]];
        $order['shipping_status'] = $ss[$order['shipping_status']];

        $order['c'] = get_region_name($order['country']);
        $order['detail_address'] .= $order['c']['region_name'];

        $order['p'] = get_region_name($order['province']);
        $order['detail_address'] .= $order['p']['region_name'];

        $order['cc'] = get_region_name($order['city']);
        $order['detail_address'] .= $order['cc']['region_name'];

        $order['dd'] = get_region_name($order['district']);
        $order['detail_address'] .= $order['dd']['region_name'];
        $order['detail_address'] .= $order['address'];

        /* 自提点信息 */
        $sql = "SELECT * FROM ".$this->ecs->table('shipping_point')." WHERE id IN (SELECT point_id FROM ".$this->ecs->table('order_info')." WHERE order_id='{$order_id}')";
        $order['point'] = $this->db->getRow($sql);
        if($order['point']){
            $order['point']['pickDate'] = $order['shipping_dateStr'];
        }
        //验证拼团订单是否失效
        if(is_dir(APP_TEAM_PATH)){
            $failure = 0;
            if($order['team_id'] > 0){
                //拼团支付成功订单数量
                $sql = "SELECT count(order_id) as num  FROM " . $GLOBALS['ecs']->table('order_info') . " WHERE team_id = '" . $order['team_id'] . "' AND extension_code = 'team_buy' and user_id != '".$_SESSION['user_id']."' and pay_status = '" . PS_PAYED . "' ";
                $res = $GLOBALS['db']->getRow($sql);
                //获取拼团商品几人团信息
                $sql ="select tg.team_num from " . $GLOBALS['ecs']->table('team_log') . " as tl LEFT JOIN " .$GLOBALS['ecs']->table('team_goods')." AS tg ON tl.goods_id = tg.goods_id  where tl.team_id = '".$order['team_id']."'";
                $nums = $GLOBALS['db']->getRow($sql);
                if($res['num'] == $nums['team_num'] ){
                    $failure = 1;
                }
                $order['failure'] =$failure ;
            }
        }
        //验证拼团订单是否失效 end
        $this->assign('order', $order);
        $this->assign('goods_list', $goods_list);
        $this->assign('goods_count', $goods_count);
        $this->assign('package_goods_count', $package_goods_count);
        $this->assign('page_title', L('order_detail'));
        $this->display();
    }

    /**
     * 退换货申请列表
     */
    public function actionApplyReturnList(){
      //var_dump($_REQUEST);
        /* 根据订单id或订单号查询订单信息 */
        if (isset($_REQUEST['rec_id']))
        {
            $recr_id = intval($_REQUEST['rec_id']);
        }
        else
        {
            /* 如果参数不存在，退出 */
            die('invalid parameter');
        }
        $_REQUEST['order_id'] = intval($_REQUEST['order_id']);
        /* 退货权限 */
        $sql = " SELECT order_id FROM ".$GLOBALS['ecs']->table('order_info')." WHERE order_id = '".$_REQUEST['order_id']."' AND shipping_status > 0 ";
        $return_allowable = $GLOBALS['db']->getOne($sql);
        $this->assign('return_allowable', $return_allowable);

        /* 订单商品 */
        $goods_info = rec_goods($recr_id);

        $this->assign('goods',$goods_info);

        $this->display();
    }

    /**
     * 订单跟踪
     */
    public function actionOrderTracking(){
        $order_id = I('order_id', 0, 'intval');
        $order = get_order_detail($order_id, $this->user_id);
        if ($order === false) {
            $this->err->show(L('back_home_lnk'), './');
            exit();
        }
        if($order['invoice_no']){
            preg_match("/^<a.*href=\"(.*?)\">/is", $order['invoice_no'], $url);
            if($url[1]){
                redirect($url[1]);
            }
        }
        show_message(L('msg_unfilled_or_receive'), L('user_center'), url('user/index/index'));
    }

    /* 确认收货 */
    public function actionAffirmReceived()
    {
        $user_id = $this->user_id;
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

        if (affirm_received($order_id, $user_id)) {
            ecs_header("Location: " . url('user/order/index'));
            exit();
        } else {
//            show_message("还未发货或者已收货");
            show_message(L('msg_unfilled_or_receive'));
        }
    }
     /**
     * 删除订单
     */
    public function actionDelOrder()
    {
        $order_id=I('order_id');
        if(IS_AJAX){
           $sql="UPDATE {pre}order_info SET `is_delete`=1 where order_id=".$order_id;
           $this->db->query($sql);
           die(json_encode(array('y'=>1)));
        }
    }

    /**
     * 取消订单
     */
    public function actionCancel()
    {
        $order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
        if (cancel_order($order_id, $this->user_id)) {
            ecs_header("Location: " . url('user/order/index'));
            exit();
        } else {
            $this->err->show(L('order_list_lnk'), url('user/order/index'));
        }
    }
    /**
     * 验证是否登录
     */
    public function actionchecklogin(){

        if(!$this->user_id){
            $url = urlencode(__HOST__ . $_SERVER['REQUEST_URI']);
            if(IS_POST) {
                $url = urlencode($_SERVER['HTTP_REFERER']);
            }
            ecs_header("Location: ".url('user/login/index',array('back_act'=>$url)));
            exit;
        }
    }
    // 订单列表获取订单数量
    function Get_Order_Where_Count($user_id = 0, $show_type = 0, $where = '')
    {
        $sql = "SELECT COUNT(*) FROM " . $GLOBALS['ecs']->table('order_info') . " as oi" . " WHERE oi.user_id = '$user_id' and oi.is_delete = '$show_type'" . " and (select count(*) from " . $GLOBALS['ecs']->table('order_info') . " as oi_2 where oi_2.main_order_id = oi.order_id) = 0 " . // 主订单下有子订单时，则主订单不显示 .
            $where;
        return $GLOBALS['db']->getOne($sql);
    }

    public function actionChangePayment()
    {
        // 检查支付方式 检查订单号
        $pay_id = intval($_POST['pay_id']);
        $order_id = intval($_POST['order_id']);

        if ($pay_id <= 0 || $order_id <= 0) {
            $this->redirect('index/index');
        }

        $payment_info = payment_info($pay_id);
        if (empty($payment_info)) {
            $this->redirect('index/index');
        }

        // 取得订单
        $order = order_info($order_id);
        if (empty($order) || ($_SESSION['user_id'] != $order['user_id'])) {
            $this->redirect('index/index');
        }
        // 检查订单是否未付款和未发货 以及订单金额是否为0 和支付id是否为改变
        if (($order['pay_status'] == PS_UNPAYED || $order['pay_status'] == PS_PAYED_PART) && $order['shipping_status'] == SS_UNSHIPPED && $order['goods_amount'] > 0 && $order['pay_id'] != $pay_id) {
            $order_amount = $order['order_amount'] - $order['pay_fee'];
            $pay_fee = pay_fee($pay_id, $order_amount);
            $order_amount += $pay_fee;

            $data['pay_id'] = $pay_id;
            $data['pay_name'] = $payment_info['pay_name'];
            $data['pay_fee'] = $pay_fee;
            $data['order_amount'] = $order_amount;
            $where['order_id'] = $order_id;
            $this->model->table('order_info')
                ->data($data)
                ->where($where)
                ->save();
        }
        $this->redirect('detail', array('order_id' => $order_id));
    }

/**
 * 余额支付
*/
    public function actionSurplusPay()
    {
        $order_id = I("post.order_id", '', 'intval');
        $type = I("get.type");
        if ($order_id <= 0) {
            ecs_header("Location: ./\n");
            exit;
        }
        $order = order_info($order_id);

        /*是否预售，检测结算时间是否超出尾款结束时间 liu */

        if ($type == 'presale' && $order['pay_status'] == PS_PAYED_PART) {
            $result = presale_settle_status($order['extension_id']);
            if ($result['settle_status'] == 0 || $result['settle_status'] == -1) {
                ecs_header("Location: ./\n");
                exit;
            }
        }

        /* 检查订单用户跟当前用户是否一致 */
        if ($_SESSION['user_id'] != $order['user_id']) {
            ecs_header("Location: ./\n");
            exit;
        }
        /* 检查订单是否未付款，检查应付款金额是否大于0 */
        if ($order['pay_status'] != PS_UNPAYED || $order['order_amount'] <= 0) {
            if ($order['pay_status'] != PS_PAYED_PART) {
                $GLOBALS['err']->add(L('error_order_is_paid'));
                $GLOBALS['err']->show(L('order_detail'), url('user/order/detail', array('order_id' => $order_id)));
            }
        }
        /* 检查余额 */
        $surplus = floatval($_POST['surplus']);
        if ($surplus <= 0) {
            $GLOBALS['err']->add(L('error_surplus_invalid'));
            $GLOBALS['err']->show(L('order_detail'), url('user/order/detail', array('order_id' => $order_id)));
        }
        /* 余额是否超过了应付款金额，改为应付款金额 */
        if ($surplus > $order['order_amount']) {
            $surplus = $order['order_amount'];
        }
        /* 修改订单，重新计算支付费用 */
        $order['surplus'] += $surplus;
        $order['order_amount'] -= $surplus;
        if ($order['order_amount'] > 0) {
            $cod_fee = 0;
            if ($order['shipping_id'] > 0) {
                $regions = array($order['country'], $order['province'], $order['city'], $order['district']);
                $shipping = shipping_area_info($order['shipping_id'], $regions);
                if ($shipping['support_cod'] == '1') {
                    $cod_fee = $shipping['pay_fee'];
                }
            }

            $pay_fee = 0;
            if ($order['pay_id'] > 0) {
                $pay_fee = pay_fee($order['pay_id'], $order['order_amount'], $cod_fee);
            }

            $order['pay_fee'] = $pay_fee;
            $order['order_amount'] += $pay_fee;
        }
        /* 如果全部支付，设为已确认、已付款 | 预售商品设为已确认、部分付款 */
        if ($order['order_amount'] == 0) {
            $amount = $order['goods_amount'] + $order['shipping_fee'];
            $paid = $order['money_paid'] + $order['surplus'];
            if ($_POST['pay_status'] == 'presale' && $amount > $paid) {//判断是否是预售订金支付 liu
                $order['pay_status'] = PS_PAYED_PART;
                $order['order_amount'] = $amount - $paid;
            } else {
                $order['pay_status'] = PS_PAYED;
            }
            if ($order['order_status'] == OS_UNCONFIRMED) {
                $order['order_status'] = OS_CONFIRMED;
                $order['confirm_time'] = gmtime();
            }
            $order['pay_time'] = gmtime();
        }
        $order = addslashes_deep($order);
        update_order($order_id, $order);

        /* 更新用户余额 */
        $change_desc = sprintf(L('pay_order_by_surplus'), $order['order_sn']);
        log_account_change($order['user_id'], (-1) * $surplus, 0, 0, 0, $change_desc);

        /* 跳转 */
        $this->redirect('user/order/detail', array('order_id' => $order_id));
        exit;

    }
}

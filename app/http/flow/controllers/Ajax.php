<?php
namespace app\http\flow\controllers;

use app\http\base\controllers\Frontend;

class Ajax extends Frontend {

    /**
     * 构造，加载文件语言包和helper文件
     */
    public function __construct(){
        parent::__construct();
        L(require(LANG_PATH  . C('shop.lang') . '/user.php'));
        L(require(LANG_PATH  . C('shop.lang') . '/flow.php'));

        $files = array(
            'order',
        );
        $this->load_helper($files);
    }

    /**
     * 改变配送方式
     */
    public function actionselectshipping(){

        $result = array('error' => '', 'content' => '', 'need_insure' => 0);

        /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

        /* 获得收货人信息 */
        $consignee = get_consignee($_SESSION['user_id']);

        /* 对商品信息赋值 */
        $cart_goods = cart_goods($flow_type); // 取得商品列表，计算合计

        if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
        {
            $result['error'] = L('no_goods_in_cart');
        }
        else
        {
            /* 取得购物流程设置 */
            $this->assign('config', C('shop'));

            /* 取得订单信息 */
            $order = flow_order_info();

            $order['shipping_id'] = intval($_REQUEST['shipping']);
            $regions = array($consignee['country'], $consignee['province'], $consignee['city'], $consignee['district']);
            $shipping_info = shipping_area_info($order['shipping_id'], $regions);

            /* 计算订单的费用 */
            $total = order_fee($order, $cart_goods, $consignee);
            $this->assign('total', $total);

            /* 取得可以得到的积分和红包 */
            $this->assign('total_integral', cart_amount(false, $flow_type) - $total['bonus'] - $total['integral_money']);
            $this->assign('total_bonus',    price_format(get_total_bonus(), false));

            /* 团购标志 */
            if ($flow_type == CART_GROUP_BUY_GOODS)
            {
                $this->assign('is_group_buy', 1);
            }

            $result['cod_fee']     = $shipping_info['pay_fee'];
            if (strpos($result['cod_fee'], '%') === false)
            {
                $result['cod_fee'] = price_format($result['cod_fee'], false);
            }
            $result['need_insure'] = ($shipping_info['insure'] > 0 && !empty($order['need_insure'])) ? 1 : 0;
            $result['content']     = $this->fetch('lib_order_total.html');
        }

        die(json_encode($result));
    }

    /**
     * 选定/取消配送的保价
     */
    public function actionselectinsure(){

        $result = array('error' => '', 'content' => '', 'need_insure' => 0);

            /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

            /* 获得收货人信息 */
        $consignee = get_consignee($_SESSION['user_id']);

            /* 对商品信息赋值 */
        $cart_goods = cart_goods($flow_type); // 取得商品列表，计算合计

        if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
        {
        $result['error'] = L('no_goods_in_cart');
        }
        else
            {
                /* 取得购物流程设置 */
                $this->assign('config', C('shop'));

                /* 取得订单信息 */
                $order = flow_order_info();

                $order['need_insure'] = intval($_REQUEST['insure']);

                /* 保存 session */
                $_SESSION['flow_order'] = $order;

                /* 计算订单的费用 */
                $total = order_fee($order, $cart_goods, $consignee);
                $this->assign('total', $total);

                /* 取得可以得到的积分和红包 */
                $this->assign('total_integral', cart_amount(false, $flow_type) - $total['bonus'] - $total['integral_money']);
                $this->assign('total_bonus',    price_format(get_total_bonus(), false));

                /* 团购标志 */
                if ($flow_type == CART_GROUP_BUY_GOODS)
                {
                    $this->assign('is_group_buy', 1);
                }

                $result['content'] = $this->fetch('lib_order_total.html');
            }
        die(json_encode($result));
    }

    /**
     * 改变支付方式
     */
    public function actionselectpayment(){

        $result = array('error' => '', 'content' => '', 'need_insure' => 0, 'payment' => 1);

            /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

            /* 获得收货人信息 */
        $consignee = get_consignee($_SESSION['user_id']);

            /* 对商品信息赋值 */
        $cart_goods = cart_goods($flow_type); // 取得商品列表，计算合计

        if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
        {
        $result['error'] = L('no_goods_in_cart');
        }
        else
            {
                /* 取得购物流程设置 */
                $this->assign('config', C('shop'));

                /* 取得订单信息 */
                $order = flow_order_info();

                $order['pay_id'] = intval($_REQUEST['payment']);
                $payment_info = payment_info($order['pay_id']);
                $result['pay_code'] = $payment_info['pay_code'];

                /* 保存 session */
                $_SESSION['flow_order'] = $order;

                /* 计算订单的费用 */
                $total = order_fee($order, $cart_goods, $consignee);
                $this->assign('total', $total);

                /* 取得可以得到的积分和红包 */
                $this->assign('total_integral', cart_amount(false, $flow_type) - $total['bonus'] - $total['integral_money']);
                $this->assign('total_bonus',    price_format(get_total_bonus(), false));

                /* 团购标志 */
                if ($flow_type == CART_GROUP_BUY_GOODS)
                {
                    $this->assign('is_group_buy', 1);
                }

                $result['content'] = $this->fetch('lib_order_total.html');
            }

        die(json_encode($result));
    }

    /**
     * 改变商品包装
     */
    public function actionselectpack(){
        $result = array('error' => '', 'content' => '', 'need_insure' => 0);

            /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

            /* 获得收货人信息 */
        $consignee = get_consignee($_SESSION['user_id']);

            /* 对商品信息赋值 */
        $cart_goods = cart_goods($flow_type); // 取得商品列表，计算合计

        if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
        {
        $result['error'] = L('no_goods_in_cart');
        }
        else
            {
                /* 取得购物流程设置 */
                $this->assign('config', C('shop'));

                /* 取得订单信息 */
                $order = flow_order_info();

                $order['pack_id'] = intval($_REQUEST['pack']);

                /* 保存 session */
                $_SESSION['flow_order'] = $order;

                /* 计算订单的费用 */
                $total = order_fee($order, $cart_goods, $consignee);
                $this->assign('total', $total);

                /* 取得可以得到的积分和红包 */
                $this->assign('total_integral', cart_amount(false, $flow_type) - $total['bonus'] - $total['integral_money']);
                $this->assign('total_bonus',    price_format(get_total_bonus(), false));

                /* 团购标志 */
                if ($flow_type == CART_GROUP_BUY_GOODS)
                {
                    $this->assign('is_group_buy', 1);
                }

                $result['content'] = $this->fetch('lib_order_total.html');
            }

        die(json_encode($result));
    }


    /**
     * 改变贺卡
     */
    public function actionselectcard(){
        $result = array('error' => '', 'content' => '', 'need_insure' => 0);

            /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

            /* 获得收货人信息 */
        $consignee = get_consignee($_SESSION['user_id']);

            /* 对商品信息赋值 */
        $cart_goods = cart_goods($flow_type); // 取得商品列表，计算合计

        if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
        {
        $result['error'] = L('no_goods_in_cart');
        }
        else
            {
                /* 取得购物流程设置 */
                $this->assign('config', C('shop'));

                /* 取得订单信息 */
                $order = flow_order_info();

                $order['card_id'] = intval($_REQUEST['card']);

                /* 保存 session */
                $_SESSION['flow_order'] = $order;

                /* 计算订单的费用 */
                $total = order_fee($order, $cart_goods, $consignee);
                $this->assign('total', $total);

                /* 取得可以得到的积分和红包 */
                $this->assign('total_integral', cart_amount(false, $flow_type) - $order['bonus'] - $total['integral_money']);
                $this->assign('total_bonus',    price_format(get_total_bonus(), false));

                /* 团购标志 */
                if ($flow_type == CART_GROUP_BUY_GOODS)
                {
                    $this->assign('is_group_buy', 1);
                }

                $result['content'] = $this->fetch('lib_order_total.html');
            }

        die(json_encode($result));
    }


    /**
     *改变余额
     */
    public function actionchangesurplus(){
        $surplus   = floatval($_GET['surplus']);
        $user_info = user_info($_SESSION['user_id']);

        if ($user_info['user_money'] + $user_info['credit_line'] < $surplus)
        {
        $result['error'] = L('surplus_not_enough');
        }
        else
            {
                /* 取得购物类型 */
                $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

                /* 取得购物流程设置 */
                $this->assign('config', C('shop'));

                /* 获得收货人信息 */
                $consignee = get_consignee($_SESSION['user_id']);

                /* 对商品信息赋值 */
                $cart_goods = cart_goods($flow_type); // 取得商品列表，计算合计

                if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
                {
                    $result['error'] = L('no_goods_in_cart');
                }
                else
                {
                    /* 取得订单信息 */
                    $order = flow_order_info();
                    $order['surplus'] = $surplus;

                    /* 计算订单的费用 */
                    $total = order_fee($order, $cart_goods, $consignee);
                    $this->assign('total', $total);

                    /* 团购标志 */
                    if ($flow_type == CART_GROUP_BUY_GOODS)
                    {
                        $this->assign('is_group_buy', 1);
                    }

                    $result['content'] = $this->fetch('lib_order_total.html');
                }
            }
        die(json_encode($result));
    }


    /**
     * 改变积分
     */
    public function actionchangeintegral(){

        $points    = floatval($_GET['points']);
        $user_info = user_info($_SESSION['user_id']);

        /* 取得订单信息 */
        $order = flow_order_info();

        $flow_points = flow_available_points($_SESSION['cart_value']);  // 该订单允许使用的积分
        $user_points = $user_info['pay_points']; // 用户的积分总数

        if ($points > $user_points)
        {
            $result['error'] = L('integral_not_enough');
        }
        elseif ($points > $flow_points)
        {
            $result['error'] = sprintf(L('integral_too_much'), $flow_points);
        }
        else
        {
            /* 取得购物类型 */
            $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

            $order['integral'] = $points;

            /* 获得收货人信息 */
            $consignee = get_consignee($_SESSION['user_id']);

            /* 对商品信息赋值 */
            $cart_goods = cart_goods($flow_type); // 取得商品列表，计算合计

            if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
            {
                $result['error'] = L('no_goods_in_cart');
            }
            else
            {
                /* 计算订单的费用 */
                $total = order_fee($order, $cart_goods, $consignee);
                $this->assign('total',  $total);
                $this->assign('config', C('shop'));

                /* 团购标志 */
                if ($flow_type == CART_GROUP_BUY_GOODS)
                {
                    $this->assign('is_group_buy', 1);
                }

                $result['content'] = $this->fetch('lib_order_total.html');
                $result['error'] = '';
            }
        }
        die(josn_encode($result));
    }


    /**
     * 改变红包
     */
    public function actionchangebonus(){

        $result = array('error' => '', 'content' => '');

        /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

        /* 获得收货人信息 */
        $consignee = get_consignee($_SESSION['user_id']);

        /* 对商品信息赋值 */
        $cart_goods = cart_goods($flow_type); // 取得商品列表，计算合计

        if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
        {
            $result['error'] = L('no_goods_in_cart');
        }
        else
        {
            /* 取得购物流程设置 */
            $this->assign('config', C('shop'));

            /* 取得订单信息 */
            $order = flow_order_info();

            $bonus = bonus_info(intval($_GET['bonus']));

            if ((!empty($bonus) && $bonus['user_id'] == $_SESSION['user_id']) || $_GET['bonus'] == 0)
            {
                $order['bonus_id'] = intval($_GET['bonus']);
            }
            else
            {
                $order['bonus_id'] = 0;
                $result['error'] = L('invalid_bonus');
            }

            /* 计算订单的费用 */
            $total = order_fee($order, $cart_goods, $consignee);
            $this->assign('total', $total);

            /* 团购标志 */
            if ($flow_type == CART_GROUP_BUY_GOODS)
            {
                $this->assign('is_group_buy', 1);
            }

            $result['content'] = $this->fetch('lib_order_total.html');
        }
        die(json_encode($result));
    }

    /**
     * 改变发票的设置
     */
    public function actionchangeneedinv(){

        $result = array('error' => '', 'content' => '');

        $_GET['inv_type'] = !empty($_GET['inv_type']) ? json_str_iconv(urldecode($_GET['inv_type'])) : '';
        $_GET['invPayee'] = !empty($_GET['invPayee']) ? json_str_iconv(urldecode($_GET['invPayee'])) : '';
        $_GET['inv_content'] = !empty($_GET['inv_content']) ? json_str_iconv(urldecode($_GET['inv_content'])) : '';

        /* 取得购物类型 */
        $flow_type = isset($_SESSION['flow_type']) ? intval($_SESSION['flow_type']) : CART_GENERAL_GOODS;

        /* 获得收货人信息 */
        $consignee = get_consignee($_SESSION['user_id']);

        /* 对商品信息赋值 */
        $cart_goods = cart_goods($flow_type); // 取得商品列表，计算合计

        if (empty($cart_goods) || !check_consignee_info($consignee, $flow_type))
        {
            $result['error'] = L('no_goods_in_cart');
            die(json_encode($result));
        }
        else
        {
            /* 取得购物流程设置 */
            $this->assign('config', C('shop'));

            /* 取得订单信息 */
            $order = flow_order_info();

            if (isset($_GET['need_inv']) && intval($_GET['need_inv']) == 1)
            {
                $order['need_inv']    = 1;
                $order['inv_type']    = trim(stripslashes($_GET['inv_type']));
                $order['inv_payee']   = trim(stripslashes($_GET['inv_payee']));
                $order['inv_content'] = trim(stripslashes($_GET['inv_content']));
            }
            else
            {
                $order['need_inv']    = 0;
                $order['inv_type']    = '';
                $order['inv_payee']   = '';
                $order['inv_content'] = '';
            }

            /* 计算订单的费用 */
            $total = order_fee($order, $cart_goods, $consignee);
            $this->assign('total', $total);
            /* 团购标志 */
            if ($flow_type == CART_GROUP_BUY_GOODS)
            {
                $this->assign('is_group_buy', 1);
            }
            die($this->fetch('lib_order_total.html'));
        }
    }


    /**
     * 改变缺货处理时的方式
     */
    public function actionchangeoos(){

        /* 取得订单信息 */
        $order = flow_order_info();

        $order['how_oos'] = intval($_GET['oos']);

        /* 保存 session */
        $_SESSION['flow_order'] = $order;
    }

    /**
     * 检查用户输入的余额
     */
    public function actionchecksurplus(){
        $surplus   = floatval($_GET['surplus']);
        $user_info = user_info($_SESSION['user_id']);
        if (($user_info['user_money'] + $user_info['credit_line'] < $surplus))
        {
            die(L('surplus_not_enough'));
        }
        exit;
    }


    /**
     * 检查用户输入的积分
     */
    public function actioncheckintegral(){
        $points      = floatval($_GET['integral']);
        $user_info   = user_info($_SESSION['user_id']);
        $flow_points = flow_available_points($_SESSION['cart_value']);  // 该订单允许使用的积分
        $user_points = $user_info['pay_points']; // 用户的积分总数

        if ($points > $user_points)
        {
            die(L('integral_not_enough'));
        }

        if ($points > $flow_points)
        {
            die(sprintf(L('integral_too_much'), $flow_points));
        }
        exit;
    }
}
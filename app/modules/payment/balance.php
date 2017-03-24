<?php
defined('IN_ECTOUCH') or die('Deny Access');

/**
 * 余额支付插件类
 */
class balance
{

    /**
     * 提交函数
     */
    function get_code($order, $payment)
    {
        $action = url('user/order/surpluspay',array('type'=>$order['extension_code']));

        $order_amount = $order['order_amount'];
        $order_id = $order['order_id'];


        $button = <<<eot
         <form id="pay_form" name="pay_form" action="{$action}" method="post">
             <input type="hidden" name="order_id"  value="{$order_id}" />
             <input type="hidden" name="surplus"  value="{$order_amount}" />
           <input type="submit" type="hidden" value="余额支付" class="btn btn-info ect-btn-info ect-colorf ect-bg c-btn3  box-flex btn-submit">
        </form>
eot;
        return $button;
    }

    /**
     * 处理函数
     */
    function response()
    {
        return;
    }
}

?>
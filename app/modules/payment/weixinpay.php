<?php
defined('IN_ECTOUCH') or die('Deny Access');

use ectouch\Wechat;

class weixinpay
{
    private $parameters; // cft 参数
    private $payment; // 配置信息
    /**
     * 生成支付代码
     * @param   array $order 订单信息
     * @param   array $payment 支付方式信息 appid,mch_id,key
     */
    public function get_code($order, $payment)
    {
        if (! defined('CHARSET')) {
            $charset = 'utf-8';
        } else {
            $charset = CHARSET;
        }
        include_once(BASE_PATH.'helpers/payment_helper.php');

        $weObj = new Wechat($payment);

        $order_amount = $order['order_amount'] * 100;
        $this->setParameter("body", $order['order_sn']); // 商品描述
        $this->setParameter("out_trade_no", $order['order_sn'] . 'A' . $order['log_id']); // 商户订单号
        $this->setParameter("total_fee", $order_amount); // 总金额 分
        $this->setParameter("spbill_create_ip", $this->get_client_ip()); // 客户端IP
        $this->setParameter("notify_url", notify_url(basename(__FILE__, '.php'))); // 异步通知地址
        $this->setParameter("trade_type", "APP"); // 交易类型

        $respond = $weObj->appPayOrder($this->parameters,true);
        $respond['order_id'] = $order['order_id'];
        $respond['payShop'] = 'dashangchuang';
        $appParameters = json_encode($respond);

        // wxjsbridge
        $js = "<script type='text/javascript'>var u = navigator.userAgent;var isAndroid = u.indexOf('Android') > -1 || u.indexOf('Adr') > -1;var isiOS = !!u.match(/\(i[^;]+;( U;)? CPU.+Mac OS X/);function appCall(){if(isiOS){window.webkit.messageHandlers.yjtWXPay.postMessage('".$appParameters."');}else if(isAndroid){myObj.fun1FromAndroid('".$appParameters."');}}</script>";

        $button = '<a class="box-flex btn-submit" type="button" onclick="appCall();">微信支付</a>' . $js;

        return $button;
    }

    /**
     * 同步通知
     * @param $data
     * @return mixed
     */
    public function callback($data)
    {
        if ($_GET['status'] == 1) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 异步通知
     * @param $data
     * @return mixed
     */
    public function notify($data)
    {
        include_once(BASE_PATH . 'helpers/payment_helper.php');
        $_POST['postStr'] = file_get_contents("php://input");
        if (!empty($_POST['postStr'])) {
            $payment = get_payment($data['code']);
            $postdata = json_decode(json_encode(simplexml_load_string($_POST['postStr'], 'SimpleXMLElement', LIBXML_NOCDATA)), true);
            /* 检查插件文件是否存在，如果存在则验证支付是否成功，否则则返回失败信息 */
            // 微信端签名
            $wxsign = $postdata['sign'];
            unset($postdata['sign']);

            foreach ($postdata as $k => $v) {
                $Parameters[$k] = $v;
            }
            // 签名步骤一：按字典序排序参数
            ksort($Parameters);

            $buff = "";
            foreach ($Parameters as $k => $v) {
                $buff .= $k . "=" . $v . "&";
            }
            $String = '';
            if (strlen($buff) > 0) {
                $String = substr($buff, 0, strlen($buff) - 1);
            }
            // 签名步骤二：在string后加入KEY
            $String = $String . "&key=" . $payment['key'];
            // 签名步骤三：MD5加密
            $String = md5($String);
            // 签名步骤四：所有字符转为大写
            $sign = strtoupper($String);
            // 验证成功
            if ($wxsign == $sign) {
                // 交易成功
                if ($postdata['result_code'] == 'SUCCESS') {
                    // 获取log_id
                    $out_trade_no = explode('A', $postdata['out_trade_no']);
                    $order_sn = $out_trade_no[1]; // 订单号log_id
                    // 修改订单信息(openid，tranid)
                    dao('pay_log')->data(array('openid' => $postdata['openid'], 'transid' => $postdata['transaction_id']))->where(array('log_id' => $order_sn))->save();
                    // 改变订单状态
                    order_paid($order_sn, 2);
                }
                $returndata['return_code'] = 'SUCCESS';
            } else {
                $returndata['return_code'] = 'FAIL';
                $returndata['return_msg'] = '签名失败';
            }
        } else {
            $returndata['return_code'] = 'FAIL';
            $returndata['return_msg'] = '无数据返回';
        }
        // 数组转化为xml
        $xml = "<xml>";
        foreach ($returndata as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";

        exit($xml);
    }

    /**
     * 订单查询
     * @return mixed
     */
    public function query($order, $payment)
    {

    }

    /**
     * 获取当前服务器的IP
     */
    private function get_client_ip()
    {
        if ($_SERVER['REMOTE_ADDR']) {
            $cip = $_SERVER['REMOTE_ADDR'];
        } elseif (getenv("REMOTE_ADDR")) {
            $cip = getenv("REMOTE_ADDR");
        } elseif (getenv("HTTP_CLIENT_IP")) {
            $cip = getenv("HTTP_CLIENT_IP");
        } else {
            $cip = "unknown";
        }
        return $cip;
    }

    // 过滤空格字符
    function trimString($value)
    {
        $ret = null;
        if (null != $value) {
            $ret = $value;
            if (strlen($ret) == 0) {
                $ret = null;
            }
        }
        return $ret;
    }


    /**
     * 作用：产生随机字符串，不长于32位
     */
    // public function createNoncestr($length = 32)
    // {
    //     $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
    //     $str = "";
    //     for ($i = 0; $i < $length; $i++) {
    //         $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    //     }
    //     return $str;
    // }

    /**
     * 作用：设置请求参数
     */
    function setParameter($parameter, $parameterValue)
    {
        $this->parameters[$this->trimString($parameter)] = $this->trimString($parameterValue);
    }

}
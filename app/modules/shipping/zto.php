<?php
defined('IN_ECTOUCH') or die('Deny Access');

class zto
{
    /**
     * 配置信息
     */
    public $configure;

    /**
     * 构造函数
     * @param: $configure[array]    配送方式的参数的数组
     * @return null
     */
    public function __construct($cfg = array())
    {
        foreach ($cfg AS $key=>$val)
        {
            $this->configure[$val['name']] = $val['value'];
        }
    }

    /**
     * 计算订单的配送费用的函数
     *
     * @param   float   $goods_weight   商品重量
     * @param   float   $goods_amount   商品金额
     * @param   float   $goods_number   商品件数
     * @return  decimal
     */
    public function calculate($goods_weight, $goods_amount, $goods_number)
    {
        if ($this->configure['free_money'] > 0 && $goods_amount >= $this->configure['free_money'])
        {
            return 0;
        }
        else
        {
            @$fee = $this->configure['base_fee'];
            $this->configure['fee_compute_mode'] = !empty($this->configure['fee_compute_mode']) ? $this->configure['fee_compute_mode'] : 'by_weight';

            if ($this->configure['fee_compute_mode'] == 'by_number')
            {
                $fee = $goods_number * $this->configure['item_fee'];
            }
            else
            {
                if ($goods_weight > 1)
                {
                    $fee += (ceil(($goods_weight - 1))) * $this->configure['step_fee'];
                }
            }

            return $fee;
        }
    }

    /**
     * 查询发货状态
     *
     * @access  public
     * @param   string  $invoice_sn     发货单号
     * @return  string
     */
    public function query($invoice_sn)
    {
        $str = '<a class="btn-submit" href="http://wap.guoguo-app.com/wuliuDetail.htm?mailNo=' .$invoice_sn. '">订单跟踪</a>';
        return $str;
    }

    /**
     * 计算保价费用
     * 保价费不低于100元，保价金额不得高于10000元，保价金额超过10000元的，超过的部分无效
     * @access  public
     * @param   int     $goods_amount       保价费用
     * @param   int     $insure             保价比例
     *
     * @return void
     */
    public function calculate_insure ($goods_amount, $insure)
    {
        if ($goods_amount > 10000)
        {
            $goods_amount = 10000;
        }

        $fee = $goods_amount * $insure;

        if ($fee < 100)
        {
            $fee = 100;
        }

        return $fee;
    }

}

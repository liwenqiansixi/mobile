<?php
namespace app\modules\notification\sms;

use app\modules\notification\sms\driver\Ihuyi;
use app\modules\notification\sms\driver\Alidayu;

class Sms
{

    protected $config = array(
        'sms_name' => '',
        'sms_password' => '',
    );

    protected $sms;

    /**
     * 驱动对象
     * @var array
     */
    protected static $objArr = array();

    /**
     * 构建函数
     * @param array $config 邮箱配置
     */
    public function __construct($config = array())
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 回调驱动
     * @param  string $method 回调方法
     * @param  array $args 回调参数
     * @return object
     */
    public function __call($method, $args)
    {
        $sms_type = $this->config['sms_type'];
        if (!isset(self::$objArr[$sms_type])) {
            $smsDriver = __NAMESPACE__ . '\driver\\' . ucfirst($this->config['sms_type']);
            if (!class_exists($smsDriver)) {
                throw new \Exception("Sms Driver '{$smsDriver}' not found'", 500);
            }
            self::$objArr[$sms_type] = new $smsDriver($this->config[$sms_type]);
        }
        return call_user_func_array(array(self::$objArr[$sms_type], $method), $args);
    }
}

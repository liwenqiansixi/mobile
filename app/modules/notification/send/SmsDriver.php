<?php
namespace app\modules\notification\send;

use app\modules\notification\sms\Sms;

/**
 * 短信发送驱动
 */
class SmsDriver implements SendInterface
{

    protected $config = array(
        'sms_name' => '',
        'sms_password' => '',
    );

    protected $sms;

    public function __construct($config = array())
    {
        $this->config = array_merge($this->config, $config);
        $this->sms = new Sms($this->config);
    }

    /**
     * 发送短信
     * @param  string $to 收信人
     * @param  string $title 标题
     * @param  string $content 内容
     * @param  array $data 其他数据
     * @return array
     */
    public function push($to, $title, $content, $data = array())
    {
        return $this->sms->setSms($title, $content)->sendSms($to);
    }

    public function getError()
    {
        return $this->sms->getError();
    }
}

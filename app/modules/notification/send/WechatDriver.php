<?php
namespace app\modules\notification\send;

use app\modules\notification\wechat\Wechat;

/**
 * 微信发送驱动
 */
class WechatDriver implements SendInterface
{

    protected $config = array();

    protected $wechat;

    public function __construct($config = array())
    {
        $this->config = array_merge($this->config, $config);
        $this->wechat = new Wechat($this->config);
    }

    /**
     * 发送微信
     * @param  string $to 接收人
     * @param  string $title 标题 code 模板标识
     * @param  string $content 模板消息内容
     * @param  array $data 其他数据 url
     * @return array
     */
    public function push($to, $title, $content, $data = array())
    {
        return $this->wechat->setData($to, $title, $content, $data)->send($to);
    }

    public function getError()
    {
        return $this->wechat->getError();
    }
}

<?php
namespace app\custom\site\controllers;

use app\http\site\controllers\Index;

class Send extends Index
{
    public function actionTest()
    {
        // 短信发送测试
        $message = array(
            'code' => '1234',
            'product' => 'sitename'
        );
        $res = send_sms('18801828888', 'sms_signin', $message);
        if ($res !== true) {
            exit($res);
        };

        // 邮件发送测试
        $res = send_mail('xxx', 'wanglin@ecmoban.com', 'title', 'content');
        if ($res !== true) {
            exit($res);
        };
    }
}
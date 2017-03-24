<?php
namespace app\behavior;

use ectouch\Http;

/**
 * 系统行为扩展：SaaS服务兼容
 */
class SaaSServiceBehavior
{

    // 行为扩展的执行入口必须是run
    public function run()
    {
        // 定义目录
        $wechat_path = BASE_PATH . 'http/wechat';
        $drp_path = BASE_PATH . 'http/drp';
        // 兼容SaaS
        if (file_exists(ROOT_PATH . 'storage/saas_mode.txt')) {
            $site_url = "aHR0cDovL2Nsb3VkLmRzY21hbGwuY24vaW5kZXgucGhwP2M9c2l0ZSZhPWxldmVsJm1hbGxfZG9tYWluPQ==";
            $site_rsp = Http::doGet(base64_decode($site_url) . substr(config('DB_NAME'), 3));
            $site_rsp = json_decode($site_rsp, true);
            // 返回值
            if ($site_rsp['code'] == -1) {
                $mall_level = 0;
            } else {
                $mall_level = $site_rsp['data']['mall_level'];
            }
            // 权限
            if ($mall_level <= 0) {
                $wechat_path .= time();
                $drp_path .= time();
            } else if ($mall_level == 1) {
                $drp_path .= time();
            }
        }
        // 定义常量
        define('APP_WECHAT_PATH', $wechat_path);
        define('APP_DRP_PATH', $drp_path);
    }
}

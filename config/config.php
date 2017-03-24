<?php
$db = require __DIR__ . '/database.php';
$deploy = require __DIR__ . '/deploy.php';
$envfile = __DIR__ . '/config-local.php';
$env = is_file($envfile) ? require $envfile : array();
$static = isset($deploy['static_url']) ? $deploy['static_url'] : dirname(__ROOT__);
$conf = array(
    'url_model' => '0',
    'url_pathinfo_depr' => '/',

    'url_router_on' => true,
    'url_route_rules' => require ROOT_PATH . 'routes/web' . CONF_EXT,

    'curl_http_version' => CURL_HTTP_VERSION_1_1, // 设置curl的HTTP版本

    'session_auto_start' => false,
    'session_options' => require __DIR__ . '/session' . CONF_EXT,

#    'default_module' => 'site',//默认首页是site，如果启用可视化把site换成index
    'default_module' => 'index',//默认首页是site，如果启用可视化把site换成index
    'action_prefix' => 'action',
    'var_pathinfo' => 'r',

    'taglib_begin' => '{',
    'taglib_end' => '}',

    'tmpl_file_depr' => '.',
    'tmpl_parse_string' => array(
        '__STATIC__' => rtrim(str_replace('\\', '/', $static), '/'),
        '__PUBLIC__' => __ROOT__ . '/resources/assets',
        '__TPL__' => __ROOT__ . '/statics',
    ),

    'upload_path' => isset($deploy['upload_path']) ? rtrim($deploy['upload_path'], '/') . '/' : dirname(ROOT_PATH) . '/',

    'assets' => require __DIR__ . '/assets' . CONF_EXT,

    'tmpl_action_error' => ROOT_PATH . 'resources/views/vendor/message.html', // 默认错误跳转对应的模板文件
    'tmpl_action_success' => ROOT_PATH . 'resources/views/vendor/message.html', // 默认成功跳转对应的模板文件
    'tmpl_exception_file' => ROOT_PATH . 'resources/views/errors/exception.html',// 异常页面的模板文件
);
return array_merge($conf, $db, $deploy, $env);

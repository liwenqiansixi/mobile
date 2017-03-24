<?php
return array(
    'app_init' => array(

    ),
    'app_begin' => array(
        'app\behavior\MigrateDbBehavior',
        'app\behavior\CompatibleBehavior',
        'app\behavior\SaaSServiceBehavior',
    ),
    'frontend_init' => array(
        'app\behavior\ReplaceLangBehavior',
    ),
    'template_replace' => array(
        'app\behavior\ParseTemplateBehavior',
    )
);
<?php

/**
 * ECTouch E-Commerce Project
 * ============================================================================
 * Copyright (c) 2014-2016 http://ectouch.cn All rights reserved.
 * ----------------------------------------------------------------------------
 * This is NOT a freeware, use is subject to license terms
 * ----------------------------------------------------------------------------
 * Licensed ( http://www.ectouch.cn/license )
 * ----------------------------------------------------------------------------
 */

define('BIND_MODULE', 'Respond');
define('BIND_CONTROLLER', 'Index');
define('BIND_ACTION', 'notify');
$_GET['code'] = basename(__FILE__, '.php');
require __DIR__ . '/../../index.php';
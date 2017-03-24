<?php

/*
|--------------------------------------------------------------------------
| Register The Composer Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script here so that we do not have to worry about the
| loading of any our classes "manually". Feels great to relax.
|
*/

require __DIR__.'/../vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;

$database = require CONF_PATH . 'database.php';

$capsule = new Capsule;
$capsule->addConnection(array(
    'driver'    => $database['db_type'],
    'host'      => $database['db_host'],
    'database'  => $database['db_name'],
    'username'  => $database['db_user'],
    'password'  => $database['db_pwd'],
    'charset'   => $database['db_charset'],
    'collation' => 'utf8_unicode_ci',
    'prefix'    => $database['db_prefix'],
));

// Make this Capsule instance available globally via static methods... (optional)
$capsule->setAsGlobal();

// Setup the Eloquent ORM... (optional; unless you've used setEventDispatcher())
$capsule->bootEloquent();

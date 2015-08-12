<?php

use \Framework\Queue\QueueProcessor;
use \Framework\Config;
use \Framework\Queue\Driver\Driver;
use \Framework\Queue\Driver\RedisDriver;
use \Framework\Exception\FrameworkException;
use \Framework\Input;
use \Framework\Route;

define('__APP__', __DIR__ . "/..");
require __APP__ . "/vendor/autoload.php";


$rs = Config::get('driver', new RedisDriver() );

if ( !$rs instanceof Driver )
    throw FrameworkException::internalError('Queue Driver Not Set');

QueueProcessor::getInstance()->setDriver($rs)->setAsReceiver();

for ( $i = 0; $i < 10; $i++ ) {
    $message = $rs->receiveMessage('route');
    if ( ! $message ) exit;

    Input::bind($message);
    Route::reset();
    Route::setSkipMain();
    include __APP__ . "/route.php";

}
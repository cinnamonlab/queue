<?php

set_time_limit(0);

define('__APP__', __DIR__ . "/..");
require __APP__ . "/vendor/autoload.php";

use \Framework\Redis\Redis;
use \Framework\Config;
use Framework\Queue\Driver\RedisDriver;
use Framework\Exception\FrameworkException;
use Framework\Queue\QueueProcessor;
use Framework\Input;
use Framework\Route;
use Framework\Queue\Driver\Driver;


$r = Redis::getInstance();

if ( ! isset($argv[1]) ) return;
$ip = $argv[1];
$process = $r->get('cinnamon-process-' . $ip);

if ( $process == null ) {
    Redis::set('cinnamon-process-' . $ip, date('U') );
    Redis::expire('cinnamon-process-' . $ip, 1200);

    try {
        $r->subscribe('cinnamon-process', function ($message, $channel) use ($r) {
            $tasks = $r->zrange('cinnamon-queue-' . $channel, 0, 10);
            if (count($tasks) > 0){

                $rs = Config::get('driver', new RedisDriver() );

                if ( !$rs instanceof Driver )
                    throw FrameworkException::internalError('Queue Driver Not Set');
                QueueProcessor::getInstance()->setDriver($rs)->setAsReceiver();

                for ( $i = 0; $i < 10; $i++ ) {
                    $message = $rs->receiveMessage('route');
                    if ( ! $message ) continue;

                    Input::bind($message);
                    Route::reset();
                    Route::setSkipMain();
                    include __APP__ . "/route.php";

                }
            }
        });
    } catch (Exception $e ) {
        //
    }
    $r->del('cinnamon-process-' . $ip);
}
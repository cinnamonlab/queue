<?php

set_time_limit(0);

define('__APP__', __DIR__ . "/..");
require __APP__ . "/vendor/autoload.php";

use \Framework\Redis\Redis;
use \Framework\Config;

$r = Redis::getInstance();

if ( ! isset($argv[1]) ) return;
$ip = $argv[1];
$process = $r->get('cinnamon-process-' . $ip);

if ( $process == null ) {
    try {
        $r->subscribe('cinnamon-process', function ($message, $channel) use ($r) {
            exec(Config::get('queue.php_path') . " " . __APP__ . "/commands/receive.php > /dev/null");

        });
    } catch (Exception $e ) {
        //
    }
    $r->del('cinnamon-process-' . $ip);
}
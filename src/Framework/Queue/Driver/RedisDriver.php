<?php

namespace Framework\Queue\Driver;

use Framework\Config;
use Framework\Redis\Redis;
use Exception;

class RedisDriver extends Driver
{

    function sendMessage($queue, $data, $delay = 0) {
        $data['randomize'] = md5(rand(0,1000000000).rand(0,1000000000).rand(0,1000000000));
        $score = date("U") + $delay;
        Redis::zadd('cinnamon-queue-' . $queue, $score, json_encode($data) );

        if ( Config::get('queue.auto_run', false) && defined('__APP__') ) {
            $base_path = __APP__;
            if ( ! file_exists( $base_path . "/commands/receive.php") ) {
                $base_path = __APP__ . "/vendor/cinnamonlab/queue";
            }
            $cmd = "cd $base_path && nohup " . Config::get('queue.php_path', '/usr/bin/php' )
                . " commands/receive.php > /dev/null &";

        }

    }

    function receiveMessage($queue) {
        while( true ) {
            if ( Redis::setnx('cinnamon-lock-' . $queue, 1) ) {
                Redis::expire('cinnamon-lock-' . $queue, 1);

                $return = Redis::zrange('cinnamon-queue-' . $queue, 0, 1);
                if ( isset($return[0])) {
                    $return = $return[0];
                    if ( $return ) {
                        Redis::zrem('cinnamon-queue-' . $queue, $return);
                    }
                } else $return = null;
                Redis::del('cinnamon-lock-' . $queue);
                try {
                    if ($return) return json_decode($return, true);
                } catch( Exception $e ) {
                    continue;
                }
                return null;
            }
            usleep(20000);
        }
        return null;
    }
}
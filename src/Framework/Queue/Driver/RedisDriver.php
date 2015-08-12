<?php

namespace Framework\Queue\Driver;

use Framework\Redis\Redis;
use Exception;

class RedisDriver extends Driver
{

    function sendMessage($queue, $data, $delay = 0) {
        $data['randomize'] = md5(rand(0,1000000000).rand(0,1000000000).rand(0,1000000000));
        $score = date("U") + $delay;
        Redis::zadd('cinnamon-queue-' . $queue, $score, json_encode($data) );
    }

    function receiveMessage($queue) {
        while( true ) {
            if ( Redis::setnx('cinnamon-lock-' . $queue, 1) ) {
                Redis::expire('cinnamon-lock-' . $queue, 1);

                $return = Redis::zrange('cinnamon-queue-' . $queue, 0, 1);
                if ( $return ) {
                    Redis::zrem('cinnamon-queue-' . $queue, $return);
                }
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
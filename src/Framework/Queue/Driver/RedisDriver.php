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
            if ( ! file_exists( $base_path . "/commands/subscribe.php") ) {
                $base_path = __APP__ . "/vendor/cinnamonlab/queue";
            }

            $ip = $_SERVER['SERVER_ADDR'];
            $process = Redis::get('cinnamon-process-' . $ip );
            if ( $process != null ) {
                if ( $process > date('U') - 600 ) {
                    Redis::publish('cinnamon-process', $queue );
                    return $this;
                }
                ob_start();
                system("ps ax|grep commands/subscribe.php| grep -v grep");
                $process = trim(ob_get_clean());
                if ( strlen($process) > 0 ) {
                    Redis::set('cinnamon-process-' . $ip, date('U') );
                    Redis::expire('cinnamon-process-' . $ip, 1200);
                    Redis::publish('cinnamon-process', $queue );
                    return $this;
                } else {
                    Redis::del('cinnamon-process-' . $ip);
                }
                $process = null;
            }

            $cmd = "nohup " . Config::get('queue.php_path', '/usr/bin/php' )
                . " " . $base_path . "/commands/subscribe.php $ip > /dev/null &";
            exec($cmd);

            Redis::publish('cinnamon-process', $queue );

        }
        return $this;

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
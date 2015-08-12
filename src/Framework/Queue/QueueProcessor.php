<?php

namespace Framework\Queue;


use Framework\Exception\FrameworkException;
use Framework\Input;
use Framework\Processor\Processor;
use Framework\Queue\Driver\Driver;
use Framework\Queue\Driver\RedisDriver;
use Exception;
use Framework\Log\Log;

class QueueProcessor extends Processor
{
    private $driver = null;
    public function setDriver( Driver $d ) {
        $this->driver = $d;
        return $this;
    }

    private $receiver = false;
    public function setAsReceiver( ) {
        $this->receiver = true;
        return $this;
    }

    public function setAsSender( ) {
        $this->receiver = false;
        return $this;
    }

    public function then( $func ) {
        if ( $this->receiver ) return $this->processAsReceiver( $func );
        return $this->processAsSender($func);

    }

    private function processAsReceiver( $func ) {
        try {
            if (is_callable($func)) {
                $func();
            }
            if (is_string($func)) {
                $function_array = preg_split("/@/", $func);
                if (!isset($function_array[1]))
                    throw FrameworkException::internalError('Routing Error');

                $class_name = 'App\\Controller\\' . $function_array[0];
                $method_name = $function_array[1];
                $class_name::$method_name();
            }
        } catch( Exception $e ) {
            Log::error($e->getTraceAsString());
        }
        return $this;

    }


    private $sent = false;
    private function processAsSender( $func ) {
        if ( $this->driver == null ) $this->setDriver(new RedisDriver( ) );

        if ( $this->sent) return $this;
        $this->sent = true;
        $data = Input::getAllData();

        if ( isset($data['__queue_delay'] ) )
            $delay = $data['__queue_delay'];
        else $delay = 0;

        $this->driver->sendMessage('route', $data , $delay);

        return $this;
    }

    public function setDelay( $sec ) {
        Input::set('__queue_delay', $sec);
    }

}
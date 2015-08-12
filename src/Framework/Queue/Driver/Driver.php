<?php

namespace Framework\Queue\Driver;

abstract class Driver
{
    abstract function sendMessage($queue, $data, $delay);
    abstract function receiveMessage($queue);

}
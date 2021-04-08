<?php

namespace App\Kernel;

use Predis\Client;

class Redis
{
    protected static $instance;

    public static function initClient()
    {
        if (! self::$instance) {
            self::$instance = new Client([
                'scheme' => 'tcp',
                'host'   => env('REDIS_HOST'),
                'port'   => env('REDIS_PORT'),
                'password' => env('REDIS_PASSWORD', null)
            ]);
        }
    }

    public static function client()
    {
        self::initClient();
        return self::$instance;
    }

    public static function __callStatic($name, $arguments)
    {
        self::initClient();
        return self::$instance->$name(...$arguments);
    }
}

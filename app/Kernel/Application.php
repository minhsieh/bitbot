<?php

namespace App\Kernel;

use Dotenv\Dotenv;
use Illuminate\Database\Capsule\Manager as Capsule;

class Application
{
    private static $instance;

    protected $basePath;

    public static function getInstance()
    {
        if(! self::$instance) {
            self::$instance = new Application();
        }

        return self::$instance;
    }

    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
        return $this;
    }

    public function init()
    {
        $this->initializeEnv();
        $this->initializeEloquent();
    }

    private function initializeEnv()
    {
        $dotenv = Dotenv::createImmutable($this->basePath);
        $dotenv->load();

        return $this;
    }

    private function initializeEloquent()
    {
        $capsule = new Capsule;

        $capsule->addConnection([
            "driver" => env('DB_CONNECTION'),
            "host" => env('DB_HOST'),
            "port" => env('DB_PORT'),
            "database" => env('DB_DATABASE'),
            "username" => env('DB_USERNAME'),
            "password" => env('DB_PASSWORD'),
            "unix_socket" => "",
        ]);

        //Make this Capsule instance available globally.
        $capsule->setAsGlobal();

        // Setup the Eloquent ORM.
        $capsule->bootEloquent();
    }
}

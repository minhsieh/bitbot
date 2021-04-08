<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Kernel\Application;

Application::getInstance()->setBasePath(dirname(__DIR__));
Application::getInstance()->init();

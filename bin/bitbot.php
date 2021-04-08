<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Kernel\Application;

Application::getInstance()->setBasePath(dirname(__DIR__));
Application::getInstance()->init();

$daemon = new \App\Kernel\Daemon();

$daemon->setBasePath(__DIR__);
$daemon->setProcessNumber(2);
$daemon->setHandler(function ($pno) {
    switch ($pno) {
        case 1:
            $wsClient = new \App\Processes\WebsocketClient;
            $wsClient->run();
            break;
        case 2:
            $accWsClient = new \App\Processes\AccountWebsocketClient;
            $accWsClient->run();
            break;
    }
});

$daemon->run();

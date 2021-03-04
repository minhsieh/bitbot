<?php

require __DIR__ . '/../vendor/autoload.php';

$daemon = new \App\Kernel\Daemon();

$daemon->setBasePath(__DIR__);
$daemon->setProcessNumber(3);
$daemon->setHandler(function ($pno) {
    echo "$pno is up!!!!" . PHP_EOL;

    while (true) {
        echo "$pno tick." . PHP_EOL;
        sleep(2);
    }
});

$daemon->run();

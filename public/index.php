<?php

require "../autoload.php";

$klein = new \Klein\Klein();

$klein->respond('GET', '/test', function () {
    return 'Hello World!';
});



$klein->dispatch();
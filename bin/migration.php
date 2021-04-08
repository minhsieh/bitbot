<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Kernel\Application;

Application::getInstance()->setBasePath(dirname(__DIR__));
Application::getInstance()->init();

use Illuminate\Database\Capsule\Manager as Capsule;

if (! Capsule::schema()->hasTable('todos')) {
    Capsule::schema()->create('todos', function ($table) {
        $table->increments('id');
        $table->string('todo');
        $table->string('description');
        $table->string('category');
        $table->integer('user_id')->unsigned();
        $table->timestamps();
    });
}

if (! Capsule::schema()->hasTable('users')) {
    Capsule::schema()->create('users', function ($table) {
        $table->increments('id');
        $table->string('name');
        $table->string('email')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });
}

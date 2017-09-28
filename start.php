<?php
require dirname(__FILE__).'/Tool/Email.php';
require 'vendor/autoload.php';
require 'Task.php';
require 'Worker.php';

$task = [
    [
        'from' => '北京',
        'to' => '成都',
        'date' => '2017-09-30',
        'houxuancheci' => 'K1363,Z49',
        'zuoweiLeixin' => '硬卧'
    ],
    [
        'from' => '北京',
        'to' => '成都',
        'date' => '2017-09-30',
        'houxuancheci' => 'K1363,Z49',
        'zuoweiLeixin' => '硬卧'
    ]
];

$config = [
    'notifyEmail' => ['test@gmail.com'],
    'notifyTel' => ['11111111111'],
    'debug' => FALSE 
];

$app = new Worker($config,$task);
$app->run();

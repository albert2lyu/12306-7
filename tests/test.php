<?php
chdir("/vagrant/data/huoche");
// echo getcwd()."\n";
require './Tool/Email.php';
require './vendor/autoload.php';
require './Task.php';
require './Worker.php';

$task = [
    [
        'from' => '天津',
        'to' => '南昌',
        'date' => '2017-09-27',
        'houxuancheci' => 'Z188,K568',
        'zuoweiLeixin' => '硬卧'
    ]
];

$config = [
    'notifyEmail' => ['764436364@qq.com'],
    'notifyTel' => ['13132530501'],
    'debug' => TRUE 
];

$app = new Worker($config,$task);
$app->run();

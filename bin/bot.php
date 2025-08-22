<?php
require __DIR__ . '/../vendor/autoload.php';

use App\QQBot\{Client, Http, Logger};
use App\Handlers\{C2CMessageHandler, GroupAtMessageHandler};
use React\EventLoop\Factory;

date_default_timezone_set('Asia/Shanghai'); // 设置为上海时区
$config = require __DIR__ . '/../config/config.php';
$logger = new Logger($config['log_level']);
$http   = new Http($config, $logger);
$loop   = Factory::create();

$client = new Client($config, $logger, $http, $loop);
$client->addHandler(new C2CMessageHandler());
$client->addHandler(new GroupAtMessageHandler());

// 添加信号处理器，优雅关闭
$loop->addSignal(SIGINT, function () use ($loop, $client) {
    $logger->info('收到中断信号，正在关闭...');
    $loop->stop();
});

$client->run();
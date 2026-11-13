#!/usr/bin/env php
<?php
/**
 * 实时推送服务器（WebSocket）
 *
 * 用法: php bin/realtime_server.php start [-d]
 *       php bin/realtime_server.php stop
 *       php bin/realtime_server.php restart
 */

require_once __DIR__ . '/../vendor/autoload.php';

use GatewayWorker\BusinessWorker;
use GatewayWorker\Gateway;
use GatewayWorker\Lib\Gateway as GatewayLib;
use Workerman\Worker;
use extend\realtime\RealtimeEventHandler;
use extend\realtime\RealtimeService;

define('APP_PATH', __DIR__ . '/../app/');

// 加载 .env 文件，使 config/security.php 中的 env() 函数可用
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) return $default;
        return match (strtolower((string)$value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

$host = getenv('REALTIME_HOST') ?: '0.0.0.0';
$port = (int) (getenv('REALTIME_PORT') ?: 8002);
$workerCount = (int) (getenv('REALTIME_WORKER_NUM') ?: 4);

// 注册事件处理类，供 GatewayWorker 回调
require_once __DIR__ . '/../extend/realtime/RealtimeEventHandler.php';

$gateway = new Gateway("ws://{$host}:{$port}");
$gateway->name = 'youlai-realtime';
$gateway->count = 1;
$gateway->registerAddress = '127.0.0.1:1238';
$gateway->pingInterval = 25;
$gateway->pingNotResponseLimit = 2;

$worker = new BusinessWorker();
$worker->name = 'youlai-realtime-business';
$worker->count = $workerCount;
$worker->registerAddress = '127.0.0.1:1238';
$worker->eventHandler = \extend\realtime\RealtimeEventHandler::class;
$worker->onWorkerStart = function ($worker) {
    \Workerman\Timer::add(25, function () {
        RealtimeService::getInstance()->sendHeartbeat();
    });
};

if (DIRECTORY_SEPARATOR === '\\') {
    $worker->count = 1;
}

Worker::runAll();
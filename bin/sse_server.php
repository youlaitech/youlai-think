#!/usr/bin/env php
<?php
/**
 * SSE 服务器
 *
 * 用法: php bin/sse_server.php start [-d]
 *       php bin/sse_server.php stop
 *       php bin/sse_server.php restart
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Workerman\Worker;
use extend\sse\SseWorkerServer;

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

$sseHost = getenv('SSE_HOST') ?: '0.0.0.0';
$ssePort = (int)(getenv('SSE_PORT') ?: 8001);
$sseWorkerCount = (int)(getenv('SSE_WORKER_NUM') ?: 4);

$sseWorker = new SseWorkerServer("http://{$sseHost}:{$ssePort}");
$sseWorker->count = $sseWorkerCount;
$sseWorker->name = 'youlai-sse';

if (DIRECTORY_SEPARATOR === '\\') {
    $sseWorker->count = 1;
}

Worker::runAll();

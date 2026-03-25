<?php declare(strict_types=1);

namespace app\common\middleware;

use app\system\annotation\Log;
use app\system\model\Log as LogModel;
use think\Response;
use ReflectionMethod;
use Throwable;

final class LogMiddleware
{
    public function handle($request, \Closure $next): Response
    {
        $startTime = microtime(true);
        $exception = null;
        $response = null;

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            $exception = $e;
            throw $e;
        } finally {
            try {
                $controller = $request->controller();
                $action = $request->action();

                if (empty($controller) || empty($action)) {
                    return $response instanceof Response ? $response : response($response);
                }

                $controllerClass = app()->parseClass('controller', $controller);
                if (!class_exists($controllerClass)) {
                    return $response instanceof Response ? $response : response($response);
                }

                $method = new ReflectionMethod($controllerClass, $action);
                $attributes = $method->getAttributes(Log::class);

                if (empty($attributes)) {
                    return $response instanceof Response ? $response : response($response);
                }

                $logAnnotation = $attributes[0]->newInstance();
                $module = $logAnnotation->module;
                $actionType = $logAnnotation->actionType;

                // 构建标题
                $title = $logAnnotation->title;
                if (empty($title)) {
                    $title = $module->description() . '-' . $actionType->description();
                }
                $content = $logAnnotation->content;

                $executionTime = (int) round((microtime(true) - $startTime) * 1000);

                $authUser = (array) ($request->__authUser ?? []);
                $operatorId = (int) ($authUser['id'] ?? $authUser['userId'] ?? 0);
                $operatorName = $authUser['nickname'] ?? $authUser['username'] ?? '';

                $userAgent = $request->header('user-agent', '');
                $ua = $this->parseUserAgent((string) $userAgent);

                LogModel::create([
                    'module'         => $module->value,
                    'action_type'    => $actionType->value,
                    'title'          => $title,
                    'content'        => $content,
                    'operator_id'    => $operatorId,
                    'operator_name'  => $operatorName,
                    'request_uri'    => $request->pathinfo(),
                    'request_method' => $request->method(),
                    'ip'             => $request->ip(),
                    'browser'        => $ua['browser'],
                    'os'             => $ua['os'],
                    'status'         => $exception === null ? 1 : 0,
                    'error_msg'      => $exception?->getMessage(),
                    'execution_time' => $executionTime,
                    'create_time'    => date('Y-m-d H:i:s'),
                ]);
            } catch (Throwable) {
                // 日志记录失败不影响主请求
            }
        }

        return $response instanceof Response ? $response : response($response);
    }

    private function parseUserAgent(string $userAgent): array
    {
        $os = '';
        $browser = '';

        if (empty($userAgent)) {
            return ['os' => '', 'browser' => ''];
        }

        // OS 检测
        if (str_contains($userAgent, 'Windows NT 10')) {
            $os = 'Windows 10';
        } elseif (str_contains($userAgent, 'Windows NT 6.3')) {
            $os = 'Windows 8.1';
        } elseif (str_contains($userAgent, 'Windows NT 6.1')) {
            $os = 'Windows 7';
        } elseif (str_contains($userAgent, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($userAgent, 'Mac OS X')) {
            if (preg_match('/Mac OS X ([\d_]+)/', $userAgent, $matches)) {
                $os = 'macOS ' . str_replace('_', '.', $matches[1]);
            } else {
                $os = 'macOS';
            }
        } elseif (str_contains($userAgent, 'Android')) {
            if (preg_match('/Android ([\d.]+)/', $userAgent, $matches)) {
                $os = 'Android ' . $matches[1];
            } else {
                $os = 'Android';
            }
        } elseif (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad')) {
            if (preg_match('/OS ([\d_]+)/', $userAgent, $matches)) {
                $os = 'iOS ' . str_replace('_', '.', $matches[1]);
            } else {
                $os = 'iOS';
            }
        } elseif (str_contains($userAgent, 'Linux')) {
            $os = 'Linux';
        }

        // 浏览器检测（顺序重要 - Edge 在 Chrome 之前检测）
        if (str_contains($userAgent, 'Edg/')) {
            if (preg_match('/Edg\/([\d.]+)/', $userAgent, $matches)) {
                $browser = 'Edge ' . $matches[1];
            } else {
                $browser = 'Edge';
            }
        } elseif (str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera/')) {
            if (preg_match('/(?:OPR|Opera)\/([\d.]+)/', $userAgent, $matches)) {
                $browser = 'Opera ' . $matches[1];
            } else {
                $browser = 'Opera';
            }
        } elseif (str_contains($userAgent, 'Firefox/')) {
            if (preg_match('/Firefox\/([\d.]+)/', $userAgent, $matches)) {
                $browser = 'Firefox ' . $matches[1];
            } else {
                $browser = 'Firefox';
            }
        } elseif (str_contains($userAgent, 'Chrome/') && !str_contains($userAgent, 'Edg/')) {
            if (preg_match('/Chrome\/([\d.]+)/', $userAgent, $matches)) {
                $browser = 'Chrome ' . $matches[1];
            } else {
                $browser = 'Chrome';
            }
        } elseif (str_contains($userAgent, 'Safari/') && !str_contains($userAgent, 'Chrome')) {
            if (preg_match('/Version\/([\d.]+)/', $userAgent, $matches)) {
                $browser = 'Safari ' . $matches[1];
            } else {
                $browser = 'Safari';
            }
        } elseif (str_contains($userAgent, 'MSIE') || str_contains($userAgent, 'Trident/')) {
            $browser = 'IE';
        }

        return ['os' => $os, 'browser' => $browser];
    }
}

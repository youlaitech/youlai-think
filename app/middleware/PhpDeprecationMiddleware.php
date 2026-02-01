<?php

declare(strict_types=1);

namespace app\middleware;

final class PhpDeprecationMiddleware
{
    public function handle($request, \Closure $next)
    {
        // 屏蔽弃用警告，避免污染日志
        error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

        $previous = set_error_handler(static function (
            int $severity,
            string $message,
            string $file = '',
            int $line = 0,
        ) use (&$previous): bool {
            if ($severity === E_DEPRECATED || $severity === E_USER_DEPRECATED) {
                return true;
            }

            if (is_callable($previous)) {
                return (bool) $previous($severity, $message, $file, $line);
            }

            return false;
        });

        try {
            return $next($request);
        } finally {
            restore_error_handler();
        }
    }
}

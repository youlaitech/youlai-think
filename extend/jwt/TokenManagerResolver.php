<?php declare(strict_types=1);

namespace extend\jwt;

/**
 * TokenManager 单例解析器
 * 统一管理 JwtTokenManager 实例
 */
class TokenManagerResolver
{
    private static ?TokenManager $instance = null;

    public static function resolve(): TokenManager
    {
        if (self::$instance === null) {
            self::$instance = new JwtTokenManager();
        }
        return self::$instance;
    }

    public static function setInstance(TokenManager $manager): void
    {
        self::$instance = $manager;
    }
}

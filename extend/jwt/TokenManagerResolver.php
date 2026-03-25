<?php declare(strict_types=1);

namespace extend\jwt;

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

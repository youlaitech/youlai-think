<?php

declare(strict_types=1);

namespace app\common\security;

use app\common\exception\BusinessException;
use app\common\redis\RedisClient;
use app\common\redis\RedisKey;
use app\common\web\ResultCode;

final class RedisTokenManager implements TokenManager
{
    public function generateToken(array $userAuthInfo): AuthenticationToken
    {
        $cfg = config('security');
        $redisCfg = $cfg['redis'] ?? [];
        $keys = $redisCfg['keys'] ?? [];
        $redis = RedisClient::get();

        $accessTtl = (int) ($redisCfg['access_ttl'] ?? 7200);
        $refreshTtl = (int) ($redisCfg['refresh_ttl'] ?? 604800);

        $userId = (int) ($userAuthInfo['userId'] ?? 0);
        if ($userId <= 0) {
            throw new BusinessException(ResultCode::SYSTEM_ERROR, 'Invalid userId');
        }

        // 读取旧 token，避免并发登录残留
        $oldAccess = $redis->get(RedisKey::format((string) ($keys['user_access_token'] ?? 'auth:user:access:{}'), $userId));
        $oldRefresh = $redis->get(RedisKey::format((string) ($keys['user_refresh_token'] ?? 'auth:user:refresh:{}'), $userId));

        if (!empty($oldAccess)) {
            $redis->del([
                RedisKey::format((string) ($keys['access_token_user'] ?? 'auth:token:access:{}'), (string) $oldAccess),
            ]);
        }

        if (!empty($oldRefresh)) {
            $redis->del([
                RedisKey::format((string) ($keys['refresh_token_user'] ?? 'auth:token:refresh:{}'), (string) $oldRefresh),
            ]);
        }

        // 使用随机字符串作为 token
        $accessToken = bin2hex(random_bytes(16));
        $refreshToken = bin2hex(random_bytes(16));

        $userJson = json_encode($userAuthInfo, JSON_UNESCAPED_UNICODE);

        $accessUserKey = RedisKey::format((string) ($keys['access_token_user'] ?? 'auth:token:access:{}'), $accessToken);
        $refreshUserKey = RedisKey::format((string) ($keys['refresh_token_user'] ?? 'auth:token:refresh:{}'), $refreshToken);

        // token 与用户信息双向映射
        $redis->setex($accessUserKey, $accessTtl, $userJson);
        $redis->setex($refreshUserKey, $refreshTtl, $userJson);

        $redis->setex(RedisKey::format((string) ($keys['user_access_token'] ?? 'auth:user:access:{}'), $userId), $accessTtl, $accessToken);
        $redis->setex(RedisKey::format((string) ($keys['user_refresh_token'] ?? 'auth:user:refresh:{}'), $userId), $refreshTtl, $refreshToken);

        return new AuthenticationToken(
            (string) ($cfg['token_type'] ?? 'Bearer'),
            $accessToken,
            $refreshToken,
            $accessTtl,
        );
    }

    public function parseAccessToken(string $accessToken): array
    {
        $cfg = config('security');
        $keys = $cfg['redis']['keys'] ?? [];
        $redis = RedisClient::get();

        $accessUserKey = RedisKey::format((string) ($keys['access_token_user'] ?? 'auth:token:access:{}'), $accessToken);
        $json = $redis->get($accessUserKey);

        if (empty($json)) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        $user = json_decode((string) $json, true);
        if (!is_array($user) || empty($user['userId'])) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        $user['accessToken'] = $accessToken;

        return $user;
    }

    public function refreshToken(string $refreshToken): AuthenticationToken
    {
        $cfg = config('security');
        $keys = $cfg['redis']['keys'] ?? [];
        $redis = RedisClient::get();

        $refreshUserKey = RedisKey::format((string) ($keys['refresh_token_user'] ?? 'auth:token:refresh:{}'), $refreshToken);
        $json = $redis->get($refreshUserKey);

        if (empty($json)) {
            throw new BusinessException(ResultCode::REFRESH_TOKEN_INVALID);
        }

        $user = json_decode((string) $json, true);
        if (!is_array($user) || empty($user['userId'])) {
            throw new BusinessException(ResultCode::REFRESH_TOKEN_INVALID);
        }

        return $this->generateToken($user);
    }

    public function invalidate(?string $accessToken, ?string $refreshToken): void
    {
        $cfg = config('security');
        $keys = $cfg['redis']['keys'] ?? [];
        $redis = RedisClient::get();

        $userId = null;
        if (!empty($accessToken)) {
            try {
                $user = $this->parseAccessToken($accessToken);
                $userId = (int) ($user['userId'] ?? 0);
            } catch (\Throwable) {
            }
        }

        // 清理用户维度 token
        if ($userId !== null && $userId > 0) {
            $redis->del([
                RedisKey::format((string) ($keys['user_access_token'] ?? 'auth:user:access:{}'), $userId),
                RedisKey::format((string) ($keys['user_refresh_token'] ?? 'auth:user:refresh:{}'), $userId),
            ]);
        }

        if (!empty($accessToken)) {
            $redis->del([
                RedisKey::format((string) ($keys['access_token_user'] ?? 'auth:token:access:{}'), $accessToken),
            ]);
        }

        if (!empty($refreshToken)) {
            $redis->del([
                RedisKey::format((string) ($keys['refresh_token_user'] ?? 'auth:token:refresh:{}'), $refreshToken),
            ]);
        }
    }
}

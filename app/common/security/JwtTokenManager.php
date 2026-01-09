<?php

declare(strict_types=1);

namespace app\common\security;

use app\common\exception\BusinessException;
use app\common\redis\RedisClient;
use app\common\redis\RedisKey;
use app\common\web\ResultCode;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Db;

final class JwtTokenManager implements TokenManager
{
    private const ALG = 'HS256';

    public function generateToken(array $userAuthInfo): AuthenticationToken
    {
        $cfg = config('security');
        $jwtCfg = $cfg['jwt'] ?? [];

        $now = time();
        $accessTtl = (int) ($jwtCfg['access_ttl'] ?? 7200);
        $refreshTtl = (int) ($jwtCfg['refresh_ttl'] ?? 604800);

        $userId = (int) ($userAuthInfo['userId'] ?? 0);
        if ($userId <= 0) {
            throw new BusinessException(ResultCode::SYSTEM_ERROR, 'Invalid userId');
        }

        $redis = RedisClient::get();
        $keys = $cfg['redis']['keys'] ?? [];

        $securityVersionKey = RedisKey::format((string) ($keys['user_security_version'] ?? 'auth:user:security_version:{}'), $userId);
        $securityVersion = (int) ($redis->get($securityVersionKey) ?: 1);
        if ($securityVersion <= 0) {
            $securityVersion = 1;
        }
        $redis->set($securityVersionKey, (string) $securityVersion);

        $secret = (string) ($jwtCfg['secret'] ?? 'change-me');
        $issuer = (string) ($jwtCfg['issuer'] ?? 'youlai-think');

        $accessPayload = [
            'iss' => $issuer,
            'iat' => $now,
            'exp' => $now + $accessTtl,
            'jti' => bin2hex(random_bytes(16)),
            JwtClaimConstants::TOKEN_TYPE => 'access',
            JwtClaimConstants::USER_ID => $userId,
            JwtClaimConstants::DEPT_ID => $userAuthInfo['deptId'] ?? null,
            JwtClaimConstants::DATA_SCOPE => $userAuthInfo['dataScope'] ?? null,
            JwtClaimConstants::AUTHORITIES => $userAuthInfo['authorities'] ?? [],
            JwtClaimConstants::SECURITY_VERSION => $securityVersion,
        ];

        $refreshPayload = [
            'iss' => $issuer,
            'iat' => $now,
            'exp' => $now + $refreshTtl,
            'jti' => bin2hex(random_bytes(16)),
            JwtClaimConstants::TOKEN_TYPE => 'refresh',
            JwtClaimConstants::USER_ID => $userId,
            JwtClaimConstants::SECURITY_VERSION => $securityVersion,
        ];

        $accessToken = JWT::encode($accessPayload, $secret, self::ALG);
        $refreshToken = JWT::encode($refreshPayload, $secret, self::ALG);

        $userAccessKey = RedisKey::format((string) ($keys['user_access_token'] ?? 'auth:user:access:{}'), $userId);
        $userRefreshKey = RedisKey::format((string) ($keys['user_refresh_token'] ?? 'auth:user:refresh:{}'), $userId);
        $oldAccess = $redis->get($userAccessKey);
        $oldRefresh = $redis->get($userRefreshKey);

        $redis->setex($userAccessKey, $accessTtl, $accessToken);
        $redis->setex($userRefreshKey, $refreshTtl, $refreshToken);

        if (!empty($oldAccess)) {
            $this->blacklistToken((string) $oldAccess, $accessTtl);
        }
        if (!empty($oldRefresh)) {
            $this->blacklistToken((string) $oldRefresh, $refreshTtl);
        }

        return new AuthenticationToken(
            (string) ($cfg['token_type'] ?? 'Bearer'),
            $accessToken,
            $refreshToken,
            $accessTtl,
        );
    }

    public function parseAccessToken(string $accessToken): array
    {
        $claims = $this->decodeAndValidate($accessToken);

        if (($claims[JwtClaimConstants::TOKEN_TYPE] ?? '') !== 'access') {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        return [
            'userId' => (int) ($claims[JwtClaimConstants::USER_ID] ?? 0),
            'deptId' => $claims[JwtClaimConstants::DEPT_ID] ?? null,
            'dataScope' => $claims[JwtClaimConstants::DATA_SCOPE] ?? null,
            'authorities' => $claims[JwtClaimConstants::AUTHORITIES] ?? [],
            'accessToken' => $accessToken,
        ];
    }

    public function refreshToken(string $refreshToken): AuthenticationToken
    {
        $claims = $this->decodeAndValidate($refreshToken);

        if (($claims[JwtClaimConstants::TOKEN_TYPE] ?? '') !== 'refresh') {
            throw new BusinessException(ResultCode::REFRESH_TOKEN_INVALID);
        }

        $userId = (int) ($claims[JwtClaimConstants::USER_ID] ?? 0);
        if ($userId <= 0) {
            throw new BusinessException(ResultCode::REFRESH_TOKEN_INVALID);
        }

        $user = Db::name('sys_user')
            ->where('id', $userId)
            ->where('is_deleted', 0)
            ->field('id,dept_id')
            ->find();

        if (!$user) {
            throw new BusinessException(ResultCode::REFRESH_TOKEN_INVALID);
        }

        $userAuthInfo = [
            'userId' => $userId,
            'deptId' => $user['dept_id'] ?? null,
            'dataScope' => null,
            'authorities' => [],
        ];

        return $this->generateToken($userAuthInfo);
    }

    public function invalidate(?string $accessToken, ?string $refreshToken): void
    {
        $cfg = config('security');
        $keys = $cfg['redis']['keys'] ?? [];
        $redis = RedisClient::get();

        $userId = null;
        if (!empty($accessToken)) {
            try {
                $claims = $this->decodeAndValidate($accessToken, false);
                $userId = (int) ($claims[JwtClaimConstants::USER_ID] ?? 0);
            } catch (\Throwable) {
            }
        }

        if ($userId !== null && $userId > 0) {
            $securityVersionKey = RedisKey::format((string) ($keys['user_security_version'] ?? 'auth:user:security_version:{}'), $userId);
            $redis->incr($securityVersionKey);
        }

        if (!empty($accessToken)) {
            $this->blacklistToken($accessToken, 86400);
        }

        if (!empty($refreshToken)) {
            $this->blacklistToken($refreshToken, 86400);
        }
    }

    private function decodeAndValidate(string $token, bool $checkBlacklist = true): array
    {
        if ($checkBlacklist && $this->isBlacklisted($token)) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        $cfg = config('security');
        $jwtCfg = $cfg['jwt'] ?? [];
        $secret = (string) ($jwtCfg['secret'] ?? 'change-me');

        try {
            $decoded = JWT::decode($token, new Key($secret, self::ALG));
        } catch (\Throwable) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        $claims = json_decode(json_encode($decoded, JSON_UNESCAPED_UNICODE), true);
        if (!is_array($claims)) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        $userId = (int) ($claims[JwtClaimConstants::USER_ID] ?? 0);
        $securityVersion = (int) ($claims[JwtClaimConstants::SECURITY_VERSION] ?? 0);

        if ($userId <= 0 || $securityVersion <= 0) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        $keys = $cfg['redis']['keys'] ?? [];
        $redis = RedisClient::get();
        $securityVersionKey = RedisKey::format((string) ($keys['user_security_version'] ?? 'auth:user:security_version:{}'), $userId);
        $current = (int) ($redis->get($securityVersionKey) ?: 1);

        if ($current !== $securityVersion) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        return $claims;
    }

    private function isBlacklisted(string $token): bool
    {
        $cfg = config('security');
        $keys = $cfg['redis']['keys'] ?? [];
        $redis = RedisClient::get();
        $k = RedisKey::format((string) ($keys['blacklist_token'] ?? 'auth:token:blacklist:{}'), $token);
        return $redis->exists($k) > 0;
    }

    private function blacklistToken(string $token, int $ttl): void
    {
        $cfg = config('security');
        $keys = $cfg['redis']['keys'] ?? [];
        $redis = RedisClient::get();

        $k = RedisKey::format((string) ($keys['blacklist_token'] ?? 'auth:token:blacklist:{}'), $token);
        $redis->setex($k, max(60, $ttl), '1');
    }
}

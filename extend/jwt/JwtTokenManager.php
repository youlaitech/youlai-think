<?php declare(strict_types=1);

namespace extend\jwt;

use app\common\exception\BusinessException;
use app\common\web\ResultCode;
use extend\redis\RedisClient;
use extend\redis\KeyFormatter;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtTokenManager implements TokenManager
{
    private readonly string $secret;
    private readonly string $issuer;
    private readonly int $accessTokenTtl;
    private readonly int $refreshTokenTtl;

    public function __construct()
    {
        $cfg = config('security') ?? [];
        $jwtCfg = $cfg['jwt'] ?? [];
        $secret = (string) ($jwtCfg['secret'] ?? '');
        $issuer = (string) ($jwtCfg['issuer'] ?? '');
        if ($secret === '' || $issuer === '') {
            throw new BusinessException(ResultCode::SYSTEM_ERROR, 'JWT secret/issuer 未配置，请检查 config/security.php 和 .env');
        }
        $this->secret = $secret;
        $this->issuer = $issuer;
        $this->accessTokenTtl = (int) ($jwtCfg['access_token_ttl'] ?? 7200);
        $this->refreshTokenTtl = (int) ($jwtCfg['refresh_token_ttl'] ?? 604800);
    }

    /**
     * 生成 Token（兼容旧接口）
     *
     * @param array $userAuthInfo ['userId' => int, 'deptId' => int|null]
     */
    public function generateToken(array $userAuthInfo): object
    {
        $userId = (int) ($userAuthInfo['userId'] ?? 0);
        $token = $this->generateTokenPair($userId, $userAuthInfo);
        return (object) [
            'accessToken' => $token->accessToken,
            'refreshToken' => $token->refreshToken,
            'tokenType' => 'Bearer',
            'expiresIn' => $token->accessTokenTtl,
        ];
    }

    public function generateTokenPair(int $userId, array $extra = []): AuthenticationToken
    {
        $now = time();

        // 获取用户的 Token 版本号，用于会话失效控制
        $tokenVersionKey = KeyFormatter::format((string) SecurityConstants::REDIS_USER_TOKEN_VERSION, $userId);
        $redis = RedisClient::get();
        $currentVersion = (int) ($redis->get($tokenVersionKey) ?: 0);

        $payload = array_merge([
            'iss' => $this->issuer,
            'iat' => $now,
            JwtClaimConstants::USER_ID => $userId,
            JwtClaimConstants::TOKEN_VERSION => $currentVersion,
        ], $extra);

        $accessToken = JWT::encode(array_merge($payload, ['exp' => $now + $this->accessTokenTtl, 'type' => 'access']), $this->secret, 'HS256');
        $refreshToken = JWT::encode(array_merge($payload, ['exp' => $now + $this->refreshTokenTtl, 'type' => 'refresh']), $this->secret, 'HS256');

        return new AuthenticationToken($accessToken, $refreshToken, $this->accessTokenTtl, $this->refreshTokenTtl);
    }

    public function validateAccessToken(string $token): array
    {
        return $this->validateToken($token, 'access');
    }

    public function parseAccessToken(string $token): array
    {
        return $this->validateToken($token, 'access');
    }

    public function validateRefreshToken(string $token): array
    {
        return $this->validateToken($token, 'refresh');
    }

    private function validateToken(string $token, string $expectedType): array
    {
        // 根据 token 类型决定失败时的错误码
        $invalidCode = $expectedType === 'refresh'
            ? ResultCode::REFRESH_TOKEN_INVALID
            : ResultCode::ACCESS_TOKEN_INVALID;

        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $data = (array) json_decode(json_encode($decoded), true);

            if (($data['type'] ?? '') !== $expectedType) {
                throw new BusinessException($invalidCode, 'Token 类型不匹配');
            }

            // Token 版本号用于强制失效历史 token
            $tokenVersionKey = KeyFormatter::format((string) (SecurityConstants::REDIS_USER_TOKEN_VERSION), $data[JwtClaimConstants::USER_ID] ?? 0);
            $redis = RedisClient::get();
            $currentVersion = (int) ($redis->get($tokenVersionKey) ?: 0);
            $tokenVersion = (int) ($data[JwtClaimConstants::TOKEN_VERSION] ?? 0);
            if ($currentVersion > 0 && $tokenVersion < $currentVersion) {
                throw new BusinessException($invalidCode, 'Token 已失效，请重新登录');
            }

            $k = KeyFormatter::format((string) (SecurityConstants::REDIS_BLACKLIST_TOKEN), $token);
            if ($redis->exists($k) > 0) {
                throw new BusinessException($invalidCode, 'Token 已失效');
            }

            return $data;
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new BusinessException($invalidCode, 'Token 无效或已过期');
        }
    }

    public function blacklist(string $token): void
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, 'HS256'));
            $data = (array) json_decode(json_encode($decoded), true);
            $exp = (int) ($data['exp'] ?? 0);
            $ttl = max($exp - time(), 0);

            if ($ttl > 0) {
                $k = KeyFormatter::format((string) (SecurityConstants::REDIS_BLACKLIST_TOKEN), $token);
                RedisClient::get()->setex($k, $ttl, '1');
            }
        } catch (\Throwable) {}
    }

    /**
     * 登出（将 accessToken 和 refreshToken 加入黑名单）
     */
    public function invalidate(?string $accessToken, ?string $refreshToken): void
    {
        if ($accessToken !== null && $accessToken !== '') {
            $this->blacklist($accessToken);
        }
        if ($refreshToken !== null && $refreshToken !== '') {
            $this->blacklist($refreshToken);
        }
    }

    /**
     * 刷新 Token（兼容旧接口）
     */
    public function refreshToken(string $refreshToken): object
    {
        $data = $this->validateRefreshToken($refreshToken);
        $userId = (int) ($data[JwtClaimConstants::USER_ID] ?? 0);
        $this->blacklist($refreshToken);
        $token = $this->generateTokenPair($userId);
        return (object) [
            'accessToken' => $token->accessToken,
            'refreshToken' => $token->refreshToken,
            'tokenType' => 'Bearer',
            'expiresIn' => $token->accessTokenTtl,
        ];
    }

    public function revokeUserTokens(int $userId): void
    {
        $redis = RedisClient::get();
        $keys = config('security.redis.keys') ?? [];
        $tokenVersionKey = KeyFormatter::format((string) ($keys['user_token_version'] ?? SecurityConstants::REDIS_USER_TOKEN_VERSION), $userId);
        $redis->incr($tokenVersionKey);
        $redis->expire($tokenVersionKey, $this->refreshTokenTtl + 3600);
    }

    public function storeRefreshToken(int $userId, string $refreshToken): void
    {
        $redis = RedisClient::get();
        $keys = config('security.redis.keys') ?? [];
        $userAccessKey = KeyFormatter::format((string) ($keys['user_access_token'] ?? SecurityConstants::REDIS_USER_ACCESS_TOKEN), $userId);
        $userRefreshKey = KeyFormatter::format((string) ($keys['user_refresh_token'] ?? SecurityConstants::REDIS_USER_REFRESH_TOKEN), $userId);

        $redis->setex($userRefreshKey, $this->refreshTokenTtl, $refreshToken);
        $redis->del($userAccessKey);
    }

    public function rotateRefreshToken(int $userId, string $oldRefreshToken): AuthenticationToken
    {
        $data = $this->validateRefreshToken($oldRefreshToken);
        $userId = (int) ($data[JwtClaimConstants::USER_ID] ?? 0);
        $this->blacklist($oldRefreshToken);
        return $this->generateTokenPair($userId);
    }
}

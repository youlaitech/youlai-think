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

        // Token 版本号用于强制失效历史 token
        $tokenVersionKey = RedisKey::format((string) ($keys['user_token_version'] ?? 'auth:user:token_version:{}'), $userId);
        $tokenVersion = (int) ($redis->get($tokenVersionKey) ?: 0);
        $redis->set($tokenVersionKey, (string) $tokenVersion);

        $secret = (string) ($jwtCfg['secret'] ?? 'change-me');
        $issuer = (string) ($jwtCfg['issuer'] ?? 'youlai-think');

        // 如果没有传入 dataScopes，则从数据库查询
        $dataScopes = $userAuthInfo['dataScopes'] ?? [];
        $authorities = $userAuthInfo['authorities'] ?? [];
        if (empty($dataScopes) || empty($authorities)) {
            $dataScopes = $this->buildDataScopes($userId);
            $authorities = $this->buildAuthorities($userId);
        }

        // 序列化 dataScopes 为 JSON 字符串
        $dataScopesJson = is_array($dataScopes) ? json_encode($dataScopes, JSON_UNESCAPED_UNICODE) : $dataScopes;

        // access token 载荷
        $accessPayload = [
            'iss' => $issuer,
            'iat' => $now,
            'exp' => $now + $accessTtl,
            'jti' => bin2hex(random_bytes(16)),
            JwtClaimConstants::TOKEN_TYPE => 'access',
            JwtClaimConstants::USER_ID => $userId,
            JwtClaimConstants::DEPT_ID => $userAuthInfo['deptId'] ?? null,
            JwtClaimConstants::DATA_SCOPES => $dataScopesJson,
            JwtClaimConstants::AUTHORITIES => $authorities,
            JwtClaimConstants::TOKEN_VERSION => $tokenVersion,
        ];

        // refresh token 载荷
        $refreshPayload = [
            'iss' => $issuer,
            'iat' => $now,
            'exp' => $now + $refreshTtl,
            'jti' => bin2hex(random_bytes(16)),
            JwtClaimConstants::TOKEN_TYPE => 'refresh',
            JwtClaimConstants::USER_ID => $userId,
            JwtClaimConstants::TOKEN_VERSION => $tokenVersion,
        ];

        $accessToken = JWT::encode($accessPayload, $secret, self::ALG);
        $refreshToken = JWT::encode($refreshPayload, $secret, self::ALG);

        $userAccessKey = RedisKey::format((string) ($keys['user_access_token'] ?? 'auth:user:access:{}'), $userId);
        $userRefreshKey = RedisKey::format((string) ($keys['user_refresh_token'] ?? 'auth:user:refresh:{}'), $userId);
        $oldAccess = $redis->get($userAccessKey);
        $oldRefresh = $redis->get($userRefreshKey);

        // 记录当前用户最新 token
        $redis->setex($userAccessKey, $accessTtl, $accessToken);
        $redis->setex($userRefreshKey, $refreshTtl, $refreshToken);

        // 旧 token 进入黑名单，防止并发登录复用
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

        // 解析 dataScopes
        $dataScopes = [];
        $dataScopesRaw = $claims[JwtClaimConstants::DATA_SCOPES] ?? null;
        if (is_string($dataScopesRaw)) {
            $decoded = json_decode($dataScopesRaw, true);
            if (is_array($decoded)) {
                $dataScopes = $decoded;
            }
        } elseif (is_array($dataScopesRaw)) {
            $dataScopes = $dataScopesRaw;
        }

        return [
            'userId' => (int) ($claims[JwtClaimConstants::USER_ID] ?? 0),
            'deptId' => $claims[JwtClaimConstants::DEPT_ID] ?? null,
            'dataScopes' => $dataScopes,
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

        // refresh 时重新查询完整的角色数据权限
        $dataScopes = $this->buildDataScopes($userId);
        $authorities = $this->buildAuthorities($userId);

        $userAuthInfo = [
            'userId' => $userId,
            'deptId' => $user['dept_id'] ?? null,
            'dataScopes' => $dataScopes,
            'authorities' => $authorities,
        ];

        return $this->generateToken($userAuthInfo);
    }

    /**
     * 构建用户角色数据权限列表
     */
    private function buildDataScopes(int $userId): array
    {
        $roles = Db::name('sys_user_role')
            ->alias('ur')
            ->join('sys_role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->where('r.is_deleted', 0)
            ->where('r.status', 1)
            ->field('r.id,r.code,r.data_scope')
            ->select()
            ->toArray();

        $dataScopes = [];
        foreach ($roles as $role) {
            $roleCode = $role['code'] ?? '';
            $dataScope = (int) ($role['data_scope'] ?? 4);
            $customDeptIds = null;

            // 如果是自定义部门权限，查询该角色的自定义部门列表
            if ($dataScope === 5 && !empty($role['id'])) {
                $customDeptIds = Db::name('sys_role_dept')
                    ->where('role_id', $role['id'])
                    ->column('dept_id');
                $customDeptIds = array_values(array_filter(array_map('intval', $customDeptIds), fn($v) => $v > 0));
            }

            $dataScopes[] = [
                'roleCode' => $roleCode,
                'dataScope' => $dataScope,
                'customDeptIds' => $customDeptIds,
            ];
        }

        return $dataScopes;
    }

    /**
     * 构建用户权限标识列表
     */
    private function buildAuthorities(int $userId): array
    {
        $roles = Db::name('sys_user_role')
            ->alias('ur')
            ->join('sys_role r', 'ur.role_id = r.id')
            ->where('ur.user_id', $userId)
            ->where('r.is_deleted', 0)
            ->where('r.status', 1)
            ->column('r.code');

        $authorities = [];
        foreach ($roles as $code) {
            if (!empty($code)) {
                $authorities[] = 'ROLE_' . $code;
            }
        }

        return array_values(array_unique($authorities));
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

        // 递增 Token 版本号，统一踢出旧 token
        if ($userId !== null && $userId > 0) {
            $tokenVersionKey = RedisKey::format((string) ($keys['user_token_version'] ?? 'auth:user:token_version:{}'), $userId);
            $redis->incr($tokenVersionKey);
        }

        // 手动加入黑名单，防止立即复用
        if (!empty($accessToken)) {
            $this->blacklistToken($accessToken, 86400);
        }

        if (!empty($refreshToken)) {
            $this->blacklistToken($refreshToken, 86400);
        }
    }

    private function decodeAndValidate(string $token, bool $checkBlacklist = true): array
    {
        // 黑名单校验优先于 JWT 解码
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
        $tokenVersion = (int) ($claims[JwtClaimConstants::TOKEN_VERSION] ?? 0);

        if ($userId <= 0) {
            throw new BusinessException(ResultCode::ACCESS_TOKEN_INVALID);
        }

        $keys = $cfg['redis']['keys'] ?? [];
        $redis = RedisClient::get();
        $tokenVersionKey = RedisKey::format((string) ($keys['user_token_version'] ?? 'auth:user:token_version:{}'), $userId);
        $currentVersion = (int) ($redis->get($tokenVersionKey) ?: 0);

        if ($tokenVersion < $currentVersion) {
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

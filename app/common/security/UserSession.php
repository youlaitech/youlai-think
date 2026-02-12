<?php

declare(strict_types=1);

namespace app\common\security;

/**
 * 角色数据权限信息
 * 用于存储单个角色的数据权限范围信息，支持多角色数据权限合并（并集策略）
 */
class RoleDataScope
{
    public function __construct(
        public string $roleCode,
        public int $dataScope,
        public ?array $customDeptIds = null
    ) {}

    /**
     * 创建"全部数据"权限
     */
    public static function all(string $roleCode): self
    {
        return new self($roleCode, 1, null);
    }

    /**
     * 创建"部门及子部门"权限
     */
    public static function deptAndSub(string $roleCode): self
    {
        return new self($roleCode, 2, null);
    }

    /**
     * 创建"本部门"权限
     */
    public static function dept(string $roleCode): self
    {
        return new self($roleCode, 3, null);
    }

    /**
     * 创建"本人"权限
     */
    public static function self(string $roleCode): self
    {
        return new self($roleCode, 4, null);
    }

    /**
     * 创建"自定义部门"权限
     */
    public static function custom(string $roleCode, array $deptIds): self
    {
        return new self($roleCode, 5, $deptIds);
    }

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'roleCode' => $this->roleCode,
            'dataScope' => $this->dataScope,
            'customDeptIds' => $this->customDeptIds,
        ];
    }

    /**
     * 从数组创建实例
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['roleCode'] ?? '',
            $data['dataScope'] ?? 0,
            $data['customDeptIds'] ?? null
        );
    }
}

/**
 * 用户会话信息
 * 存储在Token中的用户会话快照，包含用户身份、数据权限和角色权限信息。
 * 用于Redis-Token模式下的会话管理，支持在线用户查询和会话控制。
 */
class UserSession
{
    public function __construct(
        public int $userId,
        public string $username,
        public ?int $deptId = null,
        public array $dataScopes = [],
        public array $roles = []
    ) {}

    /**
     * 转换为数组
     */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'username' => $this->username,
            'deptId' => $this->deptId,
            'dataScopes' => array_map(fn($ds) => $ds instanceof RoleDataScope ? $ds->toArray() : $ds, $this->dataScopes),
            'roles' => $this->roles,
        ];
    }

    /**
     * 转换为JSON字符串
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * 从数组创建实例
     */
    public static function fromArray(array $data): self
    {
        $dataScopes = [];
        if (isset($data['dataScopes']) && is_array($data['dataScopes'])) {
            foreach ($data['dataScopes'] as $ds) {
                $dataScopes[] = is_array($ds) ? RoleDataScope::fromArray($ds) : $ds;
            }
        }

        return new self(
            $data['userId'] ?? 0,
            $data['username'] ?? '',
            $data['deptId'] ?? null,
            $dataScopes,
            $data['roles'] ?? []
        );
    }

    /**
     * 从JSON字符串创建实例
     */
    public static function fromJson(string $json): ?self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        return self::fromArray($data);
    }
}

<?php
namespace app;

// 应用请求对象类
class Request extends \think\Request
{

    private ?array $authUser = null;

    public function setAuthUser(?array $user): void
    {
        $this->authUser = $user;
    }

    public function getAuthUser(): ?array
    {
        return $this->authUser;
    }
}

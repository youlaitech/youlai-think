<?php declare(strict_types=1);

namespace extend\sse;

/**
 * SSE 会话注册表（Workerman 进程内，非跨进程共享）
 */
class SseSessionRegistry
{
    /** @var array<string, SseEmitter[]> */
    private array $userEmitters = [];
    /** @var array<int, string> */
    private array $emitterUsers = [];

    public function userConnected(string $username, SseEmitter $emitter): void
    {
        $this->userEmitters[$username][] = $emitter;
        $this->emitterUsers[spl_object_id($emitter)] = $username;
    }

    public function removeEmitter(SseEmitter $emitter): void
    {
        $id = spl_object_id($emitter);
        if (!isset($this->emitterUsers[$id])) return;

        $username = $this->emitterUsers[$id];
        unset($this->emitterUsers[$id]);

        if (isset($this->userEmitters[$username])) {
            $this->userEmitters[$username] = array_values(
                array_filter($this->userEmitters[$username], fn($e) => spl_object_id($e) !== $id)
            );
            if (empty($this->userEmitters[$username])) {
                unset($this->userEmitters[$username]);
            }
        }
    }

    public function getUserEmitters(string $username): array
    {
        return $this->userEmitters[$username] ?? [];
    }

    public function getUserEmitterCount(string $username): int
    {
        return isset($this->userEmitters[$username]) ? count($this->userEmitters[$username]) : 0;
    }

    public function getAllEmitters(): array
    {
        $all = [];
        foreach ($this->userEmitters as $list) {
            foreach ($list as $emitter) {
                $all[] = $emitter;
            }
        }
        return $all;
    }

    public function getOnlineUsers(): array
    {
        return array_keys($this->userEmitters);
    }

    public function getOnlineUserCount(): int
    {
        return count($this->userEmitters);
    }

    public function getTotalConnectionCount(): int
    {
        return count($this->emitterUsers);
    }
}

<?php declare(strict_types=1);

namespace extend\sse;

/**
 * SSE 会话注册表
 * 维护用户名与 SseEmitter 实例的映射关系
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
            $emitters = &$this->userEmitters[$username];
            foreach ($emitters as $index => $e) {
                if (spl_object_id($e) === $id) { unset($emitters[$index]); break; }
            }
            if (empty($emitters)) unset($this->userEmitters[$username]);
        }
    }

    public function getUserEmitters(string $username): array { return $this->userEmitters[$username] ?? []; }
    public function getUserEmitterCount(string $username): int { return isset($this->userEmitters[$username]) ? count($this->userEmitters[$username]) : 0; }
    public function getAllEmitters(): array { $emitters = []; foreach ($this->userEmitters as $list) { foreach ($list as $emitter) { $emitters[] = $emitter; } } return $emitters; }
    public function getOnlineUsers(): array { return array_keys($this->userEmitters); }
    public function getOnlineUserCount(): int { return count($this->userEmitters); }
    public function getTotalConnectionCount(): int { return count($this->emitterUsers); }
}

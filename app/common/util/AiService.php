<?php declare(strict_types=1);

namespace app\common\util;

use app\common\exception\BusinessException;
use GuzzleHttp\Client;

/**
 * AI 对话：OpenAI 兼容的 chat/completions 调用与提示词加载
 */
final class AiService
{
    /**
     * 提示词目录，各模块的 md 提示词集中放在动态表单模块下
     */
    private const PROMPTS_DIR = 'app/form/prompts';

    /**
     * 读取提示词文件
     * @param string $name 相对提示词目录的路径，如 menu/system.md
     */
    public function loadPrompt(string $name): string
    {
        $path = root_path() . self::PROMPTS_DIR . '/' . ltrim($name, '/');
        if (!is_file($path)) {
            throw new BusinessException('AI 提示词文件缺失：' . $name);
        }

        return trim((string) file_get_contents($path));
    }

    /**
     * 调用 AI 对话接口，返回去掉代码围栏的文本内容
     */
    public function chat(string $systemPrompt, string $userPrompt): string
    {
        $baseUrl = (string) config('ai.base_url', '');
        $apiKey = (string) config('ai.api_key', '');

        if ($baseUrl === '' || $apiKey === '') {
            throw new BusinessException('AI 功能未开启，请配置 AI_BASE_URL 与 AI_API_KEY');
        }

        $client = new Client(['timeout' => (int) config('ai.timeout', 60)]);
        $response = $client->post(rtrim($baseUrl, '/') . '/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => (string) config('ai.model', 'qwen-plus'),
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'response_format' => ['type' => 'json_object'],
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        return $this->stripCodeFence((string) ($payload['choices'][0]['message']['content'] ?? ''));
    }

    /**
     * 调用 AI 对话接口并把返回内容解析为数组
     */
    public function chatArray(string $systemPrompt, string $userPrompt): array
    {
        $content = $this->chat($systemPrompt, $userPrompt);
        $result = json_decode($content, true);
        if (!is_array($result)) {
            throw new BusinessException('AI 返回内容非合法 JSON，请重试');
        }

        return $result;
    }

    /**
     * 去掉模型输出里可能包裹的 json 代码围栏
     */
    private function stripCodeFence(string $content): string
    {
        $text = trim($content);
        if (!str_starts_with($text, '```')) {
            return $text;
        }

        $start = strpos($text, "\n");
        $end = strrpos($text, '```');
        if ($start === false || $end === false || $end <= $start) {
            return $text;
        }

        return trim(substr($text, $start + 1, $end - $start - 1));
    }
}

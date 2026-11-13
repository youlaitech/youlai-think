<?php declare(strict_types=1);

// AI 对话（OpenAI 兼容协议，默认关闭；动态表单/菜单的 AI 辅助接口依赖它）
return [
    'base_url' => env('AI_BASE_URL', 'https://dashscope.aliyuncs.com/compatible-mode/v1'),
    'api_key' => env('AI_API_KEY', ''),
    'model' => env('AI_MODEL', 'qwen-plus'),
    // 秒
    'timeout' => (int) env('AI_TIMEOUT', 60),
];

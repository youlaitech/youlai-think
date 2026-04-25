<?php declare(strict_types=1);

namespace app\common\util;

/**
 * 简单字符串模板渲染器（支持 {var} 和 {$var}）
 */
final class TemplateRenderer
{
    /**
     * 渲染模板，将占位符替换为实际值
     */
    public function render(string $content, array $vars = []): string
    {
        if (empty($vars)) {
            return $content;
        }

        $keys = [];
        $values = [];
        foreach ($vars as $key => $value) {
            $keys[] = '{$' . $key . '}';
            $keys[] = '{' . $key . '}';
            $val = is_string($value) || is_numeric($value)
                ? (string) $value
                : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $values[] = $val;
            $values[] = $val;
        }

        return str_replace($keys, $values, $content);
    }
}

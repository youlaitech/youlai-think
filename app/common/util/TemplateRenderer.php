<?php

declare(strict_types=1);

namespace app\common\util;

/**
 * 轻量模板渲染
 */
final class TemplateRenderer
{
    /**
     * 变量替换渲染
     */
    public function render(string $template, array $vars): string
    {
        $replacements = [];
        foreach ($vars as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            if (is_array($v) || is_object($v)) {
                continue;
            }
            $value = (string) $v;
            $replacements['{{' . $k . '}}'] = $value;
            $replacements['${' . $k . '}'] = $value;
        }

        return strtr($template, $replacements);
    }
}

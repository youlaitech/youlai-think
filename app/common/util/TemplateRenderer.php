<?php declare(strict_types=1);

namespace app\common\util;

use think\Template;

/**
 * 模板渲染器 - 基于 think-template
 */
final class TemplateRenderer
{
    private Template $template;

    public function __construct()
    {
        $this->template = new Template([
            'view_path'    => '',
            'cache_path'   => runtime_path() . 'template/',
            'view_suffix'  => 'tpl',
            'tpl_begin'    => '{',
            'tpl_end'      => '}',
        ]);
    }

    /**
     * 渲染模板字符串
     * @param string $content 模板内容
     * @param array $vars 变量
     * @return string 渲染结果
     */
    public function render(string $content, array $vars = []): string
    {
        return $this->template->fetchString($content, $vars);
    }
}

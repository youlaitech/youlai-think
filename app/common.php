<?php
// 应用公共文件

// ThinkPHP 8.x 框架内部在 think 命名空间下 use InvalidArgumentException，
// 解析为 think\InvalidArgumentException，但该类实际位于 think\exception\InvalidArgumentException
class_alias(\think\exception\InvalidArgumentException::class, 'think\InvalidArgumentException');

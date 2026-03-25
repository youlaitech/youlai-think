<?php declare(strict_types=1);

namespace app\system\annotation;

use app\system\enums\ActionType;
use app\system\enums\LogModule;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Log
{
    public function __construct(
        public ActionType $actionType,
        public LogModule $module = LogModule::OTHER,
        public string $title = '',
        public string $content = ''
    ) {}
}

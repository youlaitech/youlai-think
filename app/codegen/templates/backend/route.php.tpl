<?php

// {$businessName} 路由

use app\{$moduleName}\controller\{$entityName}Controller;
use think\facade\Route;

Route::group('api/v1/{$entityKebabPlural}', function () {
    Route::get('', [{$entityName}Controller::class, 'page'])->middleware('perm', '{$permPrefix}:list');
    Route::get(':id/form', [{$entityName}Controller::class, 'form'])->pattern(['id' => '\d+'])->middleware('perm', '{$permPrefix}:update');
    Route::post('', [{$entityName}Controller::class, 'create'])->middleware('perm', '{$permPrefix}:create');
    Route::put(':id', [{$entityName}Controller::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', '{$permPrefix}:update');
    Route::delete(':ids', [{$entityName}Controller::class, 'delete'])->middleware('perm', '{$permPrefix}:delete');
})->middleware([\app\common\middleware\AuthMiddleware::class]);

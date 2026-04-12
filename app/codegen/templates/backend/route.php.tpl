<?php

// {$businessName} 路由

use think\facade\Route;

Route::group('api/v1/{$entityKebab}', function () {
    Route::get('', [\app\{$moduleName}\controller\{$entityName}Controller::class, 'page']);
    Route::get(':id/form', [\app\{$moduleName}\controller\{$entityName}Controller::class, 'form'])->pattern(['id' => '\\d+']);
    Route::post('', [\app\{$moduleName}\controller\{$entityName}Controller::class, 'create']);
    Route::put(':id', [\app\{$moduleName}\controller\{$entityName}Controller::class, 'update'])->pattern(['id' => '\\d+']);
    Route::delete(':ids', [\app\{$moduleName}\controller\{$entityName}Controller::class, 'delete']);
})->middleware(['auth', 'demo']);

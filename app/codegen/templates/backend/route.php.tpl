<?php

// {{businessName}} 路由

use think\facade\Route;

Route::group('api/v1/{{entityKebab}}', function () {
    Route::get('', [\\app\\controller\\{{entityName}}Controller::class, 'page']);
    Route::get(':id/form', [\\app\\controller\\{{entityName}}Controller::class, 'form'])->pattern(['id' => '\\d+']);
    Route::post('', [\\app\\controller\\{{entityName}}Controller::class, 'create']);
    Route::put(':id', [\\app\\controller\\{{entityName}}Controller::class, 'update'])->pattern(['id' => '\\d+']);
    Route::delete(':ids', [\\app\\controller\\{{entityName}}Controller::class, 'delete']);
})->middleware(['auth', 'demo']);

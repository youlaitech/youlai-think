<?php

// {{businessName}} 路由

use think\facade\Route;

Route::group('api/v1/{{moduleName}}/{{entityKebab}}', function () {
    Route::get('', [\\app\\{{moduleNameStudly}}\\Controller\\{{entityName}}Controller::class, 'page']);
    Route::get(':id/form', [\\app\\{{moduleNameStudly}}\\Controller\\{{entityName}}Controller::class, 'form'])->pattern(['id' => '\\d+']);
    Route::post('', [\\app\\{{moduleNameStudly}}\\Controller\\{{entityName}}Controller::class, 'create']);
    Route::put(':id', [\\app\\{{moduleNameStudly}}\\Controller\\{{entityName}}Controller::class, 'update'])->pattern(['id' => '\\d+']);
    Route::delete(':ids', [\\app\\{{moduleNameStudly}}\\Controller\\{{entityName}}Controller::class, 'delete']);
})->middleware(['auth', 'demo']);

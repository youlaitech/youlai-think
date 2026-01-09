<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('api/v1', function () {
    Route::post('auth/login', [\app\controller\AuthController::class, 'login']);
    Route::delete('auth/logout', [\app\controller\AuthController::class, 'logout']);
    Route::post('auth/refresh-token', [\app\controller\AuthController::class, 'refreshToken']);

    Route::group('users', function () {
        Route::get('me', [\app\controller\UserController::class, 'me']);

        Route::get('', [\app\controller\UserController::class, 'page'])->middleware('perm:sys:user:list');
        Route::get('options', [\app\controller\UserController::class, 'options']);

        Route::get(':userId/form', [\app\controller\UserController::class, 'form'])->pattern(['userId' => '\\d+'])->middleware('perm:sys:user:update');
        Route::post('', [\app\controller\UserController::class, 'create'])->middleware('perm:sys:user:create');
        Route::put(':id', [\app\controller\UserController::class, 'update'])->pattern(['id' => '\\d+'])->middleware('perm:sys:user:update');
        Route::delete(':ids', [\app\controller\UserController::class, 'delete'])->middleware('perm:sys:user:delete');

        Route::put(':id/password/reset', [\app\controller\UserController::class, 'resetPassword'])->pattern(['id' => '\\d+'])->middleware('perm:sys:user:reset-password');
        Route::patch(':userId/status', [\app\controller\UserController::class, 'updateStatus'])->pattern(['userId' => '\\d+'])->middleware('perm:sys:user:update');

        Route::get('template', [\app\controller\UserController::class, 'downloadTemplate']);
        Route::get('export', [\app\controller\UserController::class, 'export'])->middleware('perm:sys:user:export');
        Route::post('import', [\app\controller\UserController::class, 'import'])->middleware('perm:sys:user:import');

        Route::get('profile', [\app\controller\UserController::class, 'profile']);
        Route::put('profile', [\app\controller\UserController::class, 'updateProfile']);
        Route::put('password', [\app\controller\UserController::class, 'changePassword']);
        Route::post('mobile/code', [\app\controller\UserController::class, 'sendMobileCode']);
        Route::put('mobile', [\app\controller\UserController::class, 'bindOrChangeMobile']);
        Route::post('email/code', [\app\controller\UserController::class, 'sendEmailCode']);
        Route::put('email', [\app\controller\UserController::class, 'bindOrChangeEmail']);
    })->middleware(['auth', 'dataScope', 'demo']);

    Route::group('depts', function () {
        Route::get('', [\app\controller\DeptController::class, 'index']);
        Route::get('options', [\app\controller\DeptController::class, 'options']);

        Route::post('', [\app\controller\DeptController::class, 'create'])->middleware('perm:sys:dept:create');
        Route::get(':deptId/form', [\app\controller\DeptController::class, 'form'])->pattern(['deptId' => '\\d+']);
        Route::put(':deptId', [\app\controller\DeptController::class, 'update'])->pattern(['deptId' => '\\d+'])->middleware('perm:sys:dept:update');
        Route::delete(':ids', [\app\controller\DeptController::class, 'delete'])->middleware('perm:sys:dept:delete');
    })->middleware(['auth', 'dataScope', 'demo']);

    Route::group('dicts', function () {
        Route::get('', [\app\controller\DictController::class, 'page'])->middleware('perm:sys:dict:list');
        Route::get('options', [\app\controller\DictController::class, 'index']);

        Route::get(':id/form', [\app\controller\DictController::class, 'form'])->pattern(['id' => '\\d+']);
        Route::post('', [\app\controller\DictController::class, 'create'])->middleware('perm:sys:dict:create');
        Route::put(':id', [\app\controller\DictController::class, 'update'])->pattern(['id' => '\\d+'])->middleware('perm:sys:dict:update');
        Route::delete(':ids', [\app\controller\DictController::class, 'delete'])->middleware('perm:sys:dict:delete');

        Route::get(':dictCode/items', [\app\controller\DictController::class, 'itemPage']);
        Route::get(':dictCode/items/options', [\app\controller\DictController::class, 'items']);
        Route::post(':dictCode/items', [\app\controller\DictController::class, 'createItem'])->middleware('perm:sys:dict-item:create');
        Route::get(':dictCode/items/:itemId/form', [\app\controller\DictController::class, 'itemForm'])->pattern(['itemId' => '\\d+']);
        Route::put(':dictCode/items/:itemId', [\app\controller\DictController::class, 'updateItem'])->pattern(['itemId' => '\\d+'])->middleware('perm:sys:dict-item:update');
        Route::delete(':dictCode/items/:itemIds', [\app\controller\DictController::class, 'deleteItems'])->middleware('perm:sys:dict-item:delete');
    })->middleware(['auth', 'demo']);

    Route::group('notices', function () {
        Route::get('', [\app\controller\NoticeController::class, 'page'])->middleware('perm:sys:notice:list');
        Route::post('', [\app\controller\NoticeController::class, 'create'])->middleware('perm:sys:notice:create');
        Route::get(':id/form', [\app\controller\NoticeController::class, 'form'])->pattern(['id' => '\\d+']);
        Route::get(':id/detail', [\app\controller\NoticeController::class, 'detail'])->pattern(['id' => '\\d+']);
        Route::put(':id', [\app\controller\NoticeController::class, 'update'])->pattern(['id' => '\\d+'])->middleware('perm:sys:notice:update');
        Route::put(':id/publish', [\app\controller\NoticeController::class, 'publish'])->pattern(['id' => '\\d+'])->middleware('perm:sys:notice:publish');
        Route::put(':id/revoke', [\app\controller\NoticeController::class, 'revoke'])->pattern(['id' => '\\d+'])->middleware('perm:sys:notice:revoke');
        Route::delete(':ids', [\app\controller\NoticeController::class, 'delete'])->middleware('perm:sys:notice:delete');
        Route::put('read-all', [\app\controller\NoticeController::class, 'readAll']);
        Route::get('my', [\app\controller\NoticeController::class, 'my']);
    })->middleware(['auth', 'dataScope', 'demo']);

    Route::group('configs', function () {
        Route::get('', [\app\controller\ConfigController::class, 'page'])->middleware('perm:sys:config:list');
        Route::post('', [\app\controller\ConfigController::class, 'create'])->middleware('perm:sys:config:create');
        Route::get(':id/form', [\app\controller\ConfigController::class, 'form'])->pattern(['id' => '\\d+']);
        Route::put(':id', [\app\controller\ConfigController::class, 'update'])->pattern(['id' => '\\d+'])->middleware('perm:sys:config:update');
        Route::delete(':id', [\app\controller\ConfigController::class, 'delete'])->pattern(['id' => '\\d+'])->middleware('perm:sys:config:delete');
        Route::put('refresh', [\app\controller\ConfigController::class, 'refresh'])->middleware('perm:sys:config:refresh');
    })->middleware(['auth', 'demo']);

    Route::group('logs', function () {
        Route::get('', [\app\controller\LogController::class, 'page']);
    })->middleware(['auth']);

    Route::group('statistics', function () {
        Route::get('visits/trend', [\app\controller\StatisticsController::class, 'visitTrend']);
        Route::get('visits/overview', [\app\controller\StatisticsController::class, 'visitOverview']);
    })->middleware(['auth']);

    Route::group('menus', function () {
        Route::get('', [\app\controller\MenuController::class, 'index']);
        Route::get('options', [\app\controller\MenuController::class, 'options']);
        Route::get('routes', [\app\controller\MenuController::class, 'routes']);

        Route::get(':id/form', [\app\controller\MenuController::class, 'form'])->pattern(['id' => '\\d+'])->middleware('perm:sys:menu:update');
        Route::post('', [\app\controller\MenuController::class, 'create'])->middleware('perm:sys:menu:create');
        Route::put(':id', [\app\controller\MenuController::class, 'update'])->pattern(['id' => '\\d+'])->middleware('perm:sys:menu:update');
        Route::delete(':id', [\app\controller\MenuController::class, 'delete'])->pattern(['id' => '\\d+'])->middleware('perm:sys:menu:delete');
        Route::patch(':menuId', [\app\controller\MenuController::class, 'updateVisible'])->pattern(['menuId' => '\\d+'])->middleware('perm:sys:menu:update');
    })->middleware(['auth', 'demo']);

    Route::group('roles', function () {
        Route::get('', [\app\controller\RoleController::class, 'page'])->middleware('perm:sys:role:list');
        Route::get('options', [\app\controller\RoleController::class, 'options']);
        Route::get(':roleId/form', [\app\controller\RoleController::class, 'form'])->pattern(['roleId' => '\\d+'])->middleware('perm:sys:role:update');
        Route::post('', [\app\controller\RoleController::class, 'create'])->middleware('perm:sys:role:create');
        Route::put(':id', [\app\controller\RoleController::class, 'update'])->pattern(['id' => '\\d+'])->middleware('perm:sys:role:update');
        Route::delete(':ids', [\app\controller\RoleController::class, 'delete'])->middleware('perm:sys:role:delete');

        Route::get(':roleId/menuIds', [\app\controller\RoleController::class, 'menuIds'])->pattern(['roleId' => '\\d+']);
        Route::put(':roleId/menus', [\app\controller\RoleController::class, 'assignMenus'])->pattern(['roleId' => '\\d+'])->middleware('perm:sys:role:assign');
    })->middleware(['auth', 'demo']);

    Route::group('codegen', function () {
        Route::get('table', [\app\controller\CodegenController::class, 'tablePage']);
        Route::get(':tableName/config', [\app\controller\CodegenController::class, 'getConfig']);
        Route::post(':tableName/config', [\app\controller\CodegenController::class, 'saveConfig']);
        Route::delete(':tableName/config', [\app\controller\CodegenController::class, 'deleteConfig']);
        Route::get(':tableName/preview', [\app\controller\CodegenController::class, 'preview']);
        Route::get(':tableName/download', [\app\controller\CodegenController::class, 'download']);
    })->middleware(['auth', 'demo']);
});

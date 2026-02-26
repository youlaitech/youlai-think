<?php declare(strict_types=1);

use app\controller\AuthController;
use app\controller\UserController;
use app\controller\RoleController;
use app\controller\MenuController;
use app\controller\DeptController;
use think\facade\Route;

// ==================== 认证接口（无需登录�?====================

Route::group('api/v1/auth', function () {
    Route::get('captcha', [AuthController::class, 'captcha']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('refresh', [AuthController::class, 'refresh']);
    Route::post('logout', [AuthController::class, 'logout']);
});

// ==================== 业务接口（需要登录） ====================

Route::group('api/v1', function () {

    // 用户管理
    Route::group('users', function () {
        Route::get('me', [UserController::class, 'me']);
        Route::get('template', [UserController::class, 'template']);
        Route::post('import', [UserController::class, 'import'])->middleware('perm', 'sys:user:import');
        Route::get('', [UserController::class, 'page'])->middleware('perm', 'sys:user:list');
        Route::get(':id', [UserController::class, 'detail'])->pattern(['id' => '\d+']);
        Route::post('', [UserController::class, 'create'])->middleware('perm', 'sys:user:create');
        Route::put(':id', [UserController::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:user:update');
        Route::delete(':ids', [UserController::class, 'delete'])->middleware('perm', 'sys:user:delete');
        Route::put(':id/password', [UserController::class, 'resetPassword'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:user:resetPwd');
        Route::put(':id/status', [UserController::class, 'changeStatus'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:user:update');
    });

    // 角色管理
    Route::group('roles', function () {
        Route::get('options', [RoleController::class, 'options']);
        Route::get('', [RoleController::class, 'page'])->middleware('perm', 'sys:role:list');
        Route::get(':id', [RoleController::class, 'detail'])->pattern(['id' => '\d+']);
        Route::post('', [RoleController::class, 'create'])->middleware('perm', 'sys:role:create');
        Route::put(':id', [RoleController::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:role:update');
        Route::delete(':ids', [RoleController::class, 'delete'])->middleware('perm', 'sys:role:delete');
    });

    // 菜单管理
    Route::group('menus', function () {
        Route::get('routes', [MenuController::class, 'routes']);
        Route::get('tree', [MenuController::class, 'tree'])->middleware('perm', 'sys:menu:list');
        Route::get('', [MenuController::class, 'list'])->middleware('perm', 'sys:menu:list');
        Route::get(':id', [MenuController::class, 'detail'])->pattern(['id' => '\d+']);
        Route::post('', [MenuController::class, 'create'])->middleware('perm', 'sys:menu:create');
        Route::put(':id', [MenuController::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:menu:update');
        Route::delete(':id', [MenuController::class, 'delete'])->middleware('perm', 'sys:menu:delete');
    });

    // 部门管理
    Route::group('depts', function () {
        Route::get('tree', [DeptController::class, 'tree']);
        Route::get('', [DeptController::class, 'list'])->middleware('perm', 'sys:dept:list');
        Route::get(':id', [DeptController::class, 'detail'])->pattern(['id' => '\d+']);
        Route::post('', [DeptController::class, 'create'])->middleware('perm', 'sys:dept:create');
        Route::put(':id', [DeptController::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:dept:update');
        Route::delete(':id', [DeptController::class, 'delete'])->middleware('perm', 'sys:dept:delete');
    });

})->middleware([
    'auth',
]);

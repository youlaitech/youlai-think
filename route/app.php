<?php declare(strict_types=1);

use app\Auth\Controller\AuthController;
use app\Auth\Controller\WechatMiniappAuthController;
use app\Codegen\Controller\CodegenController;
use app\System\Controller\UserController;
use app\System\Controller\RoleController;
use app\System\Controller\MenuController;
use app\System\Controller\DeptController;
use app\System\Controller\NoticeController;
use app\System\Controller\StatisticsController;
use app\System\Controller\ConfigController;
use app\System\Controller\DictController;
use app\System\Controller\FileController;
use app\System\Controller\LogController;
use think\facade\Route;

// ==================== 认证接口（无需登录） ====================

Route::group('api/v1/auth', function () {
    Route::get('captcha', [AuthController::class, 'captcha']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('login/sms', [AuthController::class, 'loginBySms']);
    Route::post('sms/code', [AuthController::class, 'sendLoginVerifyCode']);
    Route::post('refresh-token', [AuthController::class, 'refresh']);
    Route::delete('logout', [AuthController::class, 'logout']);
});

// ==================== 微信小程序认证接口（无需登录） ====================

Route::group('api/v1/wechat/miniapp/auth', function () {
    Route::post('silent-login', [WechatMiniappAuthController::class, 'silentLogin']);
    Route::post('phone-login', [WechatMiniappAuthController::class, 'phoneLogin']);
    Route::post('bind-mobile', [WechatMiniappAuthController::class, 'bindMobile']);
});

// ==================== 业务接口（需要登录） ====================

Route::group('api/v1', function () {

    // 用户管理
    Route::group('users', function () {
        Route::get('me', [UserController::class, 'me']);
        Route::get('options', [UserController::class, 'options']);
        Route::get('profile', [UserController::class, 'profile']);
        Route::put('profile', [UserController::class, 'updateProfile']);
        Route::put('password', [UserController::class, 'changePassword']);
        Route::post('mobile/code', [UserController::class, 'sendMobileCode']);
        Route::put('mobile', [UserController::class, 'bindOrChangeMobile']);
        Route::delete('mobile', [UserController::class, 'unbindMobile']);
        Route::post('email/code', [UserController::class, 'sendEmailCode']);
        Route::put('email', [UserController::class, 'bindOrChangeEmail']);
        Route::delete('email', [UserController::class, 'unbindEmail']);
        Route::get('template', [UserController::class, 'template']);
        Route::get('export', [UserController::class, 'export'])->middleware('perm', 'sys:user:export');
        Route::post('import', [UserController::class, 'import'])->middleware('perm', 'sys:user:import');
        Route::get('', [UserController::class, 'page'])->middleware('perm', 'sys:user:list');
        Route::get(':id/form', [UserController::class, 'form'])->pattern(['id' => '\d+']);
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
        Route::get(':id/menu-ids', [RoleController::class, 'menuIds'])->pattern(['id' => '\d+']);
        Route::put(':id/menus', [RoleController::class, 'assignMenus'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:role:assign');
        Route::get(':id/dept-ids', [RoleController::class, 'deptIds'])->pattern(['id' => '\d+']);
        Route::put(':id/status', [RoleController::class, 'status'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:role:update');
        Route::get('', [RoleController::class, 'page'])->middleware('perm', 'sys:role:list');
        Route::get(':id/form', [RoleController::class, 'form'])->pattern(['id' => '\d+']);
        Route::get(':id', [RoleController::class, 'detail'])->pattern(['id' => '\d+']);
        Route::post('', [RoleController::class, 'create'])->middleware('perm', 'sys:role:create');
        Route::put(':id', [RoleController::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:role:update');
        Route::delete(':ids', [RoleController::class, 'delete'])->middleware('perm', 'sys:role:delete');
    });

    // 菜单管理
    Route::group('menus', function () {
        Route::get('options', [MenuController::class, 'options']);
        Route::get('routes', [MenuController::class, 'routes']);
        Route::get('tree', [MenuController::class, 'tree'])->middleware('perm', 'sys:menu:list');
        Route::put(':id/visible', [MenuController::class, 'visible'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:menu:update');
        Route::get('', [MenuController::class, 'list'])->middleware('perm', 'sys:menu:list');
        Route::get(':id/form', [MenuController::class, 'form'])->pattern(['id' => '\d+']);
        Route::get(':id', [MenuController::class, 'detail'])->pattern(['id' => '\d+']);
        Route::post('', [MenuController::class, 'create'])->middleware('perm', 'sys:menu:create');
        Route::put(':id', [MenuController::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:menu:update');
        Route::delete(':id', [MenuController::class, 'delete'])->middleware('perm', 'sys:menu:delete');
    });

    // 部门管理
    Route::group('depts', function () {
        Route::get('options', [DeptController::class, 'options']);
        Route::get('tree', [DeptController::class, 'tree']);
        Route::get('', [DeptController::class, 'list'])->middleware('perm', 'sys:dept:list');
        Route::get(':id/form', [DeptController::class, 'form'])->pattern(['id' => '\d+']);
        Route::get(':id', [DeptController::class, 'detail'])->pattern(['id' => '\d+']);
        Route::post('', [DeptController::class, 'create'])->middleware('perm', 'sys:dept:create');
        Route::put(':id', [DeptController::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:dept:update');
        Route::delete(':id', [DeptController::class, 'delete'])->middleware('perm', 'sys:dept:delete');
    });

    // 通知公告
    Route::group('notices', function () {
        Route::get('my', [NoticeController::class, 'my']);
        Route::put('read-all', [NoticeController::class, 'readAll']);
        Route::get(':id/form', [NoticeController::class, 'form'])->pattern(['id' => '\d+']);
        Route::get(':id/detail', [NoticeController::class, 'detail'])->pattern(['id' => '\d+']);
        Route::put(':id/publish', [NoticeController::class, 'publish'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:notice:publish');
        Route::put(':id/revoke', [NoticeController::class, 'revoke'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:notice:revoke');
        Route::get('', [NoticeController::class, 'page'])->middleware('perm', 'sys:notice:list');
        Route::post('', [NoticeController::class, 'create'])->middleware('perm', 'sys:notice:create');
        Route::put(':id', [NoticeController::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:notice:update');
        Route::delete(':ids', [NoticeController::class, 'delete']);
    });

    // 系统配置
    Route::group('configs', function () {
        Route::put('refresh', [ConfigController::class, 'refresh'])->middleware('perm', 'sys:config:refresh');
        Route::get(':id/form', [ConfigController::class, 'form'])->pattern(['id' => '\d+']);
        Route::get('', [ConfigController::class, 'page'])->middleware('perm', 'sys:config:list');
        Route::post('', [ConfigController::class, 'create'])->middleware('perm', 'sys:config:create');
        Route::put(':id', [ConfigController::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:config:update');
        Route::delete(':id', [ConfigController::class, 'delete'])->middleware('perm', 'sys:config:delete');
    });

    // 字典管理
    Route::group('dicts', function () {
        Route::get('options', [DictController::class, 'index']);
        Route::get(':id/form', [DictController::class, 'form'])->pattern(['id' => '\d+']);
        Route::get(':dictCode/items/options', [DictController::class, 'items'])->pattern(['dictCode' => '\w+']);
        Route::get(':dictCode/items/:itemId/form', [DictController::class, 'itemForm'])->pattern(['dictCode' => '\w+', 'itemId' => '\d+']);
        Route::get(':dictCode/items', [DictController::class, 'itemPage'])->pattern(['dictCode' => '\w+']);
        Route::post(':dictCode/items', [DictController::class, 'createItem'])->pattern(['dictCode' => '\w+'])->middleware('perm', 'sys:dict-item:create');
        Route::put(':dictCode/items/:itemId', [DictController::class, 'updateItem'])->pattern(['dictCode' => '\w+', 'itemId' => '\d+'])->middleware('perm', 'sys:dict-item:update');
        Route::delete(':dictCode/items/:itemIds', [DictController::class, 'deleteItems'])->pattern(['dictCode' => '\w+'])->middleware('perm', 'sys:dict-item:delete');
        Route::get('', [DictController::class, 'page']);
        Route::post('', [DictController::class, 'create'])->middleware('perm', 'sys:dict:create');
        Route::put(':id', [DictController::class, 'update'])->pattern(['id' => '\d+'])->middleware('perm', 'sys:dict:update');
        Route::delete(':ids', [DictController::class, 'delete'])->middleware('perm', 'sys:dict:delete');
    });

    // 文件管理
    Route::group('files', function () {
        Route::post('', [FileController::class, 'upload']);
        Route::delete('', [FileController::class, 'delete']);
    });

    // 日志管理
    Route::group('logs', function () {
        Route::get('', [LogController::class, 'page']);
    });

    // 统计分析
    Route::group('statistics', function () {
        Route::get('visits/trend', [StatisticsController::class, 'visitsTrend']);
        Route::get('visits/overview', [StatisticsController::class, 'visitsOverview']);
    });

    // 代码生成
    Route::group('codegen', function () {
        Route::get('table', [CodegenController::class, 'tablePage']);
        Route::get(':tableName/config', [CodegenController::class, 'getConfig']);
        Route::post(':tableName/config', [CodegenController::class, 'saveConfig']);
        Route::delete(':tableName/config', [CodegenController::class, 'deleteConfig']);
        Route::get(':tableName/preview', [CodegenController::class, 'preview']);
        Route::get(':tableName/download', [CodegenController::class, 'download']);
    });

})->middleware([
    'auth',
]);

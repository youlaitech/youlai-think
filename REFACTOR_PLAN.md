# youlai-think 完美重构方案

> 本文档为 AI 执行指南，按顺序执行即可达到处女座级完美。

---

## 一、问题诊断总览

| # | 问题 | 严重度 | 影响 |
|---|------|--------|------|
| P0 | `Auth/`, `System/` 目录大写，违反 TP8 规范 | **致命** | Linux 部署报错 |
| P1 | `common/` 目录承担 9 种职责，过于臃肿 | 高 | 可维护性差 |
| P2 | 缺少 DTO / VO 对象目录 | 中 | Controller 臃肿 |
| P3 | README 目录树与实际不符 + 未引用官方规范 | 低 | 文档误导 |

---

## 二、P0 修复：目录大小写（必须先做）

### 2.1 目录重命名 — 10 个 git mv 命令

```bash
cd d:/project/youlai/youlai-admin/youlai-think

# === Auth 模块（3 个） ===
git mv app/Auth app/auth
git mv "app/auth/Controller" "app/auth/controller"
git mv "app/auth/Service" "app/auth/service"

# === System 模块（7 个） ===
git mv app/System app/system
git mv "app/system/annotation" "app/system/annotation"
git mv "app/system/Controller" "app/system/controller"
git mv "app/system/enums" "app/system/enums"
git mv "app/system/Model" "app/system/model"
git mv "app/system/Service" "app/system/service"
git mv "app/system/Validate" "app/system/validate"
```

### 2.2 验证

```bash
# 确认无大写目录残留
node -e "const fs=require('fs');fs.readdirSync('app').filter(f=>f!==f.toLowerCase()&&fs.statSync('app/'+f).isDirectory()).forEach(d=>console.log('❌',d))"
# 应输出空（无结果 = 全部小写）

# 确认 namespace 已是小写（无需改代码）
grep -r "namespace.*\\\\[A-Z]" app/ --include="*.php"
# 应输出空
```

---

## 三、P1 修复：common 目录重构（核心优化）

### 3.1 当前结构 vs 目标结构

**当前（问题）**：
```
app/common/
├── constants/      ← 常量定义（3 文件）
├── controller/     ← BaseController（应提升到 app 级别）
├── enums/          ← 枚举类（1 文件，应提升到 app 级别）
├── exception/      ← 异常类（1 文件，应提升到 app 级别）
├── middleware/      ← 中间件（6 文件，保留）
├── model/          ← BaseModel（保留）
├── traits/         ← Trait 复用（3 文件，保留）
├── util/           ← 工具函数（2 文件，保留）
├── validate/       ← 校验基类（1 文件，保留）
└── web/            ← 响应封装（4 文件，保留）
```

**目标（完美）**：
```
app/
├── auth/                        # ✅ 认证模块（已改为小写）
│   ├── controller/
│   └── service/
│
├── system/                      # ✅ 系统模块（已改为小写）
│   ├── annotation/
│   ├── controller/
│   ├── enums/                   # ← 从 common 迁入
│   ├── model/
│   ├── service/
│   └── validate/
│
├── common/                     # 公共模块（精简后只保留真正的共享代码）
│   ├── middleware/              # 中间件（6 文件不变）
│   │   ├── AuthMiddleware.php
│   │   ├── Cors.php
│   │   ├── DataScopeMiddleware.php
│   │   ├── LogMiddleware.php
│   │   ├── PermMiddleware.php
│   │   └── RateLimitMiddleware.php
│   │
│   ├── model/                   # 基础模型（1 文件不变）
│   │   └── BaseModel.php
│   │
│   ├── traits/                  # Trait 复用（3 文件不变）
│   │   ├── AuthTrait.php
│   │   ├── PaginationTrait.php
│   │   └── ParamsTrait.php
│   │
│   ├── util/                    # 工具函数（2 文件不变）
│   │   ├── IdStringify.php
│   │   └── TemplateRenderer.php
│   │
│   ├── validate/                # 校验基类（1 文件不变）
│   │   └── BaseValidate.php
│   │
│   └── web/                     # 统一响应封装（4 文件不变）
│       ├── Result.php
│       ├── PageResult.php
│       ├── ResultCode.php
│       └── IResultCode.php
│
├── codegen/                     # 代码生成（不变）
├── file/                        # 文件上传（不变）
│
├── constants/                   # ★ 新建：应用常量（从 common 迁出）
│   ├── NoticeEvents.php
│   ├── RedisConstants.php
│   └── RedisKey.php
│
├── controller/                  # ★ 新建：基础控制器（从 common 迁出）
│   └── BaseController.php
│
├── enums/                       # ★ 新建：全局枚举（从 common 迁出）
│   └── DataScopeEnum.php
│
├── exception/                   # ★ 新建：异常类（从 common 迁出）
│   └── BusinessException.php
│
├── AppService.php              # 应用注册（不变）
├── ExceptionHandle.php         # 全局异常处理（不变）
├── middleware.php              # 全局中间件注册（不变）
├── provider.php                # 服务提供者（不变）
├── common.php                  # 公共函数（不变）
└── event.php                   # 事件定义（不变）
```

### 3.2 迁移命令（7 个文件移动）

```bash
cd d:/project/youlai/youlai-admin/youlai-think

# === 从 common 提升到 app 级别 ===
git mv "app/common/constants"                    "app/constants"
git mv "app/common/controller/BaseController.php"  "app/controller/BaseController.php"
git mv "app/common/enums/DataScopeEnum.php"         "app/enums/DataScopeEnum.php"
git mv "app/common/exception/BusinessException.php" "app/exception/BusinessException.php"

# === 清理 common 下已迁出的空目录 ===
# （git mv 会自动删除源位置的空目录）
# 如果有残留空目录手动删除：
rmdir "app/common/constants"     2>nul || true
rmdir "app/common/controller"    2>nul || true
rmdir "app/common/enums"         2>nul || true
rmdir "app/common/exception"     2>nul || true
```

### 3.3 namespace 更新（仅 4 个文件需修改）

```bash
# 这 4 个文件的 namespace 需要更新：

# 1. app/constants/NoticeEvents.php
#    旧: namespace app\common\constants;
#    新: namespace app\constants;

# 2. app/controller/BaseController.php
#    旧: namespace app\common\controller;
#    新: namespace app\controller;

# 3. app/enums/DataScopeEnum.php
#    旧: namespace app\common\enums;
#    新: namespace app\enums;

# 4. app/exception/BusinessException.php
#    旧: namespace app\common\exception;
#    新: namespace app\exception;
```

**具体修改方式**（每个文件替换一行）：
- `NoticeEvents.php`: `app\common\constants` → `app\constants`
- `BaseController.php`: `app\common\controller` → `app\controller`
- `DataScopeEnum.php`: `app\common\enums` → `app\enums`
- `BusinessException.php`: `app\common\exception` → `app\exception`

### 3.4 use 引用更新

以下文件引用了被移动的类，需要同步更新：

```bash
# 搜索所有引用了 old namespace 的文件
grep -r "app\\common\\constants" app/ --include="*.php" -l
grep -r "app\\common\\controller" app/ --include="*.php" -l
grep -r "app\\common\\enums" app/ --include="*.php" -l
grep -r "app\\common\\exception" app/ --include="*.php" -l
grep -r "BaseController" app/ --include="*.php" -l
grep -r "BusinessException" app/ --include="*.php" -l
grep -r "DataScopeEnum" app/ --include="*.php" -l
```

将搜索结果中的 `use app\common\xxx` 替换为 `use app\xxx`。

---

## 四、P2 修复：新增 DTO/VO 目录（可选增强）

如果 Controller 的请求参数较多，建议新增 DTO 目录：

```text
app/system/dto/          # 数据传输对象（新建）
├── UserDTO.php          # 用户相关请求参数
├── RoleDTO.php          # 角色相关请求参数
└── LoginDTO.php         # 登录请求参数

app/auth/dto/            # 认证模块 DTO（新建）
└── AuthDTO.php
```

**DTO 类模板**：
```php
<?php
declare(strict_types=1);

namespace app\system\dto;

class UserDTO
{
    public ?string $username = null;
    public ?string $nickname = null;
    public ?int $deptId = null;
    public array $roleIds = [];
}
```

> 此项为可选优化，不影响现有功能。

---

## 五、README.md 最终版

完整替换 `youlai-think/README.md` 的「目录结构」部分：

```markdown
## 目录结构

> 遵循 [ThinkPHP 8 官方目录结构](https://doc.thinkphp.cn/v8_0/directory_structure.html) 规范设计。
> 所有目录和文件名均采用 **全小写 + snake_case** 命名风格（类名除外采用 PascalCase）。

```text
youlai-think/
├─ app/                           # 应用目录
│  ├─ auth/                       # 认证模块
│  │  ├─ controller/              # 控制器
│  │  │  ├─ AuthController.php
│  │  │  └─ WxMaAuthController.php
│  │  └─ service/                 # 服务层
│  │     ├─ AuthService.php
│  │     └─ WxMaAuthService.php
│  │
│  ├─ system/                     # 系统核心模块
│  │  ├─ annotation/              # 自定义注解
│  │  ├─ controller/              # 控制器
│  │  ├─ enums/                   # 枚举定义
│  │  ├─ model/                   # 数据模型
│  │  ├─ service/                 # 业务服务
│  │  └── validate/              # 参数校验
│  │
│  ├─ common/                     # 公共共享模块
│  │  ├─ middleware/              # 全局中间件
│  │  ├─ model/                   # 基础模型
│  │  ├─ traits/                  # Trait 复用
│  │  ├─ util/                    # 工具函数
│  │  ├─ validate/                # 校验基类
│  │  └─ web/                     # 统一响应封装
│  │
│  ├─ constants/                  # 应用常量
│  ├─ controller/                 # 基础控制器
│  ├─ enums/                      # 全局枚举
│  ├─ exception/                  # 异常类
│  ├─ codegen/                    # 代码生成
│  ├─ file/                       # 文件上传
│  ├─ AppService.php              # 应用注册
│  ├─ ExceptionHandle.php         # 全局异常
│  ├─ middleware.php              # 中间件注册
│  └─ provider.php                # 服务提供者
│
├─ config/                         # 配置文件
├─ extend/                         # 扩展类库
├─ public/                         # Web 入口
├─ route/                          # 路由定义
│  └─ app.php                     # 路由入口（含版本分组 v1）
├─ sql/                            # 数据库脚本
├─ runtime/                        # 运行时日志
├─ .env                            # 环境变量
└─ composer.json                   # 依赖管理
```

### 设计原则

| 原则 | 说明 |
|------|------|
| **单一职责** | 每个目录只放一类文件，不混用 |
| **依赖方向** | system → common → PHP 内置，禁止反向依赖 |
| **命名规范** | 目录/文件全小写，类名 PascalCase，方法 camelCase |
| **业务隔离** | auth 与 system 无直接依赖，通过 common 交互 |
```

---

## 六、验证清单（执行完所有修改后逐项检查）

```bash
echo "=== 1. 大写目录检查 ==="
find app -type d | while read d; do bn=$(basename "$d"); if [[ "$bn" != "$(echo "$bn" | tr 'A-Z' 'a-z')" ]]; then echo "❌ 大写目录: $d"; fi; done
echo "(无输出 = 通过)"

echo ""
echo "=== 2. namespace 小写检查 ==="
grep -rn "namespace.*\\\\[A-Z]" app/ --include="*.php"
echo "(无输出 = 通过)"

echo ""
echo "=== 3. use 引用检查 ==="
grep -rn "use app\\common\\(controller\|enums\|exception\|constants\)" app/ --include="*.php"
echo "(无输出 = 通过)"

echo ""
echo "=== 4. 目录完整性检查 ==="
for d in auth system common constants controller enums exception codegen file; do
  if [ -d "app/$d" ]; then echo "✅ app/$d"; else echo "❌ 缺失 app/$d"; fi
done

echo ""
echo "=== 5. 功能验证 ==="
php think version && echo "✅ ThinkPHP 正常" || echo "❌ ThinkPHP 异常"
composer dump-autoload -o && echo "✅ 自动加载正常" || echo "❌ 自动加载异常"
```

---

## 七、执行顺序总结

| 步骤 | 操作 | 数量 |
|------|------|------|
| 1 | `git mv` 重命名大写目录 | **10 个命令** |
| 2 | `git mv` 从 common 迁出子目录到 app | **4 个命令** |
| 3 | 修改 4 个迁移文件的 namespace | **4 个文件** |
| 4 | 更新所有 `use` 引用 | **N 个文件**（由 grep 结果决定） |
| 5 | 替换 README.md 目录树部分 | **1 次** |
| 6 | 运行验证清单 | **5 项检查** |

**预计耗时**: 15-20 分钟

---

# youlai-django 完美重构方案

> 本文档为 AI 执行指南，按顺序执行即可达到处女座级完美。
> 所有注释和文档不得出现 AI 风格表述，不得引用外部项目链接。

---

## 一、评估总览

| 维度 | 评分 | 说明 |
|------|------|------|
| 目录命名规范 | **100/100** | 全部小写，无违规 |
| Settings 分裂 | **95/100** | base/dev/prod 三级继承清晰，prod 有安全强制校验 |
| core/ 组织 | **90/100** | exceptions/middleware/permissions 子包划分合理 |
| apps/ 模块化 | **80/100** | system 下 8 个子域清晰，但 utils 放置有争议 |
| README 准确性 | **60/100** | websocket/sse 名称错误，目录树不完整 |

**综合得分：85/100**（高于 think 的 78，略低于 boot 的 88）

---

## 二、问题诊断总览

| # | 问题 | 严重度 | 影响 |
|---|------|--------|------|
| P0 | README 第 58 行 `websocket/` 实际是 `sse/` | **致命** | 文档误导 |
| P1 | `core/middleware.py` 与 `core/middleware/request_context.py` 内容**完全重复** | 高 | 维护混乱，改一个忘一个 |
| P2 | `apps/codegen/codegen/` 是完全空目录（0 个文件） | 中 | 冗余垃圾 |
| P3 | `apps/system/utils/` 包含 6 个跨域工具函数，放在 system 下可能引起循环依赖风险 | 中 | 架构隐患 |
| P4 | `apps/codegen/views.py` 单文件 40.58KB（1205 行），项目中最大文件 | 低 | 可维护性差 |
| P5 | README 目录树缺少 auth/models/、auth/utils/、system/utils/、system/signals.py、system/migrations/、file/file/、codegen/templates/、core/middleware/ 子目录、core/permissions/ 详细文件等 | 低 | 文档不全 |

---

## 三、P0 修复：README 目录名称错误（必须先做）

### 3.1 问题描述

README.md 第 58 行声明：
```
│  └─ websocket/              # WebSocket 模块
```

实际目录名为 `sse/`（Server-Sent Events），内含 registry/service/views 等 6 个文件，共 12.4KB。

### 3.2 修复操作

将 README.md 第 58 行的：
```
│  └─ websocket/              # WebSocket 模块
```
替换为：
```
│  └─ sse/                    # SSE 服务端推送模块
```

同时删除第 41 行的外部参考链接（用户明确要求不引用外部项目），替换为自主声明。

---

## 四、P1 修复：middleware.py 重复代码（核心优化）

### 4.1 问题描述

`core/middleware.py`（114 行）与 `core/middleware/request_context.py`（114 行）内容**完全一致**，都定义了：
- `request_id_var`, `user_id_var`, `path_var` 等 ContextVar
- `RequestContextFilter` 类
- `RequestContextMiddleware` 类
- `__all__` 导出列表

当前 settings/base.py 第 47 行引用的是 `core.middleware.RequestContextMiddleware`（即根级的 middleware.py），而 `core/middleware/request_context.py` 是冗余副本。

### 4.2 修复方案

**方案 A（推荐）：保留子包版本，清空根级文件**

将 `core/middleware.py` 改为纯重导出，实际实现只保留在 `core/middleware/request_context.py`：

```python
# core/middleware.py（修改后）
"""中间件模块入口。

统一从子包导出，避免重复定义。
"""

from core.middleware.request_context import (  # noqa: F401
    RequestContextFilter,
    RequestContextMiddleware,
    request_id_var,
    user_id_var,
    path_var,
    method_var,
    status_var,
    cost_ms_var,
)

from core.middleware.rate_limit import RateLimitMiddleware  # noqa: F401

__all__ = [
    "RequestContextFilter",
    "RequestContextMiddleware",
    "RateLimitMiddleware",
    "request_id_var",
    "user_id_var",
    "path_var",
    "method_var",
    "status_var",
    "cost_ms_var",
]
```

**优点**：
- 现有引用 `core.middleware.RequestContextMiddleware` 无需改动
- 消除重复代码
- 子包结构更清晰（rate_limit / request_context 各自独立）

### 4.3 验证

```bash
cd d:/project/youlai/youlai-admin/youlai-django

# 确认导入正常
python -c "from core.middleware import RequestContextMiddleware; print('OK')"

# 运行项目确认无报错
python manage.py check
```

---

## 五、P2 修复：清理空目录 codegen/codegen/

### 5.1 操作命令

```bash
cd d:/project/youlai/youlai-admin/youlai-django

# 删除空目录
rmdir "apps\codegen\codegen"

# git 追踪删除
git rm -r "apps/codegen/codegen/" 2>nul || echo "目录未被 git 追踪，直接删除即可"
```

> 如果 git 报错说明该空目录原本就未被版本控制，直接删除即可。

---

## 六、P3 修复：system/utils/ 放置位置评估（建议调整）

### 6.1 当前情况

`apps/system/utils/` 包含 6 个文件：

| 文件 | 大小 | 功能 | 被引用位置 |
|------|------|------|-----------|
| `decorators.py` | 5.87 KB | `@operation_log` 操作日志装饰器 | system 各 views.py |
| `email_utils.py` | 4.83 KB | 邮箱验证码生成/发送/校验 | 可能被 auth 引用 |
| `exception_handler.py` | 98 B | 重导出 `global_exception_handler` | 仅桥接用 |
| `mobile_utils.py` | 7.33 KB | 手机短信验证码（阿里云 SMS） | 可能被 auth 引用 |
| `rate_limit.py` | 1.39 KB | `@ip_rate_limit` IP 限流装饰器 | system views |
| `utils.py` | 618 B | IP 地理位置（百度 API） | 被 decorators.py 引用 |

### 6.2 问题分析

这些工具函数具有**跨模块通用性**：
- `email_utils.py` 和 `mobile_utils.py` 既可能被 system 用户管理引用，也可能被 auth 登录注册引用
- 放在 `system/utils/` 下会导致 auth → system 的反向依赖（auth 本应独立于 system）

### 6.3 建议方案（可选）

将 `email_utils.py` 和 `mobile_utils.py` 迁移到新建的 `utils/` 应用或放到 `core/` 下：

**目标结构**：
```
apps/
├── utils/                          # ★ 新建：跨应用工具集
│   ├── __init__.py
│   ├── email_utils.py              # 从 system/utils 迁入
│   └── mobile_utils.py             # 从 system/utils 迁入
│
├── system/
│   └── utils/                      # 保留纯 system 内部工具
       ├── __init__.py
       ├── decorators.py            # 操作日志装饰器（仅 system 内部使用）
       ├── exception_handler.py     # 异常处理桥接
       ├── rate_limit.py            # IP 限流（system 视图专用）
       └── utils.py                 # IP 地理位置
```

### 6.4 迁移步骤（如决定执行）

```bash
cd d:/project/youlai/youlai-admin/youlai-django

# 1. 创建 utils 应用目录
mkdir apps/utils

# 2. 移动文件
git mv "apps/system/utils/email_utils.py"      "apps/utils/email_utils.py"
git mv "apps/system/utils/mobile_utils.py"     "apps/utils/mobile_utils.py"

# 3. 创建 apps/utils/__init__.py
# 4. 更新所有 from apps.system.utils.email_utils import ... 为 from apps.utils.email_utils import ...
# 5. 更新所有 from apps.system.utils.mobile_utils import ... 为 from apps.utils.mobile_utils import ...

# 6. 在 config/settings/base.py 的 INSTALLED_APPS 中添加 'apps.utils',
```

> 此项为可选优化。如果目前 auth 模块没有直接引用 email_utils/mobile_utils，可以暂不调整，后续有需求时再迁移。

---

## 七、P4 修复：codegen/views.py 拆分评估（长期优化）

### 7.1 当前状况

`apps/codegen/views.py` 达到 **40.58 KB / 1205 行**，包含：

| 函数/类 | 功能 | 大致行数 |
|---------|------|---------|
| `VStr` 类 | 字符串包装 | 3 行 |
| `_table_exists()` | 表存在检查 | 15 行 |
| `_codegen_tables()` | 动态检测表结构 | 5 行 |
| `_table_has_column()` | 列存在检查 | 15 行 |
| `_get_gen_config_extra()` | 读取额外配置 | 15 行 |
| `_update_gen_config_extra()` | 更新额外配置 | 20 行 |
| `CodeGenViewSet.list()` | 列表接口 | ~60 行 |
| `CodeGenViewSet.retrieve()` | 详情接口 | ~80 行 |
| `CodeGenViewSet.update()` | 更新配置 | ~60 行 |
| `CodeGenViewSet.preview()` | 预览代码 | ~150 行 |
| `CodeGenViewSet.download()` | 打包下载 | ~200 行 |
| `CodeGenViewSet.sync()` | 同步表结构 | ~80 行 |
| `CodeGenViewSet.batch_gen()` | 批量生成 | ~200 行 |

### 7.2 建议拆分方案

```
apps/codegen/
├── views.py                # CodeGenViewSet（只保留接口调度逻辑，~200 行）
├── services/
│   ├── __init__.py
│   ├── table_service.py    # _table_exists, _codegen_tables, _table_has_column (~50 行)
│   ├── config_service.py   # _get_gen_config_extra, _update_gen_config_extra (~40 行)
│   ├── preview_service.py  # preview() 内部逻辑 (~150 行)
│   ├── download_service.py # download() + batch_gen() 打包逻辑 (~300 行)
│   └── sync_service.py     # sync() 同步逻辑 (~80 行)
```

> 此项属于重构优化，不影响功能。可在后续迭代中逐步拆分，不建议一次性大规模改动。

---

## 八、README.md 最终版

完整替换 `youlai-django/README.md` 的「项目目录」部分（第 39-82 行）：

```markdown
## 📁 项目目录

> 遵循 Django 多环境配置规范设计。
> 所有目录和文件名均采用 **全小写 + snake_case** 命名风格。

```text
youlai-django/
├─ apps/                           # 业务应用（模块化）
│  ├─ auth/                        # 认证模块(登录/Token/微信小程序)
│  │  ├─ models/                   # 用户会话、社交登录模型
│  │  │  ├─ user_session.py
│  │  │  └─ user_social.py
│  │  └─ utils/                    # 认证工具集(JWT/Redis Token/Session)
│  │     ├─ auth_utils.py
│  │     ├─ jwt_authentication.py
│  │     ├─ redis_token_authentication.py
│  │     └─ session_utils.py
│  │
│  ├─ system/                      # 系统核心模块
│  │  ├─ users/                    # 用户管理
│  │  ├─ roles/                    # 角色管理
│  │  ├─ menus/                    # 菜单管理
│  │  ├─ dept/                     # 部门管理
│  │  ├─ dicts/                    # 字典管理
│  │  ├─ configs/                  # 系统配置
│  │  ├─ notices/                  # 通知公告
│  │  ├─ logs/                     # 日志管理(含访问统计)
│  │  ├─ migrations/               # 数据库迁移
│  │  ├─ signals.py                # Django 信号(缓存刷新触发器)
│  │  ├─ models.py                 # 数据模型汇总导出
│  │  └─ utils/                    # 系统内部工具集
│  │     ├─ decorators.py          # 操作日志装饰器
│  │     ├─ email_utils.py         # 邮箱验证码
│  │     ├─ mobile_utils.py        # 手机短信验证码
│  │     ├─ rate_limit.py          # IP 限流装饰器
│  │     └─ utils.py               # IP 地理定位
│  │
│  ├─ file/                        # 文件上传
│  │  └─ file/                     # 文件业务子包
│  │     ├─ selectors.py
│  │     ├─ services.py
│  │     ├─ urls.py
│  │     └─ views.py
│  │
│  ├─ codegen/                     # 代码生成
│  │  ├─ templates/                # 代码模板
│  │  │  ├─ backend/               # Django 后端模板(.vm)
│  │  │  └─ frontend/              # Vue3 前端模板
│  │  │     ├─ js/                 # JavaScript 版
│  │  │     └─ ts/                 # TypeScript 版
│  │  ├─ constants.py
│  │  ├─ models.py
│  │  ├─ urls.py
│  │  └─ views.py                  # 代码生成主视图
│  │
│  └─ sse/                         # SSE 服务端推送
│     ├─ registry.py               # 连接注册中心
│     ├─ service.py                # SSE 服务层
│     ├─ urls.py
│     └─ views.py
│
├─ core/                           # 公共基础能力
│  ├─ exceptions/                  # 异常处理
│  │  ├─ business.py               # 业务异常类
│  │  └─ handler.py                # 全局异常处理器(DRF 入口)
│  ├─ permissions/                 # 权限控制
│  │  ├─ data_scope.py             # 数据权限控制(多角色并集)
│  │  ├─ decorators.py             # 权限声明装饰器
│  │  ├─ perms.py                  # HasPerm 权限校验类
│  │  └─ role_perm_service.py      # 角色权限缓存服务(Redis Hash)
│  ├─ middleware/                   # 中间件
│  │  ├─ request_context.py        # 请求上下文(ContextVar)
│  │  └─ rate_limit.py             # IP 限流(Redis 固定窗口)
│  ├─ response.py                  # 统一响应格式({code,msg,data})
│  ├─ viewsets.py                  # 基础视图集(BaseViewSet/BaseModelViewSet)
│  ├─ serializers.py               # 序列化扩展(BigInt 转字符串)
│  ├─ pagination.py                # 分页器(pageNum/pageSize)
│  ├─ ordering.py                  # 安全排序(白名单防注入)
│  ├─ openapi.py                   # OpenAPI 辅助函数
│  └─ validators.py                # 公共验证器(手机号/邮箱)
│
├─ config/                         # 项目配置
│  ├─ settings/                    # 环境配置(base/dev/prod)
│  │  ├─ base.py                   # 通用配置(253行, 含 JWT/Redis/OSS/SMS 等)
│  │  ├─ dev.py                    # 开发配置(DEBUG=True, 数据库/Redis/邮件)
│  │  └─ prod.py                   # 生产配置(安全头强制/require_env 校验)
│  ├─ urls.py                      # 全局路由
│  ├─ asgi.py                      # ASGI 入口
│  ├─ wsgi.py                      # WSGI 入口
│  ├─ env.py                       # 环境变量读取工具
│  └─ schema.py                    # OpenAPI Schema 配置
│
├─ sql/                            # 数据库脚本
│  └─ mysql/
│     └─ youlai_admin_django.sql
│
├─ manage.py                       # Django 管理入口
├─ .env                            # 环境变量
├─ requirements.txt                # Python 依赖
├─ pyproject.toml                  # 项目元数据
├─ Dockerfile                      # Docker 镜像构建
└─ docker-compose.yml              # 容器编排
```

### 设计原则

| 原则 | 说明 |
|------|------|
| **单一职责** | 每个 app 只负责一个业务域，内部按 models/views/services/serializers 组织 |
| **依赖方向** | apps → core → Django 内置，禁止 app 间的循环依赖 |
| **命名规范** | 目录/文件全小写下划线分隔，类名 PascalCase，方法/函数 snake_case |
| **配置隔离** | base 定义共享配置，dev/prod 仅覆盖差异项，prod 强制安全校验 |
| **日志规范** | 所有请求自动携带 request_id/user_id/path/method/status/cost_ms |
```

---

## 九、验证清单（执行完所有修改后逐项检查）

```bash
cd d:/project/youlai/youlai-admin/youlai-django

echo "=== 1. 大写目录检查 ==="
find apps core config -type d | while read d; do bn=$(basename "$d"); if [[ "$bn" != "$(echo "$bn" | tr 'A-Z' 'a-z')" ]]; then echo "X $d"; fi; done
echo "(无输出 = 通过)"

echo ""
echo "=== 2. SSE 目录确认 ==="
ls apps/sse/ 2>/dev/null && echo "OK sse/ 存在" || echo "FAIL sse/ 不存在"

echo ""
echo "=== 3. 空目录检查 ==="
if [ -d "apps/codegen/codegen" ]; then echo "FAIL codegen/codegen 仍存在"; else echo "OK 无空目录残留"; fi

echo ""
echo "=== 4. middleware 重复检查 ==="
diff <(grep -c "class RequestContextMiddleware" core/middleware.py) <(grep -c "class RequestContextMiddleware" core/middleware/request_context.py) 2>/dev/null
# middleware.py 应变为重导出文件（无 class 定义）

echo ""
echo "=== 5. Django 系统检查 ==="
python manage.py check && echo "OK Django 系统检查通过" || echo "FAIL Django 系统检查失败"

echo ""
echo "=== 6. 导入完整性检查 ==="
python -c "
from core.middleware import RequestContextMiddleware, RateLimitMiddleware
from core.response import success, error, page_success
from core.viewsets import BaseViewSet, BaseModelViewSet
from core.exceptions.business import BusinessException
from core.permissions.perms import HasPerm
from core.permissions.data_scope import DataScopeEnum
print('OK 所有核心模块导入正常')
"
```

---

## 十、执行顺序总结

| 步骤 | 操作 | 数量 | 必要性 |
|------|------|------|--------|
| 1 | 修正 README `websocket/` → `sse/` + 删除外部链接 | **1 处** | 必须 |
| 2 | 重构 `core/middleware.py` 为重导出入口 | **1 个文件** | 强烈推荐 |
| 3 | 删除空目录 `apps/codegen/codegen/` | **1 个目录** | 推荐 |
| 4 | 迁移 `system/utils/` 中跨域工具到 `apps/utils/` | **2 个文件 + N 处引用更新** | 可选 |
| 5 | 替换 README.md 完整目录树 | **1 次** | 必须 |
| 6 | 拆分 `codegen/views.py` 大文件 | **~7 个新文件** | 长期优化 |
| 7 | 运行验证清单 | **6 项检查** | 必须 |

**预计耗时（步骤 1+2+3+5+7）**：10-15 分钟

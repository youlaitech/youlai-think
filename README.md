# youlai-think

`youlai-think` 是 youlai-admin 的 ThinkPHP 8 后端实现

目标是与 youlai-boot 保持接口路径与响应结构一致，方便直接对接
`vue3-element-admin`

## 技术栈

- **PHP**: 8.0+
- **框架**: ThinkPHP 8
- **ORM**: think-orm 3.x
- **数据库**: MySQL 8.x（使用本项目 `sql/mysql/youlai_admin.sql`）
- **缓存**: Redis
- **鉴权**: JWT 或 Redis Token（可切换）
- **测试**: PHPUnit + scripts 下的接口 smoke

## 目录结构

```
youlai-think/
  app/
    controller/      控制器
    service/         业务服务
    model/           ORM 模型
    middleware/      中间件（auth perm dataScope demo 等）
    common/          通用组件（安全 结果封装 工具类等）
  config/            配置
  route/             路由
  public/            Web 入口（Nginx/Apache 指向此目录）
  scripts/           本地脚本（smoke run_tests 等）
  tests/             PHPUnit 测试
```

## 环境准备

1. 安装 PHP 8.0+ 与 Composer
2. 安装并启动 MySQL 与 Redis
3. 初始化数据库

   - 创建数据库（如 `youlai_admin`）
   - 导入 `sql/mysql/youlai_admin.sql`

4. 配置项目环境变量

   - 项目根目录已提供 `.env`，直接编辑即可
   - 按项目实际情况配置数据库与 Redis 连接
   - 如需演示写保护，配置 `DEMO_MODE=true`

## 推荐编辑器

- VS Code
  - PHP Intelephense
  - EditorConfig
- PhpStorm

## 安装依赖

在 `youlai-think` 目录执行

```
composer install
```

## 配置说明

项目使用 `env()` 读取环境变量

推荐方式：直接编辑项目根目录 `.env`

常用配置项

- **数据库**（`config/database.php`）
  - `DB_HOST` `DB_PORT` `DB_NAME` `DB_USER` `DB_PASS`
- **鉴权与 Redis**（`config/security.php`）
  - `SECURITY_SESSION_MODE` 取值 `jwt` 或 `redis`
  - `JWT_SECRET` `JWT_ISSUER` `JWT_ACCESS_TTL` `JWT_REFRESH_TTL`
  - `REDIS_HOST` `REDIS_PORT` `REDIS_PASSWORD` `REDIS_DB` `REDIS_PREFIX`
- **演示模式**
  - `DEMO_MODE=true` 开启写保护

## 项目启动

开发环境启动

```
php think run
```

默认访问

```
http://localhost:8000
```

Windows 下如果 `localhost` 被解析为 IPv6（`::1`）导致前端代理或接口访问失败，建
议使用 `127.0.0.1`

如需指定端口

```
php think run -p 8096
```

## 常见问题

- **数据库连不上**

  - 检查 `DB_HOST/DB_PORT/DB_USER/DB_PASS` 是否正确
  - 确认 MySQL 已启动并允许远程或本地连接

- **登录后提示 token 无效**
  - 检查 `SECURITY_SESSION_MODE` 配置
  - `jwt` 模式需要 `JWT_SECRET` 保持稳定
  - `redis` 模式需要 Redis 连接可用

## 默认账号

导入 `sql/mysql/youlai_admin.sql` 后的默认账号

- **root** / **admin** / **test**
- **默认密码**：`123456`

## 最小验收

- `POST /api/v1/auth/login`
- `GET /api/v1/users/me`
- `GET /api/v1/depts/options`
- `GET /api/v1/notices`

你也可以执行本项目自带的 smoke

```
php scripts/smoke_api.php
```

执行 PHPUnit

```
composer test
```

## 项目部署

生产环境推荐 Nginx + PHP-FPM

- **站点根目录** 指向 `public/`
- **伪静态/重写** 按 ThinkPHP 规则配置，将请求转发到 `public/index.php`
- **环境变量** 在服务器侧配置数据库、Redis、演示模式等参数

部署后建议做一次接口健康检查

```
php scripts/smoke_api.php
```

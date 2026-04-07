<div align="center">
   <img alt="logo" width="100" height="100" src="https://foruda.gitee.com/images/1733417239320800627/3c5290fe_716974.png">
   <h2>youlai-think</h2>
   <img alt="PHP" src="https://img.shields.io/badge/PHP-8.0+-blueviolet.svg"/>
   <img alt="ThinkPHP" src="https://img.shields.io/badge/ThinkPHP-8.0-blue.svg"/>
   <a href="https://gitee.com/youlaiorg/youlai-think" target="_blank">
     <img alt="Gitee star" src="https://gitee.com/youlaiorg/youlai-think/badge/star.svg"/>
   </a>
   <a href="https://github.com/youlaitech/youlai-think" target="_blank">
     <img alt="Github star" src="https://img.shields.io/github/stars/youlaitech/youlai-think.svg?style=social&label=Stars"/>
   </a>
</div>

<p align="center">
  <a target="_blank" href="https://vue.youlai.tech/">🖥️ 在线预览</a>
  <span>&nbsp;|&nbsp;</span>
  <a target="_blank" href="https://www.youlai.tech/docs/admin/backend/php/quick-start.html">📑 快速开始</a>
  <span>&nbsp;|&nbsp;</span>
  <a target="_blank" href="https://www.youlai.tech">🌐 官网</a>
</p>

## 项目简介

`youlai-think` 是 **[vue3-element-admin](https://gitee.com/youlaiorg/vue3-element-admin)** 配套的 PHP 后端实现，基于 ThinkPHP 8 + PHP 8 + JWT + Redis + MySQL 构建，是 youlai 全家桶的重要组成部分。

- **技术栈**：ThinkPHP 8 + PHP 8，轻量高效
- **安全认证**：JWT 无状态认证 + Redis 会话双模式
- **权限管理**：RBAC 权限模型，菜单/按钮/接口三级权限
- **模块能力**：用户、角色、菜单、部门、字典、通知、日志等核心模块

## 项目源码

| 项目 | Gitee | GitHub | GitCode |
| --- | --- | --- | --- |
| ✅ PHP 后端 | [youlai-think](https://gitee.com/youlaiorg/youlai-think) | [youlai-think](https://github.com/youlaitech/youlai-think) | [youlai-think](https://gitcode.com/youlai/youlai-think) |
| vue3 前端 | [vue3-element-admin](https://gitee.com/youlaiorg/vue3-element-admin) | [vue3-element-admin](https://github.com/youlaitech/vue3-element-admin) | [vue3-element-admin](https://gitcode.com/youlai/vue3-element-admin) |
| uni-app 移动端 | [youlai-app](https://gitee.com/youlaiorg/youlai-app) | [youlai-app](https://github.com/youlaitech/youlai-app) | [youlai-app](https://gitcode.com/youlai/youlai-app) |

## 目录结构

> 遵循 [ThinkPHP 8 官方目录结构](https://doc.thinkphp.cn/v8_0/directory_structure.html) 规范设计。

```text
youlai-think/
├─ app/                       # 应用目录
│  ├─ auth/                   # 认证模块（登录/鉴权）
│  ├─ system/                 # 系统模块（用户/角色/菜单/部门/字典/通知/日志）
│  ├─ codegen/                # 代码生成模块
│  ├─ file/                   # 文件上传模块
│  ├─ common/                 # 公共模块（中间件/模型/工具/验证器/响应）
│  ├─ constants/              # 常量定义
│  ├─ controller/             # 控制器基类
│  ├─ enums/                  # 枚举定义
│  ├─ exception/              # 异常类
│  ├─ AppService.php          # 应用服务注册
│  ├─ ExceptionHandle.php     # 全局异常处理
│  ├─ middleware.php          # 全局中间件
│  └─ provider.php            # 服务提供者
├─ config/                    # 配置文件
├─ extend/                    # 扩展类库（Redis/SSE）
├─ public/                    # Web 入口
├─ route/                     # 路由定义（含版本分组）
├─ sql/                       # 数据库初始化脚本
├─ runtime/                   # 运行时日志与缓存
├─ .env                       # 环境变量
└─ composer.json              # 依赖管理
```

## 快速启动

> 本地未配置 MySQL、Redis 不影响启动，项目默认连接线上公共环境，方便快速体验。

```bash
# 1. 克隆项目
git clone https://gitee.com/youlaiorg/youlai-think.git
cd youlai-think

# 2. 安装依赖
composer install

# 3. 启动 API 服务（端口 8000）
php think run

# 4. 启动 SSE 服务（端口 8001，用于字典刷新、在线用户等实时推送）
php bin/sse_server.php start
```

**接口文档**：Swagger UI `http://localhost:8000/swagger-ui/`

**前端联调**：在 `vue3-element-admin` 的 `.env.development` 中配置 SSE 独立端口：

```env
VITE_APP_API_URL=http://localhost:8000
VITE_APP_SSE_ENABLED=true
VITE_APP_SSE_PORT=8001
```

> 更多配置和部署说明请查阅 [快速开始文档](https://www.youlai.tech/docs/admin/backend/php/quick-start.html)。

## 技术交流

关注「有来技术」公众号，点击菜单【交流群】获取微信群二维码：

<div align="center">
  <img src="https://foruda.gitee.com/images/1737108820762592766/3390ed0d_716974.png" width="280">
</div>

> 二维码过期？添加微信 **`haoxianrui`**，备注「前端/后端/全栈」即可拉你入群。

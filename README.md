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
  <a target="_blank" href="https://www.youlai.tech/youlai-think">📑 阅读文档</a>
  <span>&nbsp;|&nbsp;</span>
  <a target="_blank" href="https://www.youlai.tech">🌐 官网</a>
</p>

## 📢 项目简介

`youlai-think` 是 **[vue3-element-admin](https://gitee.com/youlaiorg/vue3-element-admin)** 配套的 PHP 后端实现，基于 ThinkPHP 8, PHP 8, JWT, Redis, MySQL 构建，是 youlai 全家桶的重要组成部分。

- **🚀 技术栈**：ThinkPHP 8 + PHP 8，轻量高效、上手成本低
- **🔐 安全认证**：JWT 无状态认证 + Redis 会话双模式，支持会话治理
- **🔑 权限管理**：RBAC 权限模型，菜单/按钮/接口三级权限统一治理
- **🛠️ 模块能力**：用户、角色、菜单、部门、字典、日志等核心模块开箱即用

## 🌈 项目源码

| 项目 | Gitee | GitHub | GitCode |
| --- | --- | --- | --- |
| ✅ PHP 后端 | [youlai-think](https://gitee.com/youlaiorg/youlai-think) | [youlai-think](https://github.com/youlaitech/youlai-think) | [youlai-think](https://gitcode.com/youlai/youlai-think) |
| vue3 前端 | [vue3-element-admin](https://gitee.com/youlaiorg/vue3-element-admin) | [vue3-element-admin](https://github.com/youlaitech/vue3-element-admin) | [vue3-element-admin](https://gitcode.com/youlai/vue3-element-admin) |
| uni-app 移动端 | [vue-uniapp-template](https://gitee.com/youlaiorg/vue-uniapp-template) | [vue-uniapp-template](https://github.com/youlaitech/vue-uniapp-template) | [vue-uniapp-template](https://gitcode.com/youlai/vue-uniapp-template) |

## 📁 目录结构

> 参考：[ThinkPHP 8.0 官方目录结构](https://doc.thinkphp.cn/v8_0/directory_structure.html)

```text
youlai-think/
├─ app/                       # 应用目录
│  ├─ BaseController.php      # 基础控制器
│  ├─ ExceptionHandle.php     # 异常处理
│  ├─ common.php              # 全局函数
│  ├─ middleware.php          # 全局中间件定义
│  ├─ provider.php            # 服务提供
│  ├─ event.php               # 事件定义
│  │
│  ├─ auth/                   # 认证模块
│  │  ├─ controller/
│  │  └─ service/
│  │
│  ├─ system/                 # 系统模块
│  │  ├─ controller/
│  │  ├─ service/
│  │  ├─ model/
│  │  └─ validate/
│  │
│  ├─ file/                   # 文件模块
│  │  ├─ controller/
│  │  └─ service/
│  │
│  ├─ codegen/                # 代码生成模块
│  │  ├─ controller/
│  │  ├─ service/
│  │  └─ templates/
│  │
│  ├─ swagger/                # Swagger 文档
│  │
│  └─ common/                 # 公共代码
│     ├─ constants/           # 常量
│     ├─ enums/               # 枚举
│     ├─ exception/           # 异常
│     ├─ middleware/          # 中间件
│     ├─ model/               # 公共模型
│     ├─ redis/               # Redis
│     ├─ security/            # 认证/权限
│     ├─ traits/              # 复用特性
│     ├─ util/                # 工具类
│     ├─ web/                 # Web 相关
│     └─ websocket/           # WebSocket
│
├─ config/                    # 配置目录
├─ public/                    # 入口目录
├─ route/                     # 路由定义
├─ runtime/                   # 运行时
├─ vendor/                    # Composer 类库
├─ .env                       # 环境变量
└─ composer.json              # 依赖管理
```

## 🚀 快速启动

### 1. 环境准备

| 技术 | 版本/说明 | 安装文档 |
| --- | --- | --- |
| **PHP** | `8.0` 或更高版本 | [Windows (XAMPP)](https://www.apachefriends.org/index.html) / [macOS (brew)](https://formulae.brew.sh/formula/php) |
| **MySQL** | `5.7` 或 `8.x` | [Windows](https://youlai.blog.csdn.net/article/details/133272887) / [Linux](https://youlai.blog.csdn.net/article/details/130398179) |
| **Redis** | `7.x` | [Windows](https://youlai.blog.csdn.net/article/details/133410293) / [Linux](https://youlai.blog.csdn.net/article/details/130439335) |
| **Composer** | `2.x`（PHP 依赖管理工具） | [官方下载](https://getcomposer.org/) |

> 💡 **贴心小提示**：本地未配置 MySQL、Redis 不影响启动，项目默认会连接 [youlai](https://www.youlai.tech) 线上公共环境运行，方便您快速体验。

### 2. 开发工具

**PhpStorm** (推荐):

- JetBrains 官方出品的专业 PHP IDE，开箱即用。

**VS Code**:

- **PHP Intelephense**: 提供代码智能提示、补全、格式化等核心功能。
- **PHP Debug**: Xdebug 调试支持。

### 3. 初始化数据库

使用数据库客户端执行 `sql/mysql/youlai_admin.sql` 脚本，完成数据库和基础数据的初始化。

### 4. 修改配置

复制 `.env.example` 为 `.env`，并修改 MySQL/Redis 连接信息。

### 5. 启动项目

```bash
# 1. 克隆项目
git clone https://gitee.com/youlaiorg/youlai-think.git
cd youlai-think

# 2. 安装依赖
composer install

# 3. 启动服务
php think run
```

启动成功后，访问 `http://localhost:8000/`，如返回 `ok` 即表示服务已正常运行。

### 6. WebSocket

项目基于 `topthink/think-worker` 提供 WebSocket 服务，默认路径为 `/ws`。

```bash
# 启动 worker（HTTP 与 WebSocket 共用端口）
php think worker
```

> Windows 下 `think-worker` 存在限制，建议在 Linux/macOS 环境或 Docker 中启动

### 6. 接口文档

- Swagger UI：`http://localhost:8000/swagger`
- OpenAPI JSON：`http://localhost:8000/swagger/openapi.json`

## 🤝 前端整合

`youlai-think` 与 `vue3-element-admin` 前后端协议完全兼容，可无缝对接。

```bash
# 1. 获取前端项目
git clone https://gitee.com/youlaiorg/vue3-element-admin.git
cd vue3-element-admin

# 2. 安装依赖
pnpm install

# 3. 配置后端地址 (编辑 .env.development)
VITE_APP_API_URL=http://localhost:8000

# 4. 启动前端
pnpm run dev
```

- **访问地址**: [http://localhost:3000](http://localhost:3000)
- **登录账号**: `admin` / `123456`

## 🐳 项目部署

### 1. Nginx + PHP-FPM

- **站点根目录** 指向 `public/`
- **伪静态/重写** 按 ThinkPHP 规则配置，将所有请求转发到 `public/index.php`

### 2. Docker 部署

```bash
# 构建镜像
docker build -t youlai-think:latest .

# 运行容器
docker run -d -p 8000:8000 --name youlai-think youlai-think:latest
```

## 💖 技术交流

① 关注「有来技术」公众号，点击菜单 **交流群** 获取加群二维码（此举防止广告进群，感谢理解和支持）。

② 直接添加微信 **`haoxianrui`** 备注「前端/后端/全栈」。

![有来技术公众号](https://foruda.gitee.com/images/1737108820762592766/3390ed0d_716974.png)

**博客**：[CSDN](https://youlai.blog.csdn.net/) | [稀土掘金](https://juejin.cn/user/4187394044331261) | [博客园](https://www.cnblogs.com/haoxianrui) | [51CTO](https://blog.51cto.com/youlai) | [阿里云](https://developer.aliyun.com/profile/r6wxjk6qzasuy) | [腾讯云社区](https://cloud.tencent.com/developer/user/10500752) | [知乎](https://www.zhihu.com/people/haoxr)

**官网**：https://www.youlai.tech/

**代码仓库**：[Gitee](https://gitee.com/youlaiorg) | [AtomGit](https://atomgit.com/youlai) | [GitHub](https://github.com/youlaitech)

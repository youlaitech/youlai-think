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

**[youlai-think](https://gitee.com/youlaiorg/youlai-think)** 是 **[vue3-element-admin](https://gitee.com/youlaiorg/vue3-element-admin)** 的 ThinkPHP 8 后端实现，接口路径与返回结构完全对齐，可直接为前端提供后端服务。

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

## 📁 项目目录

<details>
<summary> 目录结构 </summary>

```text
youlai-think/
├─ app/                       # 应用核心目录
│  ├─ controller/             # 控制器 (用户/角色/菜单等)
│  ├─ service/                # 业务服务
│  ├─ model/                  # 数据模型
│  ├─ common/                 # 公共模块(认证/权限/异常)
│  └─ ...                     # 其他(中间件/验证器等)
├─ config/                    # 配置文件
├─ public/                    # Web 入口目录
├─ route/                     # 路由定义
├─ runtime/                   # 运行时缓存
├─ .env                       # 环境变量
└─ composer.json              # 依赖管理
```

</details>

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

启动成功后，访问 `http://localhost:8000`，如看到 ThinkPHP 欢迎页面即表示成功。

### 6. 接口文档（Swagger）

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

- **问题反馈**：[Gitee Issues](https://gitee.com/youlaiorg/youlai-think/issues)
- **技术交流群**：[QQ 群：950387562](https://qm.qq.com/cgi-bin/qm/qr?k=U57IDw7ufwuzMA4qQ7BomwZ44hpHGkLg)
- **博客教程**：[https://www.youlai.tech](https://www.youlai.tech)

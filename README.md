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

**[youlai-think](https://gitee.com/youlaiorg/youlai-think)** 是 `vue3-element-admin` 配套的 PHP 后端实现，基于 ThinkPHP 8, PHP 8, JWT, Redis, MySQL 构建，是 **youlai 全家桶** 的重要组成部分。

- **🚀 轻量高效**: 基于 ThinkPHP 8 框架，提供简单、高效的 Web API 开发体验。
- **🔐 双重认证**: 支持 JWT 和 Redis Token 两种会话模式，可根据业务需求灵活切换。
- **🔑 权限管理**: 内置基于 RBAC 的权限模型，精确控制接口和按钮权限。
- **🛠️ 功能模块**: 包含用户、角色、菜单、部门、字典等后台管理系统的核心功能。

## 🌈 项目源码

| 项目类型 | Gitee | Github | GitCode |
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
| **Composer** | `2.x` | [官方下载](https://getcomposer.org/) |

> 💡 **提示**：项目启动依赖 MySQL 和 Redis。为方便快速体验，若本地未配置，项目会默认连接 [youlai](https://www.youlai.tech) 的线上公共环境。

### 2. 开发工具

**PhpStorm** (推荐):

- JetBrains 官方出品的专业 PHP IDE，开箱即用。

**VS Code**:

- **PHP Intelephense**: 提供代码智能提示、补全、格式化等核心功能。
- **PHP Debug**: Xdebug 调试支持。

### 3. 启动项目

```bash
# 1. 克隆项目
git clone https://gitee.com/youlaiorg/youlai-think.git
cd youlai-think

# 2. 安装依赖
composer install

# 3. 初始化数据库
#    请使用数据库客户端执行 sql/mysql/youlai_admin.sql

# 4. 修改配置
#    复制 .example.env 为 .env，并修改 MySQL/Redis 连接信息

# 5. 启动服务
php think run
```

启动成功后，访问 `http://localhost:8000`，如看到 ThinkPHP 欢迎页面即表示成功。

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

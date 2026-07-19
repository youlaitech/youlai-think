<div align="center">



# <img alt="youlai-think" width="28" src="./docs/images/logo/logo.png" align="center"> youlai-think



[English](./README.en.md) · [简体中文](./README.md)



**Enterprise-grade permission management backend based on ThinkPHP 8**



[![PHP](https://img.shields.io/badge/PHP-8.2+-8892BF?logo=php)](https://www.php.net/)

[![ThinkPHP](https://img.shields.io/badge/ThinkPHP-8-00B894)](https://www.thinkphp.cn/)

[![License](https://img.shields.io/badge/License-Apache%202.0-blue?logo=apache)](LICENSE.txt)

[![Gitee Star](https://gitee.com/youlaiorg/youlai-think/badge/star.svg)](https://gitee.com/youlaiorg/youlai-think/stargazers)

[![GitHub Star](https://img.shields.io/github/stars/youlaitech/youlai-think?style=social)](https://github.com/youlaitech/youlai-think)



</div>



![](https://foruda.gitee.com/images/1708618984641188532/a7cca095_716974.png "rainbow.png")



<div align="center">



[🖥️ Live Preview](https://vue.youlai.tech) | [📱 Mobile Preview](https://app.youlai.tech) | [📖 Documentation](https://www.youlai.tech/docs/server/thinkphp/)



</div>



## Introduction



**youlai-think** is an enterprise-grade permission management backend built on ThinkPHP 8. It ships with the frontend [vue3-element-admin](https://gitee.com/youlaiorg/vue3-element-admin) and the mobile app [youlai-app](https://gitee.com/youlaiorg/youlai-app), and is one of **7 language implementations** (Java / Node.js / Go / Python / PHP / C# / Rust) that share the same API specification and database schema. It is suitable for learning, reference, and secondary development of enterprise admin systems.



## Core Features



- 🔐 **Security** — JWT + Redis Token dual-session model, token renewal, multi-device mutual exclusion

- 🛡️ **Fine-grained permissions** — RBAC model governing menus, buttons, and APIs in one place

- ⚡ **Code generator** — one-click generation of full-stack CRUD code

- 📦 **Complete modules** — users, roles, menus, departments, dictionaries, files, message center, operation logs

- 🔌 **Real-time communication** — SSE push: online user count, dictionary sync, notification broadcast



## System Preview



**PC**



<table align="center">

  <tr>

    <td><img alt="PC Preview 1" width="400" src="./docs/images/preview/pc-01.png"></td>

    <td><img alt="PC Preview 2" width="400" src="./docs/images/preview/pc-02.png"></td>

  </tr>

  <tr>

    <td><img alt="PC Preview 3" width="400" src="./docs/images/preview/pc-03.png"></td>

    <td><img alt="PC Preview 4" width="400" src="./docs/images/preview/pc-04.png"></td>

  </tr>

  <tr>

    <td><img alt="PC Preview 5" width="400" src="./docs/images/preview/pc-05.png"></td>

    <td><img alt="PC Preview 6" width="400" src="./docs/images/preview/pc-06.png"></td>

  </tr>

</table>



**Mobile**



<table align="center">

  <tr>

    <td><img alt="App Preview 1" width="200" src="./docs/images/preview/app-01.png"></td>

    <td><img alt="App Preview 2" width="200" src="./docs/images/preview/app-02.png"></td>

    <td><img alt="App Preview 3" width="200" src="./docs/images/preview/app-03.png"></td>

    <td><img alt="App Preview 4" width="200" src="./docs/images/preview/app-04.png"></td>

  </tr>

</table>



## Quick Start



**Requirements**: PHP 8.2+ · Composer · MySQL 8.0+ · Redis 7.x+



1. Clone: `git clone https://gitee.com/youlaiorg/youlai-think.git`

2. Import database: `sql/mysql/youlai_admin.sql`

3. Adjust config (optional, a read-only online data source is configured by default): `.env`

4. Install dependencies: `composer install`

5. Start: `php think run`, then visit http://localhost:8000



Default credentials: `admin` / `123456`



Detailed guide: [Deployment Docs](https://www.youlai.tech/docs/server/thinkphp/deploy)



## Tech Stack



| Tech | Version | Description |

|:-----|:--------|:------------|

| PHP | 8.2+ | Core language |

| ThinkPHP | 8 | Web framework |

| MySQL | 5.7+ / 8.x | Database |

| Redis | 7.x+ | Cache · Session |

| Swagger | — | API docs |



## Directory Structure



```

youlai-think/

├── app/                            # Application directory

│   ├── auth/                       # Auth module (login/authorization)

│   ├── system/                     # System module (user/role/menu/dept/dict/notice/log)

│   ├── codegen/                    # Code generation module

│   ├── file/                       # File management module

│   ├── message/                    # SSE push

│   ├── common/                     # Common module (model/enum/exception/middleware/event)

│   └── BaseController.php          # Base controller

├── config/                         # App config

├── database/                       # DB migrations & seeders

├── public/                         # Web directory (entry/assets)

├── route/                          # Route definitions

├── sql/                            # Database init scripts

├── vendor/                         # Composer dependencies

├── composer.json                   # Composer config

└── .env                            # Environment config

```



## Ecosystem



**Frontend**



| Project | Stack | Description |

|:-----|:------|:------------|

| [vue3-element-admin](https://gitee.com/youlaiorg/vue3-element-admin) | Vue 3 + Element Plus | PC admin frontend (recommended) |

| [youlai-app](https://gitee.com/youlaiorg/youlai-app) | Vue 3 + UniApp | Mobile App |



**Backend**



| Project | Stack | Description |
| [youlai-boot](https://gitee.com/youlaiorg/youlai-boot) | Spring Boot 4 + MyBatis-Plus | Java (recommended) |
| [youlai-nest](https://gitee.com/youlaiorg/youlai-nest) | NestJS + TypeORM | Node.js |
| [youlai-gin](https://gitee.com/youlaiorg/youlai-gin) | Go + Gorm | Go |
| [youlai-django](https://gitee.com/youlaiorg/youlai-django) | Django + DRF | Python |
| [youlai-fastapi](https://gitee.com/youlaiorg/youlai-fastapi) | FastAPI + SQLAlchemy | Python |
| [youlai-think](https://gitee.com/youlaiorg/youlai-think) | ThinkPHP 8 | PHP |
| [youlai-aspnet](https://gitee.com/youlaiorg/youlai-aspnet) | ASP.NET Core | C# |
| [youlai-axum](https://gitee.com/youlaiorg/youlai-axum) | Axum + SeaORM | Rust |
> **youlai-boot** also provides the following variants and branches: [Multi-Tenant](https://gitee.com/youlaiorg/youlai-boot-tenant) (Spring Boot 4) · [MyBatis-Flex](https://gitee.com/youlaiorg/youlai-boot-flex) (Spring Boot 4) · [Spring Boot 3](https://gitee.com/youlaiorg/youlai-boot/tree/spring-boot-3) · [PostgreSQL](https://gitee.com/youlaiorg/youlai-boot/tree/db-pg) · [Multi-Module](https://gitee.com/youlaiorg/youlai-boot/tree/multi-module)

>

> The eight backends share the same **RESTful API specification** and **database schema**, so the frontend can switch seamlessly.



## Documentation



| Resource | Link |

|:-----|:-----|

| 📖 Full docs site | [www.youlai.tech](https://www.youlai.tech/) |

| 🖥️ PC live preview | [vue.youlai.tech](https://vue.youlai.tech) |

| 📱 Mobile live preview | [app.youlai.tech](https://app.youlai.tech) |

| 🔗 Apifox API docs | [apifox.com](https://www.apifox.cn/apidoc/shared-195e783f-4d85-4235-a038-eec696de4ea5) |

| 🔗 Local API docs | [localhost:8000](http://localhost:8000) |



## Contributing



Issues and Pull Requests are welcome! See the [Contribution Guide](https://www.youlai.tech/faq/help).



## License



Released under the [Apache License 2.0](LICENSE.txt); free for commercial use.



---



<table align="center">

  <tr>

    <td align="center">

      <img src="./docs/images/qrcode/wechat-official.png" height="180" alt="Official WeChat Account"><br>

      <sub>Official WeChat Account</sub>

    </td>

    <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>

    <td align="center">

      <img src="./docs/images/qrcode/wechat-mp.jpg" height="180" alt="Mini Program"><br>

      <sub>Mini Program</sub>

    </td>

    <td>&nbsp;&nbsp;&nbsp;&nbsp;</td>

    <td align="center">

      <img src="./docs/images/qrcode/wechat-personal.png" height="180" alt="Add author on WeChat"><br>

      <sub>Add author on WeChat</sub>

    </td>

  </tr>

</table>



<p align="center"><em>Technical discussion · Feedback · Business cooperation</em></p>


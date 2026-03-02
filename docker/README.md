# Docker 环境说明

## 快速启动

在 docker 目录下执行：

```bash
docker-compose up -d
```

## 服务说明

| 服务 | 端口 | 说明 |
|------|------|------|
| MySQL | 3306 | 关系型数据库 |
| Redis | 6379 | 缓存服务 |

## 默认账号

### MySQL
- Root 密码：123456
- 数据库：youlai_admin

### Redis
- 密码：123456

## 目录结构

```
docker/
├── docker-compose.yml
├── README.md
├── mysql/
│   └── data/          # MySQL 数据（自动生成）
└── redis/
    └── data/          # Redis 数据（自动生成）
```

## 环境变量配置

在项目根目录创建 `.env` 文件：

```env
DB_DRIVER=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USER=root
DB_PASS=123456
DB_NAME=youlai_admin
```

## 注意事项

- 数据目录已添加到 .gitignore，不会提交到 Git
- 生产环境请修改默认密码
- SQL 初始化脚本从 `../sql/mysql` 目录读取

# 本地开发环境

## 服务目录

```text
www/service.antifraud.local.com
```

## 自主开发模式

- 能从代码、配置、目录和现有规范判断的事项，直接按项目规范推进。
- 只在破坏性操作、数据风险、目标目录无法判断时再确认。
- 开发过程涉及关键接口、表结构、队列、命令、权限或发布步骤时，归档到 `docs/dev-flow-YYYYMMDD-HHMM-{short-topic}.md`。

## Docker 与数据库

本地环境在 Docker 中运行，宿主机工具连接 MySQL 使用：

```text
host: 127.0.0.1
port: 13306
user: root
password: 使用本机 .env 中的本地开发密码
```

项目本地 `.env` 使用：

```text
APP_TIMEZONE=Asia/Shanghai
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=13306
DB_DATABASE=service_antifraud
DB_USERNAME=root
```

`.env.example` 不记录明文密码。若以后增加 `docker-compose.yml`，应用容器内连接数据库时优先使用 Compose service name 和容器内端口 `3306`。

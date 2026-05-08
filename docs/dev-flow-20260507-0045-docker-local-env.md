# Docker 本地开发环境约定

## 基本信息
- 时间：2026-05-07 00:45
- 服务目录：`www/service.antifraud.local.com`
- 模块归属：项目环境配置
- 任务类型：本地开发环境记录

## 需求背景
- 本地开发环境运行在 Docker 中。
- MySQL 通过宿主机 `127.0.0.1:13306` 连接，用户为 `root`。
- 后续开发按自主开发模式推进：能从代码里判断的直接按现有项目规范决定；只在破坏性操作、数据风险、目标目录无法判断时再确认。

## 改动范围
- `www/service.antifraud.local.com/.env`
- `www/service.antifraud.local.com/.env.example`
- `docs/backend-lumen-project-init.md`
- `docs/dev-environment.md`

## 核心流程
1. 确认目标服务目录为 `www/service.antifraud.local.com`。
2. 将本地 `.env` 的数据库端口调整为 Docker 映射端口 `13306`。
3. 将本地时区调整为 `Asia/Shanghai`。
4. 将环境说明归档到 `docs/dev-environment.md`。

## 分层说明
- Controller：无改动。
- Business：无改动。
- Dao：无改动。
- Model：无改动。
- Constant / Rule：无改动。

## 数据与状态
- 表结构：无改动。
- 状态流转：无改动。
- 数据库名约定：`service_antifraud`。

## 权限与安全
- `.env` 使用本机实际开发密码。
- `.env.example` 与归档文档不记录明文密码。
- 未执行数据库写入、迁移或删除操作。

## 验证结果
- 已确认仓库内暂无 `docker-compose.yml` 或 `Dockerfile`。
- 已确认服务目录存在 `artisan`、`.env`、`.env.example`、`composer.json`。

## 发布与回滚
- 发布步骤：无。
- 回滚方式：还原 `.env`、`.env.example` 和相关 docs 改动。
- 注意事项：如果后续补充 Compose 配置，应用容器内应优先使用数据库 service name 和容器内端口 `3306`。

# 防诈助手 Lumen 后端项目初始化说明

## 项目选择

本次按用户要求创建 Lumen 后端项目。

选择原因：

- 服务定位是反诈分析 API，前期更适合轻量服务；
- Lumen 启动成本低，适合先做 MVP API；
- 后续可按业务复杂度扩展为 Laravel 或拆分服务。

## 创建方式

使用 Composer 官方项目创建方式，未复制任何旧项目目录。

实际创建命令：

```bash
COMPOSER_HOME=/Users/hxc/Documents/New\ project\ 2/.composer-cn \
php composer.phar create-project --prefer-dist laravel/lumen www/service.antifraud.local.com
```

国内源配置：

```bash
COMPOSER_HOME=/Users/hxc/Documents/New\ project\ 2/.composer-cn \
php composer.phar config -g repo.packagist composer https://mirrors.aliyun.com/composer/
```

说明：

- 本机没有全局 Composer；
- 已通过国内 Composer 安装镜像生成项目内 `composer.phar`；
- `composer.phar`、`composer-setup.php`、`.composer-cn/` 已加入仓库根目录 `.gitignore`；
- Lumen v10 在 Composer 2.9 下会被安全审计阻断部分历史依赖，项目内已设置 `audit.block-insecure=false`，后续正式开发前建议重新评估依赖安全策略。

## 目录位置

仓库目录：

```text
/Users/hxc/Documents/New project 2
```

服务代码目录：

```text
/Users/hxc/Documents/New project 2/www/service.antifraud.local.com
```

`service.antifraud.local.com` 是当前占位服务域名。后续确认公司名、真实域名后再统一替换。

## 服务代码目录

```text
www/service.antifraud.local.com/
├── app/
│   ├── Console/
│   ├── Events/
│   ├── Exceptions/
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Common/
│   │       ├── Management/
│   │       ├── Server/
│   │       └── Service/
│   ├── Jobs/
│   ├── Kernel/
│   │   ├── Constant/
│   │   ├── Contracts/
│   │   ├── Controllers/
│   │   ├── Dao/
│   │   ├── Traits/
│   │   └── Utils/
│   ├── Libraries/
│   ├── Models/
│   ├── Modules/
│   │   ├── Basics/
│   │   │   ├── Api/
│   │   │   ├── Constant/
│   │   │   ├── Dao/
│   │   │   ├── Factory/
│   │   │   ├── Model/
│   │   │   └── Rule/
│   │   ├── Management/
│   │   │   └── Business/
│   │   └── Service/
│   └── Providers/
├── bootstrap/
├── config/
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/
├── public/
├── resources/
├── routes/
│   ├── management/
│   │   └── proxy/
│   ├── server/
│   │   └── v1/
│   ├── service/
│   │   └── v1/
│   └── web.php
├── storage/
├── tests/
└── vendor/
```

## 分层约定

- Controller：只收参、调用 Business、包装响应。
- Business：业务校验、流程编排、事务边界。
- Dao：查询和持久化，一个主要表对应一个 Dao。
- Model：表映射、常量、scope 查询能力。
- Constant / Factory / Rule / Exception：按业务模块收敛。
- 配置：`.env` -> `config/*` -> `config()`，业务代码禁止直接 `env()`。
- 表结构：必须使用 migration。
- SQL：Raw SQL 必须参数绑定，不能拼接外部输入。

## 必须保留的基础能力

- `app/Kernel/**`：基座 Controller、Business、Dao、工具、接口和公共常量。
- `app/Modules/Basics/**`：用户、点数、文件、风险规则等基础模型与数据访问。
- `routes/management/proxy/**`：管理端代理接口。
- `routes/service/v1/**`：小程序/API 服务端接口。
- `routes/server/v1/**`：内部服务接口。
- `database/migrations/**`：所有表结构变更。
- `config/**`：服务配置、第三方 API、队列、对象存储等配置。

## 可选裁剪能力

- `app/Jobs/**`：若 MVP 暂不做异步任务，可先保留目录，后续接 AI 分析队列时启用。
- `app/Console/Commands/**`：若暂不做批处理，可后续按需要增加。
- `app/Libraries/**`：第三方 OCR、ASR、LLM、对象存储 SDK 封装。

## 环境与分支

环境：

- `local`：开发环境；
- `tests`：测试环境，对应 `beta`；
- `production`：生产环境，对应 `master`。

分支：

- `master`：生产；
- `beta`：测试；
- `dev/{yyyyMMdd}/{requirement}`：需求分支；
- `fix/{yyyyMMdd}/{requirement}`：修复分支。

禁止把 `beta` 合并回 `dev/*`、`fix/*` 或 `master`。

## 本地 Docker 开发环境

当前本地开发环境运行在 Docker 里，MySQL 通过宿主机端口映射访问。

本地连接约定：

```text
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=13306
DB_DATABASE=service_antifraud
DB_USERNAME=root
DB_PASSWORD=本地开发密码
APP_TIMEZONE=Asia/Shanghai
```

说明：

- Navicat 等宿主机工具使用 `127.0.0.1:13306` 连接本地 Docker MySQL。
- 项目 `.env` 可写入本机实际密码，`.env.example` 不写明文密码。
- 若后续补充 `docker-compose.yml`，应用容器内连接 MySQL 时优先使用 Compose service name 和容器内端口 `3306`，不要混用宿主机映射端口。
- Codex 自主开发时优先从现有 `.env`、Docker 配置和代码判断运行方式；只有破坏性操作、数据风险或目标目录无法判断时再询问。

## 后续 skill 顺序

建议顺序：

1. `laravel-standards`：确认项目分层、路由、响应、异常、配置规范。
2. `laravel-migrations`：创建用户、分析记录、文件、风险点、点数流水、规则表。
3. `laravel-crud`：实现小程序端 API 和管理后台 API。
4. `laravel-queue-job`：实现图片/录音 AI 分析异步任务。
5. `laravel-console`：实现重试、补偿、清理、统计命令。
6. `laravel-openapi`：生成 Apifox/OpenAPI 文档。
7. `laravel-git-flow`：按项目规范创建需求分支与发布流程。

## 待确认信息

- 正式项目名；
- 公司/团队英文标识；
- 真实服务域名；
- 管理端是否同仓库实现；
- 对象存储供应商；
- OCR、ASR、LLM 供应商；

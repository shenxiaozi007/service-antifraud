# MVP 后端 API 开发

## 基本信息
- 时间：2026-05-07 00:56
- 服务目录：`www/service.antifraud.local.com`
- 模块归属：Service / Management / Basics
- 任务类型：MVP 接口、表结构、基础业务闭环

## 需求背景
- 根据 `docs` 中 PRD、技术开发文档和 MVP 原型，先交付一版可供小程序和后台联调的 Lumen 后端 MVP。
- 覆盖登录、上传凭证、图片/录音分析、风险报告、历史记录、点数流水、基础管理后台和风险规则。
- 按自主开发模式推进，能从现有代码和文档判断的事项直接按项目规范落地。

## 改动范围
- `bootstrap/app.php`
- `config/database.php`
- `routes/web.php`
- `routes/service/v1/api.php`
- `routes/management/proxy/api.php`
- `database/migrations/2026_05_07_005000_create_anti_fraud_mvp_tables.php`
- `app/Kernel/Base/*`
- `app/Modules/Basics/Constant/*`
- `app/Modules/Basics/Model/*`
- `app/Modules/Basics/Dao/*`
- `app/Modules/Service/*`
- `app/Modules/Management/Business/*`
- `app/Http/Controllers/Service/V1/*`
- `app/Http/Controllers/Management/Proxy/*`
- `app/Exceptions/Handler.php`
- `docs/mvp-api.md`

## 核心流程
1. 微信登录创建或更新用户，返回 API token，新用户赠送 30 点。
2. 用户创建上传凭证，服务保存文件元信息。
3. 用户发起图片或录音分析，校验文件归属和点数余额。
4. MVP 阶段用关键词规则生成风险等级、风险分、建议动作和风险点。
5. 分析成功后扣除点数、写入点数流水、绑定文件、保存报告。
6. 用户可查询报告详情、历史记录、点数流水，并可删除记录和关联文件。
7. 管理端可查看用户、分析记录、文件、点数流水，维护风险规则，并重试失败记录。

## 分层说明
- Controller：`app/Http/Controllers/Service/V1/*`、`app/Http/Controllers/Management/Proxy/*` 仅收参、调用 Business、通过 `revert()` 返回。
- Business：`app/Modules/Service/*` 处理用户端流程，`app/Modules/Management/Business/*` 处理后台查询和规则维护。
- Dao：`app/Modules/Basics/Dao/*` 封装分页、查询、创建、文件绑定、风险点替换。
- Model：`app/Modules/Basics/Model/*` 映射用户、文件、分析记录、风险点、点数流水、风险规则。
- Constant / Rule：`AnalysisConstant`、`PointConstant` 统一分析状态、类型、点数规则。

## 数据与状态
- 新增表：`users`、`analysis_records`、`risk_items`、`file_assets`、`point_transactions`、`risk_rules`。
- 分析状态：`pending`、`processing`、`completed`、`failed`、`refunded`，MVP 同步分析后直接进入 `completed`。
- 风险等级：`low`、`medium`、`high`、`critical`。
- 点数规则：新用户 30 点，图片 20 点/次，录音 10 点/分钟，不足一分钟按一分钟计。

## 权限与安全
- 用户端使用 `Authorization: Bearer {token}` 访问个人接口。
- 用户只能访问自己的文件、报告和点数流水。
- 删除记录采用软删除。
- 错误统一 JSON 输出，校验错误不暴露敏感实现。
- 管理端当前为 MVP 基础接口，尚未接 JWT/RBAC，正式接后台前需要补鉴权和权限点。

## 验证结果
- 已执行全量 PHP 语法检查，所有新增和相关 PHP 文件通过 `php -l`。
- 已验证 Lumen 应用能加载 20 条路由。
- 已执行 PHPUnit，结果 `OK (1 test, 1 assertion)`。
- PHPUnit 在 PHP 8.4 下输出 Lumen 测试组件弃用提示，不影响当前测试通过。
- 未执行数据库迁移，避免在未确认数据状态时改动本地数据库。

## 发布与回滚
- 开发环境迁移命令：

```bash
cd www/service.antifraud.local.com
php artisan migrate --path=/database/migrations/2026_05_07_005000_create_anti_fraud_mvp_tables.php
```

- 回滚命令：

```bash
cd www/service.antifraud.local.com
php artisan migrate:rollback --path=/database/migrations/2026_05_07_005000_create_anti_fraud_mvp_tables.php
```

- 注意事项：
  - 接入真实 OCR/ASR/LLM 时，应替换 `RiskAnalysisBusiness` 的规则分析入口或改为队列异步任务。
  - 接入真实对象存储后替换上传凭证生成逻辑。
  - 接入管理后台前补管理端鉴权、权限点和审计。

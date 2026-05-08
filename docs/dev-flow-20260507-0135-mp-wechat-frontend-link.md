# 微信小程序前端与接口联调

## 基本信息
- 时间：2026-05-07 01:35
- 前端目录：`apps/mp-wechat`
- 后端服务目录：`www/service.antifraud.local.com`
- 任务类型：微信小程序 MVP、前后端联调

## 需求背景
- 在后端 MVP API 基础上启动用户端前端开发，并完成本地联调。
- 根据项目形态选择微信小程序原生工程，优先覆盖“帮您看”“帮您听”“报告”“历史记录”“我的”闭环。

## 改动范围
- `apps/mp-wechat/app.*`
- `apps/mp-wechat/project.config.json`
- `apps/mp-wechat/sitemap.json`
- `apps/mp-wechat/utils/*`
- `apps/mp-wechat/pages/home/*`
- `apps/mp-wechat/pages/image/*`
- `apps/mp-wechat/pages/audio/*`
- `apps/mp-wechat/pages/report/*`
- `apps/mp-wechat/pages/history/*`
- `apps/mp-wechat/pages/me/*`
- `apps/mp-wechat/pages/content/*`
- `docs/mp-wechat-mvp.md`
- 后端兼容修复：`app/Kernel/Base/BaseBusiness.php`、`app/Modules/Service/*`、`app/Modules/Management/Business/*`

## 核心流程
1. 小程序启动后自动登录，保存后端返回的 token。
2. 首页进入“帮您看”或“帮您听”。
3. 帮您看选择图片并创建上传凭证，MVP 阶段用文本框模拟 OCR 结果。
4. 帮您听录音或输入文本，创建音频上传凭证，MVP 阶段用文本框模拟 ASR 结果。
5. 前端提交分析接口，后端扣点并生成风险报告。
6. 报告页展示风险等级、建议动作、风险点、证据和免责声明。
7. 历史记录和我的页面分别拉取报告列表、用户信息和点数流水。

## 分层说明
- 小程序 API 层：`utils/request.js`、`utils/api.js` 统一处理 baseURL、token 和错误提示。
- 小程序页面层：各 `pages/*` 只负责页面状态、交互和跳转。
- 后端 Controller：保持收参、调用 Business、`revert()` 返回。
- 后端 Business：修复 Lumen helper 兼容，使用显式异常和 Carbon 时间。

## 数据与状态
- 已在本地 Docker MySQL 创建 `service_antifraud` 数据库。
- 已执行 MVP 建表 migration。
- 联调生成了图片分析记录和录音分析记录各 1 条。
- 图片联调风险等级：`high`。
- 录音联调风险等级：`critical`。

## 权限与安全
- 用户端接口使用 Bearer token。
- 小程序本地调试默认连接 `http://127.0.0.1:8000`，正式环境需切 HTTPS 和合法域名。
- 文档不记录明文数据库密码。
- 管理端仍为 MVP 接口，正式后台接入前需补 JWT/RBAC。

## 验证结果
- 小程序 JSON 配置全部通过 Node JSON 解析。
- 小程序 JS 文件全部通过 `node --check`。
- 后端 PHP 文件全部通过 `php -l`。
- Lumen 应用可加载 20 条路由。
- 已执行数据库 migration。
- HTTP 联调通过：
  - `POST /api/v1/auth/wechat-login`
  - `POST /api/v1/files/upload-token`
  - `POST /api/v1/analysis/image`
  - `GET /api/v1/analysis/{record_id}`
  - `GET /api/v1/analysis-records`
  - `POST /api/v1/analysis/audio`
  - `GET /api/v1/points/transactions`
  - `GET /management/proxy/analysis-records`

## 发布与回滚
- 本地启动后端：

```bash
cd www/service.antifraud.local.com
php -S 127.0.0.1:8000 -t public
```

- 微信开发者工具导入：

```text
apps/mp-wechat
```

- 回滚数据库：

```bash
cd www/service.antifraud.local.com
php artisan migrate:rollback --path=/database/migrations/2026_05_07_005000_create_anti_fraud_mvp_tables.php
```

- 注意事项：
  - 真实上线前需要补对象存储上传、OCR/ASR/LLM、微信支付、管理端鉴权、生产域名和 HTTPS。

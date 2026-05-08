# uni-app 用户端主线迁移

## 基本信息
- 时间：2026-05-07 22:35
- 前端目录：`apps/client`
- 后端服务目录：`www/service.antifraud.local.com`
- 任务类型：前端主线切换、uni-app 多端工程

## 需求背景
- 后续用户端希望优先开发 H5，并同步到小程序和 App。
- 前端标准已调整为用户端新项目默认 `uni-app + Vue 3 + TypeScript`。
- 将已完成的小程序原型能力迁移到一套 uni-app 主线工程。

## 改动范围
- `apps/client/package.json`
- `apps/client/vite.config.ts`
- `apps/client/tsconfig.json`
- `apps/client/src/App.vue`
- `apps/client/src/main.ts`
- `apps/client/src/manifest.json`
- `apps/client/src/pages.json`
- `apps/client/src/api/*`
- `apps/client/src/constants/*`
- `apps/client/src/stores/*`
- `apps/client/src/styles/*`
- `apps/client/src/types/*`
- `apps/client/src/pages/**`
- `www/service.antifraud.local.com/app/Http/Middleware/CorsMiddleware.php`
- `www/service.antifraud.local.com/bootstrap/app.php`
- `docs/uni-app-client.md`

## 核心流程
1. 建立 `apps/client` uni-app 工程。
2. 统一 API 请求、token、错误提示和接口类型。
3. 迁移首页、图片分析、录音分析、报告、历史、我的、协议隐私页面。
4. 保持页面层只处理交互和展示，接口调用集中在 `src/api`。
5. 后端增加 CORS 中间件，支持 H5 本地联调。

## 分层说明
- API：`src/api/request.ts`、`src/api/client.ts`。
- 类型：`src/types/api.ts`。
- 状态：`src/stores/session.ts`。
- 常量/格式化：`src/constants/risk.ts`。
- 页面：`src/pages/**`。
- 后端：新增 CORS Middleware，不影响 Controller / Business / Dao 分层。

## 数据与状态
- 数据库结构无变化。
- 前端 token 使用 uni storage。
- H5、小程序、App 共用同一接口配置和页面逻辑。

## 权限与安全
- H5 本地联调允许 CORS。
- 生产环境 CORS 应收敛到实际域名。
- 小程序上线需配置合法域名和真实 AppID。

## 验证结果
- 已执行：`npm install`
- 已执行：`npm run typecheck`
- 已执行：`npm run build:h5`
- 已执行：后端 PHP 语法检查
- `npm install` 提示 46 个依赖审计风险，来自 uni-app 依赖树，未自动执行破坏性升级。
- H5 构建通过，Sass 输出 legacy JS API deprecation warning，不影响构建。

## 发布与回滚
- H5 本地启动：

```bash
cd apps/client
npm run dev:h5
```

- H5 构建：

```bash
cd apps/client
npm run build:h5
```

- 微信小程序构建：

```bash
cd apps/client
npm run build:mp-weixin
```

- 回滚方式：恢复 `apps/client` 新增工程和 CORS 中间件改动；保留 `apps/mp-wechat` 原型不受影响。

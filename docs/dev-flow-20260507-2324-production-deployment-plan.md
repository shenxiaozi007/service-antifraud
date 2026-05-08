# 2026-05-07 23:24 生产部署方案归档

## 需求背景
用户确认正式项目名为“守护者max”，主域名 `188144.xyz` 已接入 Cloudflare CDN，对象存储使用 Cloudflare，LLM 使用第三方中转 API 和 key。需要给出上线部署方案，并继续按自主开发模式处理可从代码判断的工程调整。

## 服务目录与模块归属
- 后端服务目录：`www/service.antifraud.local.com`
- 用户端前端目录：`apps/client`
- 管理端建议目录：`apps/admin`
- 文档归档目录：`docs/`

## 改动文件
- `apps/client/src/api/config.ts`
- `apps/client/src/env.d.ts`
- `apps/client/.env.example`
- `www/service.antifraud.local.com/config/cors.php`
- `www/service.antifraud.local.com/bootstrap/app.php`
- `www/service.antifraud.local.com/app/Http/Middleware/CorsMiddleware.php`
- `www/service.antifraud.local.com/.env.example`
- `www/service.antifraud.local.com/.env.production.example`
- `docs/production-deployment.md`
- `docs/dev-flow-20260507-2324-production-deployment-plan.md`

## 核心流程
1. 前端 H5 构建通过 `VITE_API_BASE_URL` 注入正式 API 地址，避免写死本地地址。
2. 后端新增 CORS 配置文件，生产环境通过 `CORS_ALLOWED_ORIGINS` 控制允许来源。
3. 新增生产 `.env` 示例，预留 Cloudflare R2、LLM、OCR、ASR 配置位。
4. 输出 `docs/production-deployment.md`，明确域名规划、部署架构、Cloudflare 配置和上线顺序。

## 分层说明
- CORS 仍由全局 Middleware 处理，配置集中在 `config/cors.php`。
- 前端 API baseURL 仍由 `src/api/config.ts` 暴露，不下沉到页面层。
- 生产部署说明独立放在 `docs/production-deployment.md`，不混入 PRD 和接口文档。

## 数据与状态变化
- 本次未执行生产数据库变更。
- 本次未写入真实密钥。
- 本次未连接 Cloudflare、R2 或第三方 LLM。

## 权限与安全点
- 生产 CORS 应只允许 `https://188144.xyz`、`https://www.188144.xyz`、`https://admin.188144.xyz`。
- `APP_DEBUG` 生产必须为 `false`。
- Cloudflare 建议开启代理、WAF、限流，管理端初期可用 Cloudflare Access 保护。
- 上传、LLM、OCR、ASR 密钥只能写入服务器环境变量或密钥管理，不提交仓库。

## 验证结果
- 已执行：`php -l www/service.antifraud.local.com/app/Http/Middleware/CorsMiddleware.php`，通过。
- 已执行：`php -l www/service.antifraud.local.com/config/cors.php`，通过。
- 已执行：`npm run typecheck`，通过。
- 已执行：`npm run build:h5`，通过；构建产物位于 `apps/client/dist/build/h5`。

## 发布或回滚注意事项
- H5 回滚可直接回滚 Cloudflare Pages 上一个部署版本，或替换 Nginx 静态目录。
- 后端发布前必须备份数据库，migration 使用 `php artisan migrate --force`。
- 若 CORS 配置错误导致 H5 不可用，可临时把 `CORS_ALLOWED_ORIGINS` 调整为正式 H5 域名后重启 PHP-FPM。

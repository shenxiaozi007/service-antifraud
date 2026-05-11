# 守护者max 生产部署方案

## 项目信息
- 正式项目名：守护者max
- 主域名：`188144.xyz`，已接入 Cloudflare CDN
- 对象存储：Cloudflare R2
- 前端主线：`apps/client`，`uni-app + Vue 3 + TypeScript`，优先发布 H5，再同步小程序/App
- 后端服务：`www/service.antifraud.local.com`，Lumen API
- LLM：第三方中转 API，通过环境变量配置 `LLM_BASE_URL`、`LLM_API_KEY`、`LLM_MODEL`

## 推荐域名规划
- `https://188144.xyz`：用户端 H5，部署 `apps/client/dist/build/h5`
- `https://api.188144.xyz`：后端 API，Cloudflare 代理到服务器 Nginx/PHP-FPM
- `https://admin.188144.xyz`：后续管理端，建议同仓库新增 `apps/admin`
- `https://assets.188144.xyz`：Cloudflare R2 资源域名，用于图片、音频、报告附件

管理端建议同仓库实现，但前端工程独立放在 `apps/admin`。后端管理接口继续放在当前 Lumen 服务的 `/management/proxy/*` 路由下，这样权限、日志、业务模型和部署链路都能复用。

## 部署架构
```mermaid
flowchart LR
  U["用户/管理人员"] --> CF["Cloudflare DNS/CDN/WAF"]
  CF --> H5["Cloudflare Pages 或 Nginx 静态站点"]
  CF --> API["api.188144.xyz Nginx"]
  API --> PHP["PHP-FPM / Lumen"]
  PHP --> DB["MySQL"]
  PHP --> Redis["Redis / 队列"]
  PHP --> R2["Cloudflare R2"]
  PHP --> AI["OCR/ASR/LLM Provider"]
```

## H5 发布流程
1. 进入 `apps/client`。
2. 设置生产 API 地址：`VITE_API_BASE_URL=https://api.188144.xyz`。
3. 执行 `npm ci`。
4. 执行 `npm run build:h5`。
5. 将 `apps/client/dist/build/h5` 发布到 Cloudflare Pages，或上传到服务器 Nginx 静态目录。

H5 默认只依赖 API 域名，不再写死本地 `127.0.0.1:8000`。

## 后端发布流程
仓库已提供 Docker Compose 生产部署骨架，适合单机先上线：

1. 将代码发布到服务器，进入仓库根目录。
2. 复制 `www/service.antifraud.local.com/.env.production.example` 为 `www/service.antifraud.local.com/.env`，填写 `APP_KEY`、`DB_*`、`MYSQL_*`、R2、LLM、OCR、ASR 等真实配置；使用内置 MySQL 时，`DB_DATABASE/DB_USERNAME/DB_PASSWORD` 需要和 `MYSQL_DATABASE/MYSQL_USER/MYSQL_PASSWORD` 保持一致。
3. 若使用云数据库，把 `DB_HOST` 改成云数据库地址，并按需从 compose 中移除 `mysql` 服务。
4. 执行 `docker compose --env-file www/service.antifraud.local.com/.env -f docs/docker/backend/docker-compose.prod.yml up -d --build`。
5. 执行 `docker compose --env-file www/service.antifraud.local.com/.env -f docs/docker/backend/docker-compose.prod.yml exec app php artisan migrate --force`。
6. 访问 `http://服务器IP/api/v1/system/health` 验证后端容器、Nginx 和路由正常。
7. Cloudflare 将 `api.188144.xyz` 代理到服务器 80/443，SSL 模式建议使用 Full strict，源站配置有效证书。

本地验证时如果只想使用本机已有镜像，先把 `API_HTTP_PORT` 改成未占用端口，例如 `18080`，再执行：

```bash
docker compose --env-file www/service.antifraud.local.com/.env -f docs/docker/backend/docker-compose.prod.yml up -d --build --pull never
```

非 Docker 部署也可以继续使用 Nginx + PHP-FPM + MySQL + Redis：PHP 生产版本建议使用 8.2 或 8.3，执行 `composer install --no-dev --optimize-autoloader` 后，把 Nginx root 指向 `www/service.antifraud.local.com/public`。

## Cloudflare 配置
- DNS：
  - `188144.xyz` 指向 H5 站点。
  - `api.188144.xyz` 指向后端服务器，并开启代理。
  - `admin.188144.xyz` 预留给管理端。
  - `assets.188144.xyz` 绑定 R2 自定义域名。
- 安全：
  - 对 `/api/v1/auth/*` 和分析接口加基础限流。
  - 管理端上线前可先用 Cloudflare Access 保护 `admin.188144.xyz`。
  - 后端 `CORS_ALLOWED_ORIGINS` 只允许正式 H5、管理端域名。

## 生产前必须补齐
- R2 真实上传：当前 MVP 的上传令牌仍是占位逻辑，正式上传需要接入 R2 预签名 URL 或后端转存。
- OCR/ASR：需要确定供应商。如果第三方 LLM 中转同时支持图片和音频，可以统一走该供应商；否则分别接 OCR、ASR 服务。
- LLM 适配器：当前分析逻辑是关键词 MVP，需要把第三方中转 API 封装为独立服务，并加入超时、重试、日志脱敏和失败降级。
- 队列：图片/音频分析建议改为异步队列，避免用户请求长时间阻塞。
- 管理端鉴权：当前已有管理 API 入口，正式管理后台需要补登录、角色、操作日志和按钮权限。
- 备份：MySQL 每日备份，R2 生命周期规则，关键日志保留策略。

## 上线顺序
1. 先部署后端测试环境，跑通 migration 和 `/api/v1/system/health`。
2. 再部署 H5 到 `188144.xyz`，确认 CORS、登录、报告列表可用。
3. 接入 R2 上传，联调图片/音频文件链路。
4. 接入 LLM/OCR/ASR 真实分析链路。
5. 开 Cloudflare WAF/限流，关闭 `APP_DEBUG`。
6. 上线管理端最小版本，先覆盖分析记录、规则、失败重试和用户点数流水。

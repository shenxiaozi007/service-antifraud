# 守护者max 线上部署执行文档

本文档用于把已在本地 Laradock 验证通过的 MVP 部署到线上。

## 1. 线上目标

- 反诈业务 API：`https://ant.hxcbox.cn`
- 公共基础服务 API：`https://file.hxcbox.cn`
- 公共基础服务职责：文件上传、R2、用户、验证码登录、微信登录、token、钱包、微信支付
- 反诈业务服务职责：文件绑定、分析任务、OCR/ASR/LLM Agent、报告、管理复核
- 前端 H5：`apps/client/dist/build/h5`
- 小程序：`apps/client` 构建 `mp-weixin` 或 `apps/mp-wechat`

## 2. 服务器基础要求

- 已安装并可运行 Laradock
- Laradock 至少启动：`nginx`、`php-fpm`、`workspace`、`mysql`、`redis`
- PHP 建议使用 8.2 或 8.3；当前本地 PHP 8.4 可跑通，但 Lumen 依赖会有 deprecation 提示
- MySQL 建议 8.0
- Redis 用于队列和缓存
- 服务器 80/443 可被 Cloudflare 访问
- `ant.hxcbox.cn`、`file.hxcbox.cn` 已解析到该服务器

## 3. 目录约定

以下命令假设：

- Laradock 目录：`/var/www/laradock`
- 项目目录：`/var/www/service-antifraud`
- 容器内项目路径：`/var/www/service-antifraud`

如果你的路径不同，把命令里的路径同步替换。

## 4. 发布代码

```bash
cd /var/www
git clone <你的仓库地址> service-antifraud

cd /var/www/service-antifraud
git checkout <上线分支或 tag>
```

已有代码目录时：

```bash
cd /var/www/service-antifraud
git fetch --all --tags
git checkout <上线分支或 tag>
git pull
```

## 5. 配置 Nginx 站点

复制仓库内准备好的 Laradock Nginx 配置：

```bash
cp /var/www/service-antifraud/docs/laradock/nginx-sites/ant.hxcbox.cn.conf /var/www/laradock/nginx/sites/ant.hxcbox.cn.conf
cp /var/www/service-antifraud/docs/laradock/nginx-sites/file.hxcbox.cn.conf /var/www/laradock/nginx/sites/file.hxcbox.cn.conf
```

检查两个配置里的 `root` 是否正确：

```nginx
root /var/www/service-antifraud/www/service.antifraud.local.com/public;
root /var/www/service-antifraud/www/service.storage.company.com/public;
```

如果 Laradock 容器内项目路径不是 `/var/www/service-antifraud`，需要改成实际路径。

## 6. 创建线上数据库

进入 Laradock：

```bash
cd /var/www/laradock
docker compose up -d mysql redis nginx php-fpm workspace
```

创建两个库：

```bash
docker compose exec mysql mysql -uroot -p -e "CREATE DATABASE IF NOT EXISTS service_common DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS service_antifraud DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

建议单独创建两个数据库账号：

```sql
CREATE USER 'common_service'@'%' IDENTIFIED BY '强密码';
CREATE USER 'guardian_max'@'%' IDENTIFIED BY '强密码';
GRANT ALL PRIVILEGES ON service_common.* TO 'common_service'@'%';
GRANT ALL PRIVILEGES ON service_antifraud.* TO 'guardian_max'@'%';
FLUSH PRIVILEGES;
```

## 7. 配置公共服务 `.env`

```bash
cd /var/www/service-antifraud
cp www/service.storage.company.com/.env.production.example www/service.storage.company.com/.env
```

编辑：

```bash
vim www/service.storage.company.com/.env
```

关键配置：

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://file.hxcbox.cn

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=service_common
DB_USERNAME=common_service
DB_PASSWORD=强密码

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

SERVICES_STORAGE_DOMAIN=file.hxcbox.cn
SERVICE_APP_ID=antifraud
SERVICE_SECRET=生产环境随机长密钥

OBJECT_STORAGE_DEFAULT_DISK=cloudflare_r2
R2_ACCESS_KEY_ID=你的R2_ACCESS_KEY_ID
R2_SECRET_ACCESS_KEY=你的R2_SECRET_ACCESS_KEY
R2_REGION=auto
R2_BUCKET=hxc
R2_ENDPOINT=https://你的账号ID.r2.cloudflarestorage.com
R2_PUBLIC_HOST=https://file.hxcbox.cn
R2_PATH_STYLE=false

WECHAT_MINI_PROGRAM_APP_ID=微信小程序AppID
WECHAT_MINI_PROGRAM_APP_SECRET=微信小程序AppSecret
WECHAT_LOGIN_MOCK=false

VERIFICATION_CODE_WEBHOOK_URL=你的短信或邮件验证码发送服务地址
VERIFICATION_CODE_WEBHOOK_TOKEN=验证码服务token

# 如果暂时没有验证码 webhook，可以直接使用企业微信邮箱发邮箱验证码。
# 企业微信邮箱 SMTP：smtp.exmail.qq.com，SSL 端口 465。
VERIFICATION_CODE_MAIL_ENABLED=true
VERIFICATION_CODE_MAIL_HOST=smtp.exmail.qq.com
VERIFICATION_CODE_MAIL_PORT=465
VERIFICATION_CODE_MAIL_ENCRYPTION=ssl
VERIFICATION_CODE_MAIL_USERNAME=你的企业邮箱账号
VERIFICATION_CODE_MAIL_PASSWORD=企业邮箱客户端专用密码或授权码
VERIFICATION_CODE_MAIL_FROM_ADDRESS=你的企业邮箱账号
VERIFICATION_CODE_MAIL_FROM_NAME=守护者max
VERIFICATION_CODE_MAIL_TIMEOUT=15

WECHAT_PAY_APP_ID=微信支付AppID
WECHAT_PAY_MCH_ID=商户号
WECHAT_PAY_API_V3_KEY=APIv3密钥
WECHAT_PAY_MERCHANT_SERIAL_NO=商户证书序列号
WECHAT_PAY_MERCHANT_PRIVATE_KEY_PATH=/var/www/service-antifraud/storage/certs/wechatpay/apiclient_key.pem
WECHAT_PAY_PLATFORM_CERTIFICATE_PATH=/var/www/service-antifraud/storage/certs/wechatpay/platform_cert.pem
WECHAT_PAY_NOTIFY_URL=https://file.hxcbox.cn/service/api/v1/payment/wechat/notify
WECHAT_PAY_MOCK=false
```

生产禁止：

```env
WECHAT_LOGIN_MOCK=true
WECHAT_PAY_MOCK=true
APP_DEBUG=true
```

## 8. 配置反诈服务 `.env`

```bash
cd /var/www/service-antifraud
cp www/service.antifraud.local.com/.env.production.example www/service.antifraud.local.com/.env
```

编辑：

```bash
vim www/service.antifraud.local.com/.env
```

关键配置：

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ant.hxcbox.cn

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=service_antifraud
DB_USERNAME=guardian_max
DB_PASSWORD=强密码

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

CORS_ALLOWED_ORIGINS=https://ant.hxcbox.cn,https://你的H5域名

COMMON_SERVICE_BASE_URL=https://file.hxcbox.cn/service/api/v1
COMMON_SERVICE_PROJECT_CODE=antifraud
COMMON_SERVICE_APP_ID=antifraud
COMMON_SERVICE_SECRET=和公共服务SERVICE_SECRET一致
COMMON_SERVICE_TIMEOUT=15

LLM_BASE_URL=第三方OpenAI-compatible接口地址
LLM_API_KEY=第三方LLM密钥
LLM_MODEL=文本模型
LLM_VISION_MODEL=图片理解模型
LLM_AUDIO_MODEL=音频模型
LLM_TIMEOUT=60
OCR_PROVIDER=llm
ASR_PROVIDER=llm
```

如果反诈服务从服务器内部访问公共服务不方便走公网 HTTPS，也可以改为内网 Nginx 访问：

```env
COMMON_SERVICE_BASE_URL=http://nginx/service/api/v1
COMMON_SERVICE_HOST=file.hxcbox.cn
```

这种方式要求 `CommonServiceClient` 请求时带 `Host: file.hxcbox.cn`。

## 9. 生成 APP_KEY

如果 `.env` 里的 `APP_KEY` 为空，分别生成：

```bash
cd /var/www/laradock

docker compose exec workspace bash -lc 'cd /var/www/service-antifraud/www/service.storage.company.com && php artisan key:generate'
docker compose exec workspace bash -lc 'cd /var/www/service-antifraud/www/service.antifraud.local.com && php artisan key:generate'
```

如果当前 Lumen 项目没有启用 `key:generate`，可以手动写入 32 位以上随机字符串。

## 10. 安装依赖与迁移

```bash
cd /var/www/laradock

docker compose exec workspace bash -lc 'cd /var/www/service-antifraud/www/service.storage.company.com && composer install --no-dev --optimize-autoloader'
docker compose exec workspace bash -lc 'cd /var/www/service-antifraud/www/service.antifraud.local.com && composer install --no-dev --optimize-autoloader'

docker compose exec workspace bash -lc 'cd /var/www/service-antifraud/www/service.storage.company.com && php artisan migrate --force'
docker compose exec workspace bash -lc 'cd /var/www/service-antifraud/www/service.antifraud.local.com && php artisan migrate --force'
```

## 11. 检查生产配置

```bash
cd /var/www/service-antifraud
bash docs/scripts/check-prod-env.sh
```

检查目标：

- `APP_DEBUG=false`
- 公共服务 `WECHAT_LOGIN_MOCK=false`
- 公共服务 `WECHAT_PAY_MOCK=false`
- 两边 `SERVICE_SECRET` / `COMMON_SERVICE_SECRET` 一致
- R2、微信登录、微信支付、LLM 关键配置不为空
- 没有保留 `change-me`

## 12. 启动队列 worker

临时启动验证：

```bash
cd /var/www/laradock
docker compose exec workspace bash -lc 'cd /var/www/service-antifraud/www/service.antifraud.local.com && php artisan queue:work --tries=3 --timeout=300 --sleep=3'
```

生产建议使用 Supervisor。复制配置：

```bash
cp /var/www/service-antifraud/docs/laradock/supervisor/antifraud-worker.conf /var/www/laradock/php-worker/supervisord.d/antifraud-worker.conf
```

如果你的 Laradock supervisor 目录不同，放到实际的 `supervisord.d` 目录。然后重启对应 worker 容器或 supervisor。

确认日志：

```bash
tail -f /var/www/service-antifraud/www/service.antifraud.local.com/storage/logs/worker.log
```

## 13. 重载 Nginx

```bash
cd /var/www/laradock
docker compose exec nginx nginx -t
docker compose exec nginx nginx -s reload
```

## 14. Cloudflare 配置

DNS：

- `ant.hxcbox.cn` 指向服务器公网 IP，开启代理
- `file.hxcbox.cn` 指向服务器公网 IP，开启代理

SSL：

- 建议使用 Full strict
- 源站需要有效证书
- 临时阶段可用 Full，但不要长期用 Flexible

安全：

- 对 `/api/v1/auth/send-code`、`/api/v1/auth/code-login` 做限流
- 对 `/service/api/v1/file/upload` 设置上传大小限制
- 对管理端路径 `/management/proxy/*` 加 Cloudflare Access 或单独登录权限

## 15. 发布 H5

生产 API 地址：

```env
VITE_API_BASE_URL=https://ant.hxcbox.cn
VITE_FILE_BASE_URL=https://file.hxcbox.cn
```

构建：

```bash
cd /var/www/service-antifraud/apps/client
npm ci
npm run typecheck
npm run build:h5
```

产物目录：

```bash
apps/client/dist/build/h5
```

发布方式二选一：

- 上传到 Cloudflare Pages
- 上传到服务器 Nginx 静态站点目录

如果 H5 使用独立域名，例如 `https://guardian.hxcbox.cn`，记得把该域名加到反诈服务：

```env
CORS_ALLOWED_ORIGINS=https://guardian.hxcbox.cn
```

## 16. 发布小程序

小程序线上配置：

- 业务 API：`https://ant.hxcbox.cn`
- 文件 API：`https://file.hxcbox.cn`

微信公众平台需要配置合法域名：

- request 合法域名：`https://ant.hxcbox.cn`
- request 合法域名：`https://file.hxcbox.cn`
- uploadFile 合法域名：`https://file.hxcbox.cn`
- downloadFile 合法域名：`https://file.hxcbox.cn`

构建：

```bash
cd /var/www/service-antifraud/apps/client
npm ci
npm run build:mp-weixin
```

然后用微信开发者工具上传审核。

## 17. 上线后基础 smoke

先测两个服务是否可访问：

```bash
curl -i https://ant.hxcbox.cn/api/v1/system/health
curl -i https://file.hxcbox.cn/service/api/v1/file/disks
```

跑最小 smoke：

```bash
cd /var/www/service-antifraud

SMOKE_ACCOUNT=你的测试手机号或邮箱 \
SMOKE_CODE=真实验证码 \
ANT_BASE_URL=https://ant.hxcbox.cn \
FILE_BASE_URL=https://file.hxcbox.cn \
bash docs/scripts/smoke-mvp.sh
```

说明：

- 生产不会返回 `debug_code`
- `SMOKE_CODE` 必须填真实短信/邮箱验证码
- 该脚本验证：健康检查、公共文件服务、验证码登录、`/api/v1/me`、钱包余额、套餐列表

## 18. 上线后完整 E2E

先用测试账号完成微信支付充值，确认有点数后执行：

```bash
cd /var/www/service-antifraud

SMOKE_ACCOUNT=你的测试手机号或邮箱 \
SMOKE_CODE=真实验证码 \
ANT_BASE_URL=https://ant.hxcbox.cn \
FILE_BASE_URL=https://file.hxcbox.cn \
SMOKE_REQUIRE_ANALYSIS=true \
bash docs/scripts/smoke-e2e-analysis.sh
```

该脚本验证：

- 登录
- 钱包查询
- R2 上传
- 反诈文件注册
- 创建图片分析任务
- 队列执行
- 报告成功生成

## 19. 微信支付验收

必须人工在微信环境完成一次真实支付：

1. 小程序微信登录，确保公共服务绑定 `openid`
2. 获取点数套餐
3. 创建微信 JSAPI/小程序预支付单
4. 前端调用 `wx.requestPayment`
5. 微信回调请求：

```text
POST https://file.hxcbox.cn/service/api/v1/payment/wechat/notify
```

6. 检查公共服务数据库：

```sql
SELECT order_no, status, transaction_id, paid_at FROM payment_orders ORDER BY id DESC LIMIT 5;
SELECT user_id, project_code, balance, frozen_balance FROM project_wallets ORDER BY id DESC LIMIT 5;
SELECT related_no, amount, type, status FROM wallet_transactions ORDER BY id DESC LIMIT 10;
```

成功标准：

- `payment_orders.status = paid`
- `project_wallets.balance` 增加
- `wallet_transactions` 有 `recharge` 流水
- 重复回调不会重复加点

## 20. 分析任务验收

成功标准：

- 点数不足时创建任务失败，返回余额不足
- 点数足够时创建任务返回 `pending`
- worker 执行后变为 `success`
- 报告包含 `risk_level`、`risk_score`、`summary`、`risk_items`、`suggestions`、`confidence`
- 成功后公共钱包冻结点数减少，余额不返还
- LLM/OCR/ASR 失败时任务变为 `failed`，冻结点数释放
- 管理端 `/management/proxy/analysis/{recordId}/retry` 只能重试 `failed` 任务

## 21. 常见问题

### 邮箱验证码发送失败

企业微信邮箱 SMTP 配置：

```env
VERIFICATION_CODE_MAIL_ENABLED=true
VERIFICATION_CODE_MAIL_HOST=smtp.exmail.qq.com
VERIFICATION_CODE_MAIL_PORT=465
VERIFICATION_CODE_MAIL_ENCRYPTION=ssl
VERIFICATION_CODE_MAIL_USERNAME=你的企业邮箱账号
VERIFICATION_CODE_MAIL_PASSWORD=企业邮箱客户端专用密码或授权码
VERIFICATION_CODE_MAIL_FROM_ADDRESS=你的企业邮箱账号
VERIFICATION_CODE_MAIL_FROM_NAME=守护者max
```

注意：

- `VERIFICATION_CODE_MAIL_PASSWORD` 通常不是网页登录密码，而是客户端专用密码或授权码。
- 邮箱账号需要在企业邮箱后台开启 SMTP 服务。
- 邮箱验证码只支持邮箱账号；手机号验证码仍需要短信 webhook。

### 公共服务调用不生效

检查反诈服务：

```env
COMMON_SERVICE_BASE_URL=https://file.hxcbox.cn/service/api/v1
COMMON_SERVICE_APP_ID=antifraud
COMMON_SERVICE_SECRET=和公共服务一致
```

如果容器内不能解析公网域名，改用：

```env
COMMON_SERVICE_BASE_URL=http://nginx/service/api/v1
COMMON_SERVICE_HOST=file.hxcbox.cn
```

### 文件上传失败

检查公共服务：

```env
OBJECT_STORAGE_DEFAULT_DISK=cloudflare_r2
R2_ACCESS_KEY_ID=
R2_SECRET_ACCESS_KEY=
R2_BUCKET=
R2_ENDPOINT=
R2_PUBLIC_HOST=
```

同时确认 R2 key 有 bucket 读写权限。

### 支付下单失败

检查：

- `WECHAT_PAY_APP_ID`
- `WECHAT_PAY_MCH_ID`
- `WECHAT_PAY_API_V3_KEY`
- `WECHAT_PAY_MERCHANT_SERIAL_NO`
- 商户私钥路径是否在容器内可读
- `WECHAT_PAY_NOTIFY_URL=https://file.hxcbox.cn/service/api/v1/payment/wechat/notify`
- 用户是否通过微信登录绑定了 `openid`

### 分析一直 pending

检查 worker：

```bash
docker compose exec workspace bash -lc 'cd /var/www/service-antifraud/www/service.antifraud.local.com && php artisan queue:failed'
tail -f /var/www/service-antifraud/www/service.antifraud.local.com/storage/logs/worker.log
```

检查 `.env`：

```env
QUEUE_CONNECTION=redis
REDIS_HOST=redis
```

### LLM 没有生效

检查：

```env
LLM_BASE_URL=
LLM_API_KEY=
LLM_MODEL=
LLM_VISION_MODEL=
LLM_AUDIO_MODEL=
```

LLM 不可用时系统会走关键词 fallback，仍可出基础报告，但不是完整 Agent 效果。

## 22. 回滚

建议每次上线使用 tag：

```bash
git tag release-YYYYMMDD-HHMM
git push origin release-YYYYMMDD-HHMM
```

回滚代码：

```bash
cd /var/www/service-antifraud
git checkout 上一个稳定tag

cd /var/www/laradock
docker compose exec workspace bash -lc 'cd /var/www/service-antifraud/www/service.storage.company.com && composer install --no-dev --optimize-autoloader'
docker compose exec workspace bash -lc 'cd /var/www/service-antifraud/www/service.antifraud.local.com && composer install --no-dev --optimize-autoloader'
docker compose exec nginx nginx -s reload
```

数据库 migration 一般不自动回滚，除非确认新表/字段没有被线上数据使用。

## 23. 上线完成检查清单

- `https://ant.hxcbox.cn/api/v1/system/health` 返回正常
- `https://file.hxcbox.cn/service/api/v1/file/disks` 返回正常
- H5 能验证码登录/注册
- 小程序能微信登录
- R2 上传成功
- `/api/v1/files/register` 成功
- 套餐列表正常
- 微信支付真实付款成功
- 支付回调到账且不重复加点
- 图片分析成功生成报告
- 音频分析成功生成报告
- worker 日志无持续错误
- `APP_DEBUG=false`
- `WECHAT_LOGIN_MOCK=false`
- `WECHAT_PAY_MOCK=false`
- Cloudflare DNS、SSL、WAF、限流已配置

# 防诈助手 MVP API

## 基础信息

- 服务目录：`www/service.antifraud.local.com`
- 用户端前缀：`/api/v1`
- 管理端前缀：`/management/proxy`
- 用户端鉴权：登录后使用公共服务签发的 `Authorization: Bearer {token}`
- 公共服务：`www/service.storage.company.com`，线上域名 `https://file.hxcbox.cn/service/api/v1`

## 用户端接口

### 微信登录

```http
POST /api/v1/auth/wechat-login
```

请求：

```json
{
  "code": "wx_login_code",
  "openid": "dev_openid",
  "nickname": "微信用户"
}
```

说明：反诈服务会转发到公共服务。公共服务配置 `WECHAT_LOGIN_MOCK=false` 且填写小程序配置后，会通过微信 `jscode2session` 换取 `openid`；本地联调可显式开启 mock。

### 验证码登录/注册

```http
POST /api/v1/auth/send-code
POST /api/v1/auth/code-login
```

`code-login` 会自动完成新用户注册并返回公共 token。

### 我的信息

```http
GET /api/v1/me
```

### 公共文件上传

```http
POST https://file.hxcbox.cn/service/api/v1/file/upload
```

请求为 `multipart/form-data`：

- `file`: 图片或音频文件
- `owner_project`: `antifraud`
- `biz_type`: 业务类型，例如 `analysis_image` / `analysis_audio`

上传成功后，把公共服务返回的 `file_id/object_key/file_url/mime_type/size` 提交给反诈服务绑定。

```http
POST /api/v1/files/register
```

```json
{
  "storage_file_id": "common_file_id",
  "file_type": "image",
  "object_key": "uploads/xxx.jpg",
  "file_url": "https://file.hxcbox.cn/...",
  "mime_type": "image/jpeg",
  "file_size": 204800
}
```

`POST /api/v1/files/upload-token` 仅保留为旧客户端兼容接口，会返回公共上传入口和 `register_url`，不再创建本地占位文件。

### 图片分析

```http
POST /api/v1/analysis/image
```

请求：

```json
{
  "file_ids": [1, 2],
  "text": "保证收益，稳赚不赔，名额有限"
}
```

说明：接口创建异步任务并立即返回 `pending`；队列任务会执行图片理解/OCR、LLM 风险分析和关键词 fallback。

### 录音分析

```http
POST /api/v1/analysis/audio
```

请求：

```json
{
  "file_id": 3,
  "duration_seconds": 130,
  "text": "不要告诉家人，把验证码发给我"
}
```

### 报告详情

```http
GET /api/v1/analysis/{record_id}
```

### 历史记录

```http
GET /api/v1/analysis-records?type=image&risk_level=high&status=success&page=1&page_size=20
```

`status` 可选：`pending`、`processing`、`success`、`failed`、`canceled`。

### 删除记录

```http
DELETE /api/v1/analysis/{record_id}
```

删除记录会软删除分析记录和关联文件。

### 点数流水

```http
GET /api/v1/points/transactions?page=1&page_size=20
```

### 微信支付下单

```http
POST /api/v1/payments/wechat/order
```

反诈服务会代理公共服务的微信支付 JSAPI/小程序下单，返回 `timeStamp/nonceStr/package/signType/paySign` 等前端调起支付参数。生产必须配置微信支付 V3 商户参数并保持 `WECHAT_PAY_MOCK=false`；配置缺失时接口会报错，不会自动降级为 mock。

## 管理端接口

### 用户列表

```http
GET /management/proxy/users?keyword=xxx&page=1&page_size=20
```

### 分析记录列表

```http
GET /management/proxy/analysis-records?type=image&risk_level=high&status=success&page=1&page_size=20
```

### 分析记录详情

```http
GET /management/proxy/analysis-records/{record_id}
```

### 文件列表

```http
GET /management/proxy/file-assets?file_type=image&user_id=1&page=1&page_size=20
```

### 点数流水列表

```http
GET /management/proxy/point-transactions?user_id=1&page=1&page_size=20
```

说明：这里的 `user_id` 是反诈本地项目用户 ID，服务会通过 `global_user_id` 查询公共服务项目钱包流水；点数余额和流水以公共服务为准。

### 风险规则列表

```http
GET /management/proxy/risk-rules?category=保本高收益&enabled=1&page=1&page_size=20
```

### 新增风险规则

```http
POST /management/proxy/risk-rules
```

请求：

```json
{
  "category": "保本高收益",
  "keyword": "稳赚不赔",
  "severity": "high",
  "weight": 25,
  "enabled": 1
}
```

### 更新风险规则

```http
PUT /management/proxy/risk-rules/{rule_id}
```

### 失败任务重试

```http
POST /management/proxy/analysis-records/{record_id}/retry
```

## 数据库迁移

开发环境确认后，在服务目录执行：

```bash
cd www/service.antifraud.local.com
php artisan migrate --force

cd ../service.storage.company.com
php artisan migrate --force
```

## MVP 边界

- OCR/ASR/图片理解/风险分析通过 OpenAI-compatible 第三方 API 配置：`LLM_BASE_URL`、`LLM_API_KEY`、`LLM_MODEL`、`LLM_VISION_MODEL`、`LLM_AUDIO_MODEL`；未配置识别供应商时可用用户补充文本和文件摘要生成基础报告，供应商已启用但识别失败时任务会进入 `failed` 并释放冻结点数；风险分析 LLM 失败时保留关键词 fallback。
- 微信支付已接 API v3 JSAPI/小程序下单和 HTTPS 回调验签；退款、分账、订阅暂未做。
- 管理端接口已有分析记录、规则、失败重试等入口；正式管理后台仍需补完整登录、RBAC、操作日志和按钮权限。

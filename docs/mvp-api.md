# 防诈助手 MVP API

## 基础信息

- 服务目录：`www/service.antifraud.local.com`
- 用户端前缀：`/api/v1`
- 管理端前缀：`/management/proxy`
- 用户端鉴权：登录后使用 `Authorization: Bearer {token}`

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

说明：MVP 阶段支持用 `openid` 直传调试；不传时使用 `mock_{code}` 作为本地 openid。

### 我的信息

```http
GET /api/v1/me
```

### 创建上传凭证

```http
POST /api/v1/files/upload-token
```

请求：

```json
{
  "file_type": "image",
  "mime_type": "image/jpeg",
  "file_size": 204800
}
```

`file_type` 支持 `image`、`audio`。

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

说明：`text` 为 MVP 本地规则分析输入；后续接 OCR 后可由文件 OCR 文本自动生成。

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
GET /api/v1/analysis-records?type=image&risk_level=high&page=1&page_size=20
```

### 删除记录

```http
DELETE /api/v1/analysis/{record_id}
```

删除记录会软删除分析记录和关联文件。

### 点数流水

```http
GET /api/v1/points/transactions?page=1&page_size=20
```

### 微信支付下单占位

```http
POST /api/v1/payments/wechat/order
```

MVP 暂返回 mock 结构，后续接微信支付参数。

## 管理端接口

### 用户列表

```http
GET /management/proxy/users?keyword=xxx&page=1&page_size=20
```

### 分析记录列表

```http
GET /management/proxy/analysis-records?type=image&risk_level=high&status=completed&page=1&page_size=20
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
GET /management/proxy/point-transactions?user_id=1&type=analysis_cost&page=1&page_size=20
```

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
php artisan migrate --path=/database/migrations/2026_05_07_005000_create_anti_fraud_mvp_tables.php
```

## MVP 限制

- OCR、ASR、LLM 暂未接云服务，当前通过 `text` 入参和关键词规则生成报告。
- 文件上传返回占位 `upload_url`，真实对象存储接入后替换。
- 管理端接口当前未接 JWT/RBAC，中后台接入时需要补鉴权中间件和权限点。
- 微信支付下单为 mock 返回。

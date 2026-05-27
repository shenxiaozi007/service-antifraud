# 图片上传与 AI 分析后续项目计划

## 1. 当前结论

当前项目已经具备“反诈分析产品雏形”，但还不是完整 AI 分析系统。

已完成的部分包括：

- 图片上传服务已拆到独立项目：`www/service.storage.company.com`；
- 反诈主服务已具备分析接口、报告详情、历史记录、风险规则、点数流水等基础能力；
- 前端已具备图片分析页、录音分析页、报告页和历史页；
- 当前风险报告由关键词规则引擎生成；
- 数据表已预留 `ocr_text`、`transcript_text` 等后续 AI 分析字段。

当前未完成的关键部分包括：

- 反诈主服务还没有真正接入独立上传服务；
- OCR 图片文字识别未实现；
- LLM 风险分析未实现；
- 图片多模态理解未实现；
- 异步分析队列未实现；
- 点数冻结、确认扣费、失败释放点数流程未实现；
- 后台排障字段和 AI 调用日志未完善；
- 前端还不支持正式的 `pending / processing / completed / failed` 分析状态流转。

## 2. 当前已实现能力

### 2.1 反诈主服务

服务目录：`www/service.antifraud.local.com`

已实现能力：

- 微信登录；
- 用户信息；
- 图片分析接口：`POST /api/v1/analysis/image`；
- 录音分析接口：`POST /api/v1/analysis/audio`；
- 报告详情：`GET /api/v1/analysis/{record_id}`；
- 历史记录：`GET /api/v1/analysis-records`；
- 删除记录；
- 点数流水；
- mock 微信支付；
- 管理端分析记录、文件列表、风险规则、重试接口。

核心代码：

- `routes/service/v1/api.php`
- `routes/management/proxy/api.php`
- `app/Modules/Service/AnalysisBusiness.php`
- `app/Modules/Service/RiskAnalysisBusiness.php`
- `app/Modules/Service/FileBusiness.php`

### 2.2 独立上传服务

服务目录：`www/service.storage.company.com`

已实现能力：

- 文件上传；
- 文件详情；
- 下载地址；
- 下载和预览；
- 存储磁盘配置；
- S3/COS/R2 风格存储客户端。

核心接口：

```text
POST /service/api/v1/file/upload
GET  /service/api/v1/file/detail
GET  /service/api/v1/file/download-url
GET  /service/api/v1/file/download
GET  /service/api/v1/file/disks
```

核心代码：

- `www/service.storage.company.com/app/Http/Controllers/Service/Api/V1/File/FileController.php`
- `www/service.storage.company.com/app/Modules/Service/Business/FileBusiness.php`
- `www/service.storage.company.com/app/Libraries/Storage/S3StorageClient.php`

### 2.3 当前风险分析方式

当前 `RiskAnalysisBusiness` 是关键词规则引擎，不是 LLM。

当前逻辑：

1. 读取数据库启用的风险规则；
2. 用 `str_contains($text, $rule['keyword'])` 判断命中；
3. 按规则权重计算风险分；
4. 映射风险等级；
5. 生成标题、摘要、建议和风险点。

当前适合 MVP 联调，但不适合作为正式 AI 分析能力。

## 3. 未实现功能清单

### 3.1 上传服务对接主服务

当前状态：

- 独立上传服务已经存在；
- 反诈主服务仍使用 `files/upload-token` 创建本地 `file_assets` 占位；
- `upload_url` 仍是 `/api/v1/files/local-upload-placeholder`；
- 前端图片页还没有真正调用独立上传服务上传文件。

需要实现：

- 前端真实上传图片到 `service.storage.company.com`；
- 反诈主服务登记上传结果；
- `file_assets` 保存真实文件地址、对象 Key、文件类型、大小等信息；
- 图片分析接口使用真实文件记录。

### 3.2 OCR 图片文字识别

当前状态：

- `file_assets` 表已预留 `ocr_text` 字段；
- `AnalysisBusiness` 会读取 `ocr_text`；
- 但没有 OCR Client、图片下载、识别、回写逻辑。

需要实现：

- OCR 配置；
- OCR Client；
- 图片可访问地址获取；
- OCR 结果写入 `file_assets.ocr_text`；
- OCR 失败处理。

### 3.3 LLM 风险分析

当前状态：

- `.env.example` 已预留 `LLM_BASE_URL`、`LLM_API_KEY`、`LLM_MODEL`；
- 但没有 LLM Client、Prompt 构造、模型请求、JSON 解析、JSON 校验；
- 当前报告由规则引擎生成。

需要实现：

- LLM Client；
- Prompt Builder；
- 结构化 JSON 输出协议；
- JSON 校验；
- 敏感信息脱敏；
- LLM 原始输出记录；
- 模型调用失败处理。

### 3.4 图片多模态理解

当前状态：

- 文档规划了“图片理解模型补充视觉信息”；
- 当前没有图片理解模型调用；
- 当前只能分析文本。

需要实现：

- 选择是否接入多模态模型；
- 将图片 URL 或图片内容传给模型；
- 让模型识别海报、聊天截图、付款页面、合同等场景风险；
- 与 OCR 文本共同组成 LLM 分析上下文。

### 3.5 异步分析队列

当前状态：

- 创建分析时同步完成分析和扣点；
- `status` 直接写为 `completed`；
- `app/Jobs` 目前只有骨架；
- 没有真实分析 Job。

需要实现：

- `AnalyzeRecordJob`；
- `pending -> processing -> completed / failed` 状态流转；
- 创建分析接口快速返回 `record_id`；
- Job 内执行 OCR、规则匹配、LLM、保存报告；
- 前端轮询报告状态。

### 3.6 点数冻结与失败释放

当前状态：

- 当前是直接扣点；
- 没有冻结点数；
- 没有失败释放点数；
- 没有退款流水。

需要实现：

- 创建分析时冻结点数；
- 分析成功后确认扣费；
- OCR/LLM 失败时释放冻结点数；
- 必要时生成退款或释放流水；
- 后台可追踪点数变化。

### 3.7 后台与排障能力

当前状态：

- 管理端能查分析记录、文件、规则、流水；
- 但不能完整排查 AI 链路。

需要实现：

- OCR 文本展示；
- LLM 原始输出展示；
- 模型名称展示；
- Prompt 版本展示；
- 调用耗时展示；
- 失败原因展示；
- 失败任务列表；
- 重新分析；
- 手动退款。

### 3.8 前端分析状态

当前状态：

- 前端提交分析后直接跳转报告页；
- 当前依赖同步返回报告；
- 不支持正式异步流程。

需要实现：

- 报告页轮询；
- `pending` 展示等待分析；
- `processing` 展示分析中；
- `completed` 展示报告；
- `failed` 展示失败原因和重试提示。

## 4. 执行计划

### 第 1 步：打通真实上传链路

目标：前端图片真实上传到独立上传服务，反诈主服务只保存文件引用。

具体任务：

1. 确认独立上传服务返回字段：`file_id`、`object_key`、`url`、`mime_type`、`size`；
2. 改造反诈主服务文件登记接口；
3. 前端图片页改为先调用独立上传服务上传文件；
4. 上传成功后把上传结果提交给反诈主服务生成 `file_assets`；
5. 再调用 `analysis/image` 创建分析；
6. 保留当前 `text` 联调入口，方便 OCR 未完成前继续测试分析。

验收标准：

- 图片能真实上传到 `service.storage.company.com`；
- 反诈主服务 `file_assets` 能保存真实文件地址和对象 Key；
- 图片分析仍能用当前规则引擎生成报告；
- 现有报告页不受影响。

### 第 2 步：接入 LLM 同步分析版本

目标：先用最小改动让报告来自 LLM，而不是关键词规则。

具体任务：

1. 新增 `LlmClient`；
2. 新增 `RiskAnalysisPromptBuilder`；
3. 定义 LLM 输出 JSON Schema；
4. 新增 JSON 校验和敏感信息脱敏；
5. 改造 `RiskAnalysisBusiness` 为“规则命中 + LLM 总结分析”；
6. LLM 失败时返回明确失败，不静默伪装成 AI 报告。

建议 JSON 字段：

```json
{
  "risk_level": "low|medium|high|critical",
  "risk_score": 0,
  "title": "",
  "summary": "",
  "suggestions": [],
  "risk_items": [
    {
      "category": "",
      "severity": "low|medium|high|critical",
      "description": "",
      "evidence_text": ""
    }
  ],
  "disclaimer": ""
}
```

验收标准：

- 传入文本后，报告由 LLM JSON 生成；
- LLM 输出非法时能识别并失败；
- 规则命中结果能作为 Prompt 上下文传给 LLM；
- 不展示未校验通过的模型结果。

### 第 3 步：接入 OCR 图片识别

目标：用户只上传图片，不再依赖手动输入文本。

具体任务：

1. 新增 OCR 配置；
2. 新增 `OcrClient`；
3. 分析图片时，从上传服务拿下载地址或可访问 URL；
4. 调用 OCR 获取文字；
5. 写入 `file_assets.ocr_text`；
6. 用 OCR 文本、规则命中、文件信息构造 LLM Prompt；
7. 前端弱化或隐藏“风险文本”输入框。

验收标准：

- 不填写文本也能完成图片分析；
- `file_assets.ocr_text` 有真实识别结果；
- 报告基于 OCR 文本生成；
- OCR 失败不会生成假报告。

### 第 4 步：改造成异步分析任务

目标：正式支持 OCR/LLM 的长耗时处理。

具体任务：

1. 新增 `AnalyzeRecordJob`；
2. 创建分析接口只负责校验文件、冻结点数、创建 `pending` 记录、投递 Job、返回 `record_id`；
3. Job 负责状态改 `processing`；
4. Job 执行 OCR；
5. Job 执行规则匹配；
6. Job 执行 LLM 分析；
7. Job 保存报告；
8. Job 确认扣费；
9. Job 状态改 `completed`；
10. 失败时状态改 `failed`，释放冻结点数，写失败原因；
11. 前端报告页增加轮询。

验收标准：

- 创建分析接口快速返回；
- 前端能看到“分析中”；
- 完成后自动展示报告；
- 失败不扣点；
- 后台可以重试失败任务。

### 第 5 步：补齐后台与运维能力

目标：让 AI 链路可排查、可运营。

具体任务：

1. 分析记录详情增加 OCR 文本；
2. 分析记录详情增加 LLM 原始输出；
3. 分析记录详情增加模型名称；
4. 分析记录详情增加 Prompt 版本；
5. 分析记录详情增加调用耗时；
6. 分析记录详情增加失败原因；
7. 管理端增加失败任务列表；
8. 支持后台重新分析；
9. 支持手动退款；
10. 增加日志指标。

建议监控指标：

- OCR 成功率；
- LLM 成功率；
- LLM JSON 解析失败率；
- 平均分析耗时；
- 队列堆积数；
- 扣点异常数；
- 退款异常数。

验收标准：

- 任意失败记录能定位失败阶段；
- 管理端能重试；
- 点数处理可追踪；
- 敏感信息不会直接泄露到日志或后台。

## 5. 推荐下一步

建议下一步先执行第 1 步：打通真实上传链路。

原因：后面的 OCR 和多模态 LLM 都依赖真实图片文件。如果主服务还只保存占位 `upload_url`，后续即使接入 OCR 或 LLM，也拿不到稳定的图片来源。

推荐执行顺序：

1. 确认 `service.storage.company.com` 上传接口返回结构；
2. 改反诈主服务文件登记逻辑；
3. 改前端图片页真实上传；
4. 用现有规则引擎跑通端到端；
5. 再进入 LLM Client 接入。

## 6. 阶段优先级

| 优先级 | 阶段 | 说明 |
| --- | --- | --- |
| P0 | 打通上传服务 | OCR/LLM 的基础依赖 |
| P0 | LLM 同步分析 | 先验证模型输出质量 |
| P1 | OCR 图片识别 | 去掉手工输入文本依赖 |
| P1 | 异步分析任务 | 支持正式生产链路 |
| P2 | 后台与运维 | 支持排障和运营处理 |

## 7. 风险点

### 7.1 LLM 输出不稳定

风险：模型可能输出非 JSON、字段缺失或风险等级不合法。

应对：必须做 JSON 校验，不允许未校验结果入库或展示。

### 7.2 OCR 识别不完整

风险：截图、海报、合同中的文字可能漏识别。

应对：OCR 文本应与多模态模型分析、规则命中共同作为输入。

### 7.3 同步接口超时

风险：OCR 和 LLM 都是耗时操作，同步分析会导致接口慢或超时。

应对：第 2 步可先同步验证，正式使用前必须推进第 4 步异步队列。

### 7.4 点数扣费异常

风险：分析失败但已经扣点，会影响用户体验。

应对：异步阶段必须实现冻结、确认扣费、失败释放。

### 7.5 敏感信息泄露

风险：OCR、LLM 原始输出和日志可能包含手机号、身份证、银行卡、验证码等敏感信息。

应对：入库、展示、日志都需要脱敏。

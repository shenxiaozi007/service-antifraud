# 反诈助手 LLM 分析闭环项目计划

## 1. 当前状态

当前项目已经具备反诈助手 MVP 的后端骨架，并且图片上传能力已经拆到独立 storage 服务。主服务后续重点是把“上传文件、文本提取、LLM 风险分析、报告生成、管理端复核、点数扣费”串成完整闭环。

## 2. 已实现能力

### 2.1 主服务 MVP 骨架

主服务位于 `www/service.antifraud.local.com`，已具备用户端基础接口：

- 微信登录 mock
- 我的信息
- 上传凭证占位
- 图片/录音分析接口
- 报告详情
- 历史记录
- 删除记录
- 点数流水
- 微信支付 mock

关键文件：

- `www/service.antifraud.local.com/routes/service/v1/api.php`

### 2.2 管理端 API 骨架

管理端已具备：

- 用户列表
- 分析记录列表/详情
- 文件列表
- 点数流水
- 风险规则增删改查
- 失败重试入口

关键文件：

- `www/service.antifraud.local.com/routes/management/proxy/api.php`

### 2.3 数据表基础结构

已建表：

- `users`
- `analysis_records`
- `risk_items`
- `file_assets`
- `point_transactions`
- `risk_rules`

关键文件：

- `www/service.antifraud.local.com/database/migrations/2026_05_07_005000_create_anti_fraud_mvp_tables.php`

### 2.4 当前风险分析能力

当前风险分析主要基于关键词规则，不是真正的 LLM 分析。

关键文件：

- `www/service.antifraud.local.com/app/Modules/Service/RiskAnalysisBusiness.php`

### 2.5 独立 storage 服务

独立 storage 服务已具备：

- S3/R2 兼容上传
- 文件详情
- 下载链接
- 下载
- 预览
- 磁盘列表

关键文件：

- `www/service.storage.company.com/app/Modules/Service/Business/FileBusiness.php`
- `www/service.storage.company.com/routes/service/api/v1/file/file.php`

## 3. 剩余未实现功能

### 3.1 LLM 主链路

当前未实现：

- OCR 图片转文字
- ASR 录音转文字
- LLM 风险理解、风险分级、证据提取、建议生成
- 多模态图片理解
- LLM 调用日志
- LLM 失败重试
- LLM 成本、耗时、token 统计
- LLM 输出结构化校验

### 3.2 主服务与 storage 服务打通

当前主服务上传仍是占位逻辑，未真正调用独立 storage 服务。

待实现：

- 主服务调用 storage 上传接口
- `file_assets` 保存 storage 文件信息
- 分析记录绑定真实文件
- 管理端展示真实文件信息
- 支持预览、下载、删除、状态同步

### 3.3 异步分析任务

当前分析流程还不是完整异步任务。

待实现：

- 分析任务状态流转
- 队列 Job
- 超时处理
- 失败重试
- 管理端手动重试真实派发任务
- 分析过程日志

建议状态：

- `pending`：待处理
- `processing`：处理中
- `success`：成功
- `failed`：失败
- `canceled`：取消/删除

### 3.4 微信登录

当前微信登录仍是 mock。

待实现：

- `code2session`
- `openid` / `unionid` 获取
- `session_key` 处理
- 用户身份绑定
- token 生命周期

### 3.5 微信支付

当前微信支付仍是 mock。

待实现：

- 创建微信支付订单
- 支付回调
- 订单表
- 充值套餐
- 点数到账
- 幂等处理
- 退款/异常订单处理

### 3.6 管理后台治理能力

当前管理端缺少上线所需的治理能力。

待实现：

- JWT / RBAC
- 管理员账号
- 角色权限
- 操作日志
- 敏感操作审计
- 风险规则启停/版本管理
- LLM 分析记录追踪

### 3.7 产品补充能力

待补充：

- 用户协议/隐私政策
- 客服入口
- 删除账号
- 清除个人数据
- 分享报告
- 敏感词管理
- 风险规则配置后台
- 管理后台前端

### 3.8 文档与联调资料

待补充：

- 用户端 OpenAPI
- 管理端 OpenAPI
- storage 服务联调文档
- LLM 分析状态说明
- 错误码规范
- 状态码规范
- 示例请求/响应

## 4. 项目目标

把当前 anti-fraud MVP 从“上传/分析接口骨架 + 关键词规则”升级为：

> 图片/录音上传 → OCR/ASR → LLM 风险分析 → 报告生成 → 管理端复核 → 用户点数支付 的完整业务闭环。

## 5. 执行计划

### 阶段 1：主服务接 storage 上传

目标：让主业务真正使用独立图片上传服务。

任务：

1. 梳理 storage 服务上传接口参数和返回结构。
2. 在主服务新增 StorageClient 或 FileServiceClient。
3. 修改主服务上传接口，真实调用 storage 服务。
4. `file_assets` 保存：
   - `storage_file_id`
   - `disk`
   - `path` / `object_key`
   - `url` / `preview_url`
   - `mime_type`
   - `size`
   - `business_type`
5. 分析记录与文件建立关联。
6. 管理端文件列表展示真实文件数据。

交付结果：

- 用户上传图片后，文件进入 storage 服务。
- 主服务 `file_assets` 有真实记录。
- 后续分析能拿到图片地址或文件 ID。

优先级：P0

### 阶段 2：设计分析任务状态机

目标：把“上传文件”和“分析完成”拆开，支持异步处理。

任务：

1. 明确 `analysis_records` 字段是否够用。
2. 不够则新增 migration。
3. 新增 `analysis_jobs` 或复用 `analysis_records` 作为任务表。
4. 用户提交分析后立即返回 `task_id` / `analysis_record_id`。
5. 新增查询分析状态接口。
6. 管理端支持查看失败原因。
7. 管理端重试失败任务。

交付结果：

- 用户上传/提交后不阻塞等待 LLM。
- 前端可以轮询任务状态。
- 管理端可以追踪失败。

优先级：P0

### 阶段 3：接 OCR 图片识别

目标：图片上传后能提取文字，作为 LLM 输入。

任务：

1. 确认 OCR 服务选型。
2. 新增 OCR Client。
3. 对 `file_assets` 回填：
   - `ocr_text`
   - `ocr_status`
   - `ocr_error`
4. 在分析任务中调用 OCR。
5. OCR 失败时记录错误并允许重试。
6. 管理端展示 OCR 文本。

交付结果：

- 图片能转成文本。
- 后续 LLM 可以基于 OCR 文本分析。

优先级：P0

### 阶段 4：接 LLM 风险分析

目标：替换当前纯关键词规则，生成真正的反诈分析报告。

任务：

1. 新增 LLM 配置：
   - `LLM_BASE_URL`
   - `LLM_API_KEY`
   - `LLM_MODEL`
   - `LLM_TIMEOUT`
2. 新增 LlmClient / LlmRiskAnalysisService。
3. 设计 Prompt。
4. 约束 LLM 输出 JSON，例如：
   - `risk_level`
   - `risk_score`
   - `summary`
   - `risk_items`
   - `evidence`
   - `suggestions`
   - `confidence`
5. 做 JSON schema 校验。
6. 保留关键词规则作为 fallback。
7. 保存 LLM 原始输出、耗时、模型、token 用量。
8. 管理端展示 LLM 分析详情。

交付结果：

- 输入 OCR 文本/语音转写文本后，生成结构化反诈报告。
- 当前规则引擎升级为规则 + LLM 混合分析。

优先级：P0

### 阶段 5：队列化分析流程

目标：让 OCR/ASR/LLM 这类耗时任务走 Job。

任务：

1. 新增 AnalyzeRiskJob。
2. 分析任务进入队列。
3. Job 内部执行：
   - 获取文件
   - OCR/ASR
   - LLM 分析
   - 保存报告
   - 扣点/记录流水
4. 失败时保存 `error_message`。
5. 支持 `retry_count` / `max_retry`。
6. 管理端重试触发重新派发 Job。

交付结果：

- 分析流程稳定，不阻塞接口。
- 失败可追踪、可重试。

优先级：P0

### 阶段 6：用户点数与扣费闭环

目标：分析一次消耗点数。

任务：

1. 定义一次分析消耗多少点。
2. 提交分析前校验余额。
3. 明确扣点时机。
4. 写入 `point_transactions`。
5. 明确失败是否退点。
6. 管理端可查点数流水。

建议规则：

- 提交任务时冻结点数。
- 分析成功后确认扣除。
- 分析失败后释放/退回。

交付结果：

- 用户不能无限调用 LLM。
- 点数流水可追踪。

优先级：P1

### 阶段 7：接 ASR 录音转写

目标：录音上传后能转写文字。

任务：

1. 确认 ASR 服务选型。
2. 新增 ASR Client。
3. 对 `file_assets` 回填：
   - `transcript_text`
   - `transcript_status`
   - `transcript_error`
4. 在分析任务中根据文件类型判断走 OCR 还是 ASR。
5. 管理端展示转写文本。

交付结果：

- 录音可以转文字。
- 图片和语音都能进入统一 LLM 分析链路。

优先级：P1

### 阶段 8：真实微信登录

目标：用户身份体系从 mock 变成真实小程序登录。

任务：

1. 接 `code2session`。
2. 保存 `openid` / `unionid`。
3. 生成登录 token。
4. 补 token 中间件。
5. 用户端接口读取真实 `user_id`。
6. 处理老 mock 用户数据迁移或清理。

交付结果：

- 小程序用户可以真实登录。
- 数据归属真实用户。

优先级：P1

### 阶段 9：微信支付与充值闭环

目标：用户可以买点数。

任务：

1. 新增充值套餐表。
2. 新增订单表。
3. 创建微信支付订单。
4. 支付回调验签。
5. 幂等处理。
6. 支付成功增加点数。
7. 写入 `point_transactions`。
8. 管理端查看订单与充值记录。

交付结果：

- 用户充值 → 点数到账 → 分析扣点。

优先级：P1

### 阶段 10：管理端权限和审计

目标：让后台具备上线基本安全性。

任务：

1. 管理员登录。
2. JWT 鉴权。
3. RBAC 权限。
4. 操作日志。
5. 敏感操作审计：
   - 重试分析
   - 修改风险规则
   - 查看用户文件
   - 调整点数

交付结果：

- 管理后台可控、可审计。

优先级：P1

### 阶段 11：OpenAPI / Apifox 文档

目标：方便前后端联调。

任务：

1. 用户端 OpenAPI。
2. 管理端 OpenAPI。
3. storage 服务对接文档。
4. LLM 分析状态说明。
5. 错误码整理。
6. 示例请求/响应。

交付结果：

- 前端、小程序、后台都可以按文档联调。

优先级：P2

## 6. 推荐执行顺序

后续建议按以下顺序推进：

1. 主服务接 storage 上传。
2. 分析任务状态机。
3. OCR 图片识别。
4. LLM 风险分析。
5. 队列 Job 异步分析。
6. 点数扣费闭环。
7. ASR 录音转写。
8. 微信真实登录。
9. 微信支付充值。
10. 管理端权限/RBAC/审计。
11. OpenAPI/Apifox 文档。
12. 管理后台前端联调。

## 7. 第一阶段建议范围

如果要最快做出可演示版本，第一期建议只做：

> storage 打通 → OCR → LLM → 异步状态 → 报告详情

暂时不处理：

- 微信支付
- 真实微信登录
- RBAC
- 完整管理后台前端

第一阶段完成后，应达到：用户上传图片后，系统可以自动识别图片内容，调用 LLM 输出反诈分析报告，并支持用户查看分析状态与报告详情。

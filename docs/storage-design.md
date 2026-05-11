# 对象存储设计

首期使用 AWS S3 SDK 作为统一 S3 兼容客户端：

- 腾讯云 COS：通过 `TENCENT_COS_*` 配置接入。
- Cloudflare R2：通过 `R2_*` 配置接入。

Cloudflare R2 的 `R2_SECRET_ACCESS_KEY` 要填写 S3 凭证的 Secret Access Key。如果使用 Cloudflare API 创建 token，返回的 `cfat_` token value 需要先做 SHA-256，结果才是 S3 SDK 使用的 Secret Access Key。

上传文件后会保存 `file_objects` 元数据。业务侧只依赖 `file_id`，不直接依赖对象存储 Key，方便后续迁移 bucket、增加权限或切换 CDN。

当前接口保留两种下载方式：

- `download-url`：返回可访问 URL，适合公开文件或短期签名下载。
- `download`：服务端读取对象并流式输出，适合需要服务端鉴权的场景。

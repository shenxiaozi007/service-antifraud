# object-storage-service API

## 上传文件

```http
POST /service/api/v1/file/upload
Content-Type: multipart/form-data
```

参数：

```text
file      文件，字段名不限
file_name 可选，自定义文件名
disk      可选，tencent_cos / cloudflare_r2
```

响应：

```json
{
  "code": 0,
  "message": "success!",
  "data": {
    "file_id": "file_xxx",
    "disk": "tencent_cos",
    "bucket": "bucket-name",
    "object_key": "2026/05/11/file_xxx.pdf",
    "original_name": "demo.pdf",
    "mime_type": "application/pdf",
    "extension": "pdf",
    "size": 1024,
    "hash": "md5",
    "file_url": "https://..."
  }
}
```

## 文件详情

```http
GET /service/api/v1/file/detail?file_id=file_xxx
```

## 获取下载地址

```http
GET /service/api/v1/file/download-url?file_id=file_xxx&expires=1800
```

`expires` 为可选秒数。配置了 CDN 域名时返回 CDN 公共地址；未配置时可返回 S3 兼容地址或预签名地址。

## 流式下载

```http
GET /service/api/v1/file/download?file_id=file_xxx
```

## 存储类型

```http
GET /service/api/v1/file/disks
```

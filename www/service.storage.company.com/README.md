# object-storage-service

公共对象存储服务，统一接入腾讯云 COS 与 Cloudflare R2，提供文件上传、文件详情、下载地址和流式下载接口。

## 目录

```text
app/Http/Controllers/Service/Api/V1/File
app/Modules/Service/Business
app/Modules/Basics/Dao/File
app/Modules/Basics/Model/File
app/Libraries/Storage
routes/service/api/v1/file
config/storage.php
config/upload.php
```

## 初始化

```bash
composer install
cp .env.example .env
php artisan migrate
```

## 接口

```text
POST /service/api/v1/file/upload
GET  /service/api/v1/file/detail
GET  /service/api/v1/file/download-url
GET  /service/api/v1/file/download
GET  /service/api/v1/file/disks
```

上传时使用 `multipart/form-data`，文件字段名不限，服务会取第一个上传文件；可选参数：

```text
file_name 自定义文件名
disk      tencent_cos 或 cloudflare_r2
```

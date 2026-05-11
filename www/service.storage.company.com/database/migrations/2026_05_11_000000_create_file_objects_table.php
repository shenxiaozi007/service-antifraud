<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('file_objects', function (Blueprint $table) {
            $table->id();
            $table->string('file_id', 64)->unique()->comment('文件业务 ID');
            $table->string('disk', 64)->comment('存储磁盘');
            $table->string('bucket', 128)->comment('存储桶');
            $table->string('object_key', 512)->comment('对象 Key');
            $table->string('original_name', 255)->comment('原始文件名');
            $table->string('mime_type', 128)->default('application/octet-stream')->comment('MIME 类型');
            $table->string('extension', 32)->default('')->comment('扩展名');
            $table->unsignedBigInteger('size')->default(0)->comment('文件大小');
            $table->string('hash', 64)->index()->comment('文件 MD5');
            $table->tinyInteger('status')->default(1)->comment('状态：1 正常');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['disk', 'hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_objects');
    }
};

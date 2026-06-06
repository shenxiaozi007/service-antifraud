<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->comment('用户 - 微信用户和点数余额');
            $table->bigIncrements('id');
            $table->string('openid', 128)->default('')->unique('uniq_openid')->comment('微信 openid');
            $table->string('unionid', 128)->nullable()->comment('微信 unionid');
            $table->string('nickname', 128)->nullable()->comment('昵称');
            $table->string('avatar_url', 512)->nullable()->comment('头像');
            $table->integer('points_balance')->default(30)->comment('点数余额');
            $table->tinyInteger('status')->default(1)->index('users_status_index')->comment('状态 1正常 0禁用');
            $table->string('api_token', 128)->default('')->unique('uniq_api_token')->comment('接口令牌');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('analysis_records', function (Blueprint $table) {
            $table->comment('分析 - 风险分析记录');
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('analysis_records_user_id_index')->comment('用户ID');
            $table->string('type', 20)->index('analysis_records_type_index')->comment('类型 image/audio');
            $table->string('title', 255)->default('')->comment('报告标题');
            $table->string('risk_level', 20)->default('low')->index('analysis_records_risk_level_index')->comment('风险等级');
            $table->integer('risk_score')->default(0)->comment('风险分');
            $table->text('summary')->nullable()->comment('一句话结论');
            $table->json('suggestions')->nullable()->comment('建议动作');
            $table->string('status', 20)->default('pending')->index('analysis_records_status_index')->comment('状态');
            $table->integer('cost_points')->default(0)->comment('实际扣除点数');
            $table->integer('frozen_points')->default(0)->comment('冻结点数');
            $table->unsignedInteger('image_count')->default(0)->comment('图片数量');
            $table->unsignedInteger('duration_seconds')->default(0)->comment('录音秒数');
            $table->timestamp('analyzed_at')->nullable()->comment('分析完成时间');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['user_id', 'created_at'], 'analysis_records_user_created_index');
        });

        Schema::create('risk_items', function (Blueprint $table) {
            $table->comment('分析 - 风险点');
            $table->bigIncrements('id');
            $table->unsignedBigInteger('record_id')->index('risk_items_record_id_index')->comment('分析记录ID');
            $table->string('category', 64)->index('risk_items_category_index')->comment('风险分类');
            $table->string('severity', 20)->default('medium')->comment('严重程度');
            $table->text('description')->comment('风险说明');
            $table->text('evidence_text')->nullable()->comment('关键证据');
            $table->timestamps();
        });

        Schema::create('file_assets', function (Blueprint $table) {
            $table->comment('文件 - 用户上传素材');
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('file_assets_user_id_index')->comment('用户ID');
            $table->unsignedBigInteger('record_id')->nullable()->index('file_assets_record_id_index')->comment('分析记录ID');
            $table->string('file_type', 20)->index('file_assets_file_type_index')->comment('文件类型 image/audio');
            $table->string('storage_key', 512)->default('')->comment('存储Key');
            $table->string('file_url', 1024)->nullable()->comment('访问URL');
            $table->string('mime_type', 100)->nullable()->comment('MIME类型');
            $table->unsignedBigInteger('file_size')->default(0)->comment('文件大小');
            $table->longText('ocr_text')->nullable()->comment('OCR文本');
            $table->longText('transcript_text')->nullable()->comment('ASR文本');
            $table->softDeletes();
            $table->timestamps();
            $table->index(['user_id', 'record_id'], 'file_assets_user_record_index');
        });

        Schema::create('point_transactions', function (Blueprint $table) {
            $table->comment('点数 - 流水');
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('point_transactions_user_id_index')->comment('用户ID');
            $table->unsignedBigInteger('related_record_id')->nullable()->index('point_transactions_related_record_id_index')->comment('关联分析记录ID');
            $table->integer('amount')->comment('变动点数');
            $table->integer('balance_after')->comment('变动后余额');
            $table->string('type', 30)->index('point_transactions_type_index')->comment('类型');
            $table->string('status', 20)->default('completed')->index('point_transactions_status_index')->comment('状态');
            $table->string('remark', 255)->nullable()->comment('备注');
            $table->timestamps();
            $table->index(['user_id', 'created_at'], 'point_transactions_user_created_index');
        });

        Schema::create('risk_rules', function (Blueprint $table) {
            $table->comment('风控 - 关键词规则');
            $table->bigIncrements('id');
            $table->string('category', 64)->index('risk_rules_category_index')->comment('分类');
            $table->string('keyword', 255)->comment('关键词');
            $table->string('severity', 20)->default('medium')->comment('严重程度');
            $table->integer('weight')->default(10)->comment('权重');
            $table->tinyInteger('enabled')->default(1)->index('risk_rules_enabled_index')->comment('是否启用');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_rules');
        Schema::dropIfExists('point_transactions');
        Schema::dropIfExists('file_assets');
        Schema::dropIfExists('risk_items');
        Schema::dropIfExists('analysis_records');
        Schema::dropIfExists('users');
    }
};

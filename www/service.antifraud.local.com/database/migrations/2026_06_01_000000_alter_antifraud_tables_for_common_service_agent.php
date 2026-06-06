<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('global_user_id')->default(0)->after('id')->index('users_global_user_id_index')->comment('公共用户ID');
            $table->string('project_code', 64)->default('antifraud')->after('global_user_id')->index('users_project_code_index')->comment('项目编码');
            $table->string('email', 191)->default('')->after('unionid')->index('users_email_index')->comment('邮箱');
            $table->string('mobile', 32)->default('')->after('email')->index('users_mobile_index')->comment('手机号');
        });

        Schema::table('file_assets', function (Blueprint $table) {
            $table->string('storage_file_id', 64)->default('')->after('record_id')->index('file_assets_storage_file_id_index')->comment('公共文件ID');
            $table->string('ocr_status', 20)->default('pending')->after('ocr_text')->index('file_assets_ocr_status_index')->comment('OCR状态');
            $table->text('ocr_error')->nullable()->after('ocr_status')->comment('OCR错误');
            $table->string('transcript_status', 20)->default('pending')->after('transcript_text')->index('file_assets_transcript_status_index')->comment('ASR状态');
            $table->text('transcript_error')->nullable()->after('transcript_status')->comment('ASR错误');
        });

        Schema::table('analysis_records', function (Blueprint $table) {
            $table->text('error_message')->nullable()->after('status')->comment('错误信息');
            $table->unsignedInteger('retry_count')->default(0)->after('error_message')->comment('重试次数');
            $table->string('llm_model', 128)->default('')->after('retry_count')->comment('LLM模型');
            $table->unsignedInteger('llm_duration_ms')->default(0)->after('llm_model')->comment('LLM耗时毫秒');
            $table->json('llm_raw_output')->nullable()->after('llm_duration_ms')->comment('LLM原始输出');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_records', function (Blueprint $table) {
            $table->dropColumn(['error_message', 'retry_count', 'llm_model', 'llm_duration_ms', 'llm_raw_output']);
        });

        Schema::table('file_assets', function (Blueprint $table) {
            $table->dropIndex('file_assets_storage_file_id_index');
            $table->dropIndex('file_assets_ocr_status_index');
            $table->dropIndex('file_assets_transcript_status_index');
            $table->dropColumn(['storage_file_id', 'ocr_status', 'ocr_error', 'transcript_status', 'transcript_error']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_global_user_id_index');
            $table->dropIndex('users_project_code_index');
            $table->dropIndex('users_email_index');
            $table->dropIndex('users_mobile_index');
            $table->dropColumn(['global_user_id', 'project_code', 'email', 'mobile']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_reward_records', function (Blueprint $table) {
            $table->comment('公共 - 广告奖励记录');
            $table->bigIncrements('id');
            $table->string('reward_no', 64)->default('')->comment('奖励编号')->unique('reward_no_unique');
            $table->unsignedBigInteger('user_id')->index('user_id_index')->comment('用户ID');
            $table->string('project_code', 64)->default('')->index('project_code_index')->comment('项目编码');
            $table->string('scene', 64)->default('rewarded_video')->index('scene_index')->comment('广告场景');
            $table->string('platform', 64)->default('')->index('platform_index')->comment('广告平台');
            $table->string('ad_unit_id', 128)->default('')->comment('广告位ID');
            $table->string('idempotency_key', 128)->default('')->comment('幂等键');
            $table->unsignedInteger('reward_points')->default(0)->comment('奖励点数');
            $table->unsignedInteger('reward_date')->default(0)->index('reward_date_index')->comment('奖励日期Ymd');
            $table->string('wallet_related_no', 64)->default('')->comment('钱包关联编号');
            $table->string('status', 20)->default('completed')->index('status_index')->comment('状态');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();
            $table->unique(['project_code', 'user_id', 'idempotency_key'], 'project_user_idempotency_unique');
            $table->index(['project_code', 'user_id', 'scene', 'reward_date'], 'project_user_scene_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_reward_records');
    }
};

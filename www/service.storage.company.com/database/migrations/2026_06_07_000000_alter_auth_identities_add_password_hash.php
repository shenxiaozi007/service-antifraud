<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 增加密码登录凭据字段。
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('auth_identities', function (Blueprint $table) {
            $table->string('password_hash', 255)->default('')->comment('密码哈希');
            $table->timestamp('password_updated_at')->nullable()->comment('密码更新时间');
        });
    }

    /**
     * 回滚密码登录凭据字段。
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('auth_identities', function (Blueprint $table) {
            $table->dropColumn(['password_hash', 'password_updated_at']);
        });
    }
};

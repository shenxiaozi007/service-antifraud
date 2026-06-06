<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('file_objects', function (Blueprint $table) {
            $table->string('owner_project', 64)->default('')->after('file_id')->index('owner_project_index')->comment('归属项目');
            $table->unsignedBigInteger('owner_user_id')->default(0)->after('owner_project')->index('owner_user_id_index')->comment('归属用户ID');
            $table->string('biz_type', 64)->default('')->after('owner_user_id')->index('biz_type_index')->comment('业务类型');
        });
    }

    public function down(): void
    {
        Schema::table('file_objects', function (Blueprint $table) {
            $table->dropIndex('owner_project_index');
            $table->dropIndex('owner_user_id_index');
            $table->dropIndex('biz_type_index');
            $table->dropColumn(['owner_project', 'owner_user_id', 'biz_type']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->comment('公共 - 用户主档');
            $table->bigIncrements('id');
            $table->string('nickname', 128)->default('')->comment('昵称');
            $table->string('avatar_url', 512)->default('')->comment('头像');
            $table->tinyInteger('status')->default(1)->index('users_status_index')->comment('状态 1正常 0禁用');
            $table->timestamp('last_login_at')->nullable()->comment('最后登录时间');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('auth_identities', function (Blueprint $table) {
            $table->comment('公共 - 登录身份');
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('auth_identities_user_id_index')->comment('用户ID');
            $table->string('identity_type', 32)->comment('身份类型 wechat/email/mobile');
            $table->string('identifier', 191)->comment('身份标识');
            $table->string('unionid', 128)->default('')->comment('微信 unionid');
            $table->json('extra')->nullable()->comment('扩展信息');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['identity_type', 'identifier'], 'identity_type_identifier_unique');
        });

        Schema::create('auth_tokens', function (Blueprint $table) {
            $table->comment('公共 - 登录令牌');
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('auth_tokens_user_id_index')->comment('用户ID');
            $table->string('token', 128)->unique('token_unique')->comment('Token');
            $table->string('client_type', 32)->default('api')->comment('客户端类型');
            $table->timestamp('expires_at')->nullable()->comment('过期时间');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('verification_codes', function (Blueprint $table) {
            $table->comment('公共 - 验证码');
            $table->bigIncrements('id');
            $table->string('account', 191)->index('verification_codes_account_index')->comment('邮箱或手机号');
            $table->string('scene', 32)->default('login')->comment('场景');
            $table->string('code', 16)->comment('验证码');
            $table->string('status', 20)->default('pending')->index('verification_codes_status_index')->comment('状态');
            $table->timestamp('expires_at')->comment('过期时间');
            $table->timestamp('used_at')->nullable()->comment('使用时间');
            $table->timestamps();
        });

        Schema::create('project_wallets', function (Blueprint $table) {
            $table->comment('公共 - 项目钱包');
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('project_wallets_user_id_index')->comment('用户ID');
            $table->string('project_code', 64)->index('project_wallets_project_code_index')->comment('项目编码');
            $table->integer('balance')->default(0)->comment('可用点数');
            $table->integer('frozen_balance')->default(0)->comment('冻结点数');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'project_code'], 'user_id_project_code_unique');
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->comment('公共 - 钱包流水');
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index('wallet_transactions_user_id_index')->comment('用户ID');
            $table->string('project_code', 64)->index('wallet_transactions_project_code_index')->comment('项目编码');
            $table->string('transaction_no', 64)->unique('transaction_no_unique')->comment('流水号');
            $table->string('related_no', 64)->default('')->index('related_no_index')->comment('关联业务号');
            $table->integer('amount')->default(0)->comment('可用点数变化');
            $table->integer('frozen_amount')->default(0)->comment('冻结点数变化');
            $table->integer('balance_after')->default(0)->comment('变动后可用点数');
            $table->integer('frozen_after')->default(0)->comment('变动后冻结点数');
            $table->string('type', 32)->index('wallet_transactions_type_index')->comment('类型');
            $table->string('status', 20)->default('completed')->index('wallet_transactions_status_index')->comment('状态');
            $table->string('remark', 255)->default('')->comment('备注');
            $table->timestamps();
        });

        Schema::create('payment_packages', function (Blueprint $table) {
            $table->comment('公共 - 支付套餐');
            $table->bigIncrements('id');
            $table->string('project_code', 64)->index('payment_packages_project_code_index')->comment('项目编码');
            $table->string('name', 128)->comment('套餐名称');
            $table->integer('points')->comment('点数');
            $table->integer('amount_cent')->comment('金额 分');
            $table->tinyInteger('enabled')->default(1)->index('payment_packages_enabled_index')->comment('是否启用');
            $table->unsignedInteger('sort')->default(0)->comment('排序');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('payment_orders', function (Blueprint $table) {
            $table->comment('公共 - 支付订单');
            $table->bigIncrements('id');
            $table->string('order_no', 64)->unique('order_no_unique')->comment('订单号');
            $table->unsignedBigInteger('user_id')->index('payment_orders_user_id_index')->comment('用户ID');
            $table->string('project_code', 64)->index('payment_orders_project_code_index')->comment('项目编码');
            $table->unsignedBigInteger('package_id')->index('payment_orders_package_id_index')->comment('套餐ID');
            $table->integer('points')->comment('点数');
            $table->integer('amount_cent')->comment('金额 分');
            $table->string('channel', 32)->default('wechat')->index('payment_orders_channel_index')->comment('支付渠道');
            $table->string('status', 20)->default('pending')->index('payment_orders_status_index')->comment('状态');
            $table->string('prepay_id', 128)->default('')->comment('微信预支付ID');
            $table->string('transaction_id', 128)->default('')->index('transaction_id_index')->comment('微信支付单号');
            $table->json('payment_params')->nullable()->comment('调起支付参数');
            $table->json('notify_payload')->nullable()->comment('回调内容');
            $table->timestamp('paid_at')->nullable()->comment('支付时间');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_orders');
        Schema::dropIfExists('payment_packages');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('project_wallets');
        Schema::dropIfExists('verification_codes');
        Schema::dropIfExists('auth_tokens');
        Schema::dropIfExists('auth_identities');
        Schema::dropIfExists('users');
    }
};

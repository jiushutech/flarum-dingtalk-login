<?php

/*
 * This file is part of jiushutech/flarum-dingtalk-login.
 *
 * Copyright (c) JiushuTech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('dingtalk_login_logs')) {
            return;
        }

        $schema->create('dingtalk_login_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->nullable()->index()->comment('Flarum用户ID');
            $table->string('dingtalk_user_id', 64)->nullable()->comment('钉钉用户ID');
            $table->string('login_ip', 45)->nullable()->comment('登录IP');
            $table->string('user_agent', 500)->nullable()->comment('用户代理');
            $table->enum('login_type', ['scan', 'h5', 'redirect'])->default('scan')->comment('登录类型');
            $table->enum('status', ['success', 'failed'])->default('success')->comment('状态');
            $table->text('error_message')->nullable()->comment('错误信息');
            $table->string('corp_id', 64)->nullable()->comment('企业ID');
            $table->timestamp('created_at')->useCurrent()->index()->comment('登录时间');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('dingtalk_login_logs');
    },
];

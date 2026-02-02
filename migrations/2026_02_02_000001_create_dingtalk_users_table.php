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
        if ($schema->hasTable('dingtalk_users')) {
            return;
        }

        $schema->create('dingtalk_users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->unique()->comment('Flarum用户ID');
            $table->string('dingtalk_user_id', 64)->unique()->comment('钉钉用户ID（unionid）');
            $table->string('dingtalk_openid', 64)->nullable()->comment('钉钉openid');
            $table->string('dingtalk_nickname', 255)->nullable()->comment('钉钉昵称');
            $table->string('dingtalk_avatar', 500)->nullable()->comment('钉钉头像URL');
            $table->string('dingtalk_mobile', 255)->nullable()->comment('手机号（AES加密）');
            $table->string('dingtalk_email', 255)->nullable()->comment('邮箱（AES加密）');
            $table->string('corp_id', 64)->nullable()->index()->comment('企业ID');
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('dingtalk_users');
    },
];

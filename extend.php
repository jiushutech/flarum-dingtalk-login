<?php

/*
 * This file is part of jiushutech/flarum-dingtalk-login.
 *
 * Copyright (c) JiushuTech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Flarum\Extend;
use Flarum\User\User;
use Flarum\Api\Serializer\CurrentUserSerializer;
use JiushuTech\DingtalkLogin\Api\Controller\DingtalkAuthController;
use JiushuTech\DingtalkLogin\Api\Controller\DingtalkCallbackController;
use JiushuTech\DingtalkLogin\Api\Controller\DingtalkBindController;
use JiushuTech\DingtalkLogin\Api\Controller\DingtalkBindStatusController;
use JiushuTech\DingtalkLogin\Api\Controller\DingtalkUnbindController;
use JiushuTech\DingtalkLogin\Api\Controller\DingtalkH5LoginController;
use JiushuTech\DingtalkLogin\Api\Controller\DingtalkLoginLogController;
use JiushuTech\DingtalkLogin\Api\Controller\DingtalkAdminUnbindController;
use JiushuTech\DingtalkLogin\Api\Controller\DingtalkUsersListController;
use JiushuTech\DingtalkLogin\Api\Controller\DingtalkLogsExportController;
use JiushuTech\DingtalkLogin\Http\Middleware\RequireDingtalkBinding;
use JiushuTech\DingtalkLogin\Http\Middleware\DingtalkOnlyLogin;
use JiushuTech\DingtalkLogin\Console\CleanupLoginLogsCommand;
use JiushuTech\DingtalkLogin\Listener\AddDingtalkLoginLog;
use JiushuTech\DingtalkLogin\Event\DingtalkLoginSucceeded;
use JiushuTech\DingtalkLogin\Provider\DingtalkAuthServiceProvider;
use JiushuTech\DingtalkLogin\Model\DingtalkUser;

return [
    // 注册前端资源
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/resources/less/forum.less'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/resources/less/admin.less'),

    // 注册语言包
    new Extend\Locales(__DIR__.'/resources/locale'),

    // 注册路由 - 授权相关
    (new Extend\Routes('forum'))
        ->get('/auth/dingtalk', 'auth.dingtalk', DingtalkAuthController::class)
        ->get('/auth/dingtalk/callback', 'auth.dingtalk.callback', DingtalkCallbackController::class),

    // 注册API路由
    (new Extend\Routes('api'))
        ->get('/dingtalk/bind-status', 'dingtalk.bind-status', DingtalkBindStatusController::class)
        ->post('/dingtalk/bind', 'dingtalk.bind', DingtalkBindController::class)
        ->delete('/dingtalk/unbind', 'dingtalk.unbind', DingtalkUnbindController::class)
        ->post('/dingtalk/h5-login', 'dingtalk.h5login', DingtalkH5LoginController::class)
        ->get('/dingtalk/users', 'dingtalk.users', DingtalkUsersListController::class)
        ->get('/dingtalk/login-logs', 'dingtalk.login-logs', DingtalkLoginLogController::class)
        ->get('/dingtalk/logs-export', 'dingtalk.logs-export', DingtalkLogsExportController::class)
        ->delete('/dingtalk/users/{id}/unbind', 'dingtalk.admin.unbind', DingtalkAdminUnbindController::class),

    // 注册中间件
    (new Extend\Middleware('forum'))
        ->add(RequireDingtalkBinding::class)
        ->add(DingtalkOnlyLogin::class),

    // 注册事件监听器
    (new Extend\Event())
        ->listen(DingtalkLoginSucceeded::class, AddDingtalkLoginLog::class),

    // 注册控制台命令
    (new Extend\Console())
        ->command(CleanupLoginLogsCommand::class),

    // 注册服务提供者
    (new Extend\ServiceProvider())
        ->register(DingtalkAuthServiceProvider::class),

    // 添加用户属性 - 钉钉绑定状态
    (new Extend\ApiSerializer(CurrentUserSerializer::class))
        ->attribute('dingtalkBound', function (CurrentUserSerializer $serializer, User $user) {
            $dingtalkUser = DingtalkUser::where('user_id', $user->id)->first();
            return $dingtalkUser !== null;
        }),

    // 注册设置
    (new Extend\Settings())
        ->default('jiushutech-dingtalk-login.force_bind', false)
        ->default('jiushutech-dingtalk-login.only_dingtalk_login', false)
        ->default('jiushutech-dingtalk-login.auto_register', true)
        ->default('jiushutech-dingtalk-login.enterprise_only', false)
        ->default('jiushutech-dingtalk-login.sync_nickname', true)
        ->default('jiushutech-dingtalk-login.sync_avatar', true)
        ->default('jiushutech-dingtalk-login.sync_mobile', false)
        ->default('jiushutech-dingtalk-login.sync_email', false)
        ->default('jiushutech-dingtalk-login.username_rule', 'nickname')
        ->default('jiushutech-dingtalk-login.log_retention_days', 30)
        ->default('jiushutech-dingtalk-login.allow_log_export', true)
        ->default('jiushutech-dingtalk-login.enable_h5_login', true)
        ->default('jiushutech-dingtalk-login.show_on_index', false)
        ->default('jiushutech-dingtalk-login.show_login_button', true)
        ->serializeToForum('dingtalkLoginEnabled', 'jiushutech-dingtalk-login.app_key', function ($value) {
            return !empty($value);
        })
        ->serializeToForum('dingtalkShowLoginButton', 'jiushutech-dingtalk-login.show_login_button', function ($value) {
            // 当值为 '0' 时返回 false，其他情况（包括 null、'1'、true）返回 true
            if ($value === '0' || $value === false) {
                return false;
            }
            return true;
        })
        ->serializeToForum('dingtalkOnlyLogin', 'jiushutech-dingtalk-login.only_dingtalk_login', function ($value) {
            return $value === '1' || $value === true;
        })
        ->serializeToForum('dingtalkForceBind', 'jiushutech-dingtalk-login.force_bind', function ($value) {
            return $value === '1' || $value === true;
        })
        ->serializeToForum('dingtalkH5Enabled', 'jiushutech-dingtalk-login.enable_h5_login', function ($value) {
            return $value === '1' || $value === true;
        })
        ->serializeToForum('dingtalkAgentId', 'jiushutech-dingtalk-login.agent_id')
        ->serializeToForum('dingtalkCorpId', 'jiushutech-dingtalk-login.corp_id')
        ->serializeToForum('dingtalkShowOnIndex', 'jiushutech-dingtalk-login.show_on_index', function ($value) {
            return $value === '1' || $value === true;
        }),
];

<?php

/*
 * This file is part of jiushutech/flarum-dingtalk-login.
 *
 * Copyright (c) JiushuTech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JiushuTech\DingtalkLogin\Api\Controller;

use Flarum\Http\RequestUtil;
use Flarum\User\User;
use JiushuTech\DingtalkLogin\Model\DingtalkUser;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkBindStatsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 验证管理员权限
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        // 获取总用户数
        $totalUsers = User::count();

        // 获取已绑定钉钉的用户数
        $boundUsers = DingtalkUser::count();

        // 计算百分比
        $percentage = $totalUsers > 0 ? round(($boundUsers / $totalUsers) * 100, 1) : 0;

        return new JsonResponse([
            'data' => [
                'totalUsers' => $totalUsers,
                'boundUsers' => $boundUsers,
                'percentage' => $percentage,
            ],
        ]);
    }
}

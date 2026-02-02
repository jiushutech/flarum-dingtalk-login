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
use JiushuTech\DingtalkLogin\Service\DingtalkUserService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkAdminUnbindController implements RequestHandlerInterface
{
    protected DingtalkUserService $userService;

    public function __construct(DingtalkUserService $userService)
    {
        $this->userService = $userService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        
        // 必须是管理员
        if (!$actor->isAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => '权限不足',
            ], 403);
        }

        $id = $request->getAttribute('id');
        
        $dingtalkUser = DingtalkUser::find($id);
        
        if (!$dingtalkUser) {
            return new JsonResponse([
                'success' => false,
                'message' => '绑定记录不存在',
            ], 404);
        }

        $user = $dingtalkUser->user;
        
        if (!$user) {
            // 用户已被删除，直接删除绑定记录
            $dingtalkUser->delete();
            return new JsonResponse([
                'success' => true,
                'message' => '解绑成功',
            ]);
        }

        try {
            $this->userService->unbindDingtalkAccount($user);

            return new JsonResponse([
                'success' => true,
                'message' => '解绑成功',
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

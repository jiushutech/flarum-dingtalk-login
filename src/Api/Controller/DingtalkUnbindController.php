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
use JiushuTech\DingtalkLogin\Service\DingtalkUserService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkUnbindController implements RequestHandlerInterface
{
    protected DingtalkUserService $userService;

    public function __construct(DingtalkUserService $userService)
    {
        $this->userService = $userService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        
        // 必须登录
        if ($actor->isGuest()) {
            return new JsonResponse([
                'success' => false,
                'message' => '请先登录',
            ], 401);
        }

        try {
            $this->userService->unbindDingtalkAccount($actor);

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

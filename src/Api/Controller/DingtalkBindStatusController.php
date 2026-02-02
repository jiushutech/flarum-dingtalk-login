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

class DingtalkBindStatusController implements RequestHandlerInterface
{
    protected DingtalkUserService $userService;

    public function __construct(DingtalkUserService $userService)
    {
        $this->userService = $userService;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        if ($actor->isGuest()) {
            return new JsonResponse([
                'bound' => false,
                'message' => 'Not logged in',
            ], 401);
        }

        $dingtalkUser = $this->userService->getDingtalkUser($actor);

        return new JsonResponse([
            'bound' => $dingtalkUser !== null,
            'dingtalk_nickname' => $dingtalkUser?->dingtalk_nickname,
            'dingtalk_avatar' => $dingtalkUser?->dingtalk_avatar,
            'dingtalk_user_id' => $dingtalkUser?->dingtalk_user_id,
            'dingtalk_mobile' => $dingtalkUser?->dingtalk_mobile,
            'dingtalk_email' => $dingtalkUser?->dingtalk_email,
        ]);
    }
}

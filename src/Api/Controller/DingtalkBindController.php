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
use JiushuTech\DingtalkLogin\Service\DingtalkApiClient;
use JiushuTech\DingtalkLogin\Service\DingtalkUserService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkBindController implements RequestHandlerInterface
{
    protected DingtalkApiClient $apiClient;
    protected DingtalkUserService $userService;

    public function __construct(
        DingtalkApiClient $apiClient,
        DingtalkUserService $userService
    ) {
        $this->apiClient = $apiClient;
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

        $body = $request->getParsedBody();
        $code = $body['code'] ?? null;
        $type = $body['type'] ?? 'scan'; // scan 或 h5

        if (empty($code)) {
            return new JsonResponse([
                'success' => false,
                'message' => '授权码缺失',
            ], 400);
        }

        try {
            // 根据类型获取用户信息
            if ($type === 'h5') {
                $userInfo = $this->apiClient->getUserInfoByH5Code($code);
            } else {
                $tokenData = $this->apiClient->getUserAccessTokenByCode($code);
                $userInfo = $this->apiClient->getUserInfo($tokenData['accessToken']);
            }

            // 绑定账号
            $dingtalkUser = $this->userService->bindDingtalkAccount($actor, $userInfo);

            return new JsonResponse([
                'success' => true,
                'message' => '绑定成功',
                'data' => [
                    'dingtalkNickname' => $dingtalkUser->dingtalk_nickname,
                    'dingtalkAvatar' => $dingtalkUser->dingtalk_avatar,
                ],
            ]);

        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

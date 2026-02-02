<?php

/*
 * This file is part of jiushutech/flarum-dingtalk-login.
 *
 * Copyright (c) JiushuTech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JiushuTech\DingtalkLogin\Http\Middleware;

use Flarum\Http\RequestUtil;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use JiushuTech\DingtalkLogin\Service\DingtalkUserService;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequireDingtalkBinding implements MiddlewareInterface
{
    protected SettingsRepositoryInterface $settings;
    protected DingtalkUserService $userService;
    protected UrlGenerator $url;

    // 不需要绑定检查的路径
    protected array $excludedPaths = [
        '/auth/dingtalk',
        '/auth/dingtalk/callback',
        '/api/dingtalk/bind',
        '/api/dingtalk/h5-login',
        '/logout',
        '/login',
        '/register',
    ];

    public function __construct(
        SettingsRepositoryInterface $settings,
        DingtalkUserService $userService,
        UrlGenerator $url
    ) {
        $this->settings = $settings;
        $this->userService = $userService;
        $this->url = $url;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 检查是否启用强制绑定
        if (!$this->settings->get('jiushutech-dingtalk-login.force_bind', false)) {
            return $handler->handle($request);
        }

        $actor = RequestUtil::getActor($request);
        
        // 未登录用户不检查
        if ($actor->isGuest()) {
            return $handler->handle($request);
        }

        // 检查是否为豁免用户
        if ($this->userService->isExemptUser($actor)) {
            return $handler->handle($request);
        }

        // 检查是否已绑定
        if ($this->userService->isUserBound($actor)) {
            return $handler->handle($request);
        }

        // 检查是否为排除的路径
        $path = $request->getUri()->getPath();
        foreach ($this->excludedPaths as $excludedPath) {
            if (strpos($path, $excludedPath) !== false) {
                return $handler->handle($request);
            }
        }

        // 检查是否为API请求
        if (strpos($path, '/api/') === 0) {
            return new JsonResponse([
                'errors' => [
                    [
                        'status' => '403',
                        'code' => 'dingtalk_binding_required',
                        'title' => '需要绑定钉钉账号',
                        'detail' => '请先绑定钉钉账号以继续使用',
                    ],
                ],
            ], 403);
        }

        // 重定向到绑定页面
        // 前端会处理这个状态并显示绑定弹窗
        return $handler->handle($request);
    }
}

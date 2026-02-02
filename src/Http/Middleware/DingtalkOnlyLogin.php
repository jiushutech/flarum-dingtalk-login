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
use Flarum\Settings\SettingsRepositoryInterface;
use JiushuTech\DingtalkLogin\Service\DingtalkUserService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkOnlyLogin implements MiddlewareInterface
{
    protected SettingsRepositoryInterface $settings;
    protected DingtalkUserService $userService;

    // 允许的登录相关路径
    protected array $allowedPaths = [
        '/auth/dingtalk',
        '/auth/dingtalk/callback',
        '/api/dingtalk/h5-login',
        '/api/dingtalk/bind',
        '/logout',
    ];

    // 需要拦截的原生登录路径
    protected array $blockedPaths = [
        '/login',
        '/api/token',
        '/api/forgot',
        '/register',
    ];

    public function __construct(
        SettingsRepositoryInterface $settings,
        DingtalkUserService $userService
    ) {
        $this->settings = $settings;
        $this->userService = $userService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // 检查是否启用仅钉钉登录
        if (!$this->settings->get('jiushutech-dingtalk-login.only_dingtalk_login', false)) {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $method = $request->getMethod();

        // 检查是否为允许的路径
        foreach ($this->allowedPaths as $allowedPath) {
            if (strpos($path, $allowedPath) !== false) {
                return $handler->handle($request);
            }
        }

        // 检查是否为需要拦截的路径
        $shouldBlock = false;
        foreach ($this->blockedPaths as $blockedPath) {
            if (strpos($path, $blockedPath) !== false) {
                $shouldBlock = true;
                break;
            }
        }

        if (!$shouldBlock) {
            return $handler->handle($request);
        }

        // 检查是否为豁免用户（通过用户名参数）
        $actor = RequestUtil::getActor($request);
        
        if (!$actor->isGuest() && $this->userService->isExemptUser($actor)) {
            return $handler->handle($request);
        }

        // 检查请求体中的用户名是否为豁免用户
        if ($method === 'POST') {
            $body = $request->getParsedBody();
            $identification = $body['identification'] ?? $body['username'] ?? $body['email'] ?? null;
            
            if ($identification && $this->isExemptIdentification($identification)) {
                return $handler->handle($request);
            }
        }

        // 拦截请求
        if (strpos($path, '/api/') === 0) {
            return new JsonResponse([
                'errors' => [
                    [
                        'status' => '403',
                        'code' => 'dingtalk_only_login',
                        'title' => '仅支持钉钉登录',
                        'detail' => '当前仅支持钉钉登录，请使用钉钉扫码登录',
                    ],
                ],
            ], 403);
        }

        // 对于非API请求，让前端处理
        return $handler->handle($request);
    }

    /**
     * 检查标识是否为豁免用户
     */
    protected function isExemptIdentification(string $identification): bool
    {
        $exemptUsers = $this->settings->get('jiushutech-dingtalk-login.exempt_users', '');
        
        if (empty($exemptUsers)) {
            return false;
        }

        $exemptList = array_map('trim', explode(',', $exemptUsers));
        
        return in_array($identification, $exemptList);
    }
}

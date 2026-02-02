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
use Flarum\Http\SessionAccessToken;
use Flarum\Http\SessionAuthenticator;
use Illuminate\Contracts\Events\Dispatcher;
use JiushuTech\DingtalkLogin\Event\DingtalkLoginSucceeded;
use JiushuTech\DingtalkLogin\Model\DingtalkLoginLog;
use JiushuTech\DingtalkLogin\Service\DingtalkApiClient;
use JiushuTech\DingtalkLogin\Service\DingtalkUserService;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkH5LoginController implements RequestHandlerInterface
{
    protected DingtalkApiClient $apiClient;
    protected DingtalkUserService $userService;
    protected SessionAuthenticator $authenticator;
    protected Dispatcher $events;

    public function __construct(
        DingtalkApiClient $apiClient,
        DingtalkUserService $userService,
        SessionAuthenticator $authenticator,
        Dispatcher $events
    ) {
        $this->apiClient = $apiClient;
        $this->userService = $userService;
        $this->authenticator = $authenticator;
        $this->events = $events;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $code = $body['code'] ?? null;
        $session = $request->getAttribute('session');

        // 获取客户端信息
        $ip = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent');

        if (empty($code)) {
            DingtalkLoginLog::logFailed(
                null,
                DingtalkLoginLog::LOGIN_TYPE_H5,
                $ip,
                $userAgent,
                'Missing H5 authorization code'
            );
            return new JsonResponse([
                'success' => false,
                'message' => '授权码缺失',
            ], 400);
        }

        try {
            // 获取H5免登用户信息
            $userInfo = $this->apiClient->getUserInfoByH5Code($code);
            
            // 检查企业限制
            $corpId = $userInfo['corpId'] ?? $this->apiClient->getCorpId();
            if ($corpId && !$this->apiClient->validateEnterpriseRestriction($corpId)) {
                DingtalkLoginLog::logFailed(
                    $userInfo['unionid'] ?? null,
                    DingtalkLoginLog::LOGIN_TYPE_H5,
                    $ip,
                    $userAgent,
                    'Enterprise restriction: User not in allowed enterprise',
                    $corpId
                );
                return new JsonResponse([
                    'success' => false,
                    'message' => '您不属于允许登录的企业',
                ], 403);
            }

            // 查找或创建用户
            $user = $this->userService->findOrCreateUser($userInfo);

            // 记录成功日志
            DingtalkLoginLog::logSuccess(
                $user->id,
                $userInfo['unionid'] ?? $userInfo['unionId'],
                DingtalkLoginLog::LOGIN_TYPE_H5,
                $ip,
                $userAgent,
                $corpId
            );

            // 触发登录成功事件
            $this->events->dispatch(new DingtalkLoginSucceeded($user, $userInfo));

            // 创建访问令牌并登录用户
            $token = SessionAccessToken::generate($user->id);
            $token->save();
            $this->authenticator->logIn($session, $token);

            return new JsonResponse([
                'success' => true,
                'message' => '登录成功',
                'data' => [
                    'userId' => $user->id,
                    'username' => $user->username,
                    'displayName' => $user->display_name,
                    'avatarUrl' => $user->avatar_url,
                ],
            ]);

        } catch (\Exception $e) {
            DingtalkLoginLog::logFailed(
                null,
                DingtalkLoginLog::LOGIN_TYPE_H5,
                $ip,
                $userAgent,
                $e->getMessage()
            );
            return new JsonResponse([
                'success' => false,
                'message' => '登录失败：' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 获取客户端IP
     */
    protected function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];
        
        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ips = explode(',', $serverParams[$header]);
                return trim($ips[0]);
            }
        }

        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

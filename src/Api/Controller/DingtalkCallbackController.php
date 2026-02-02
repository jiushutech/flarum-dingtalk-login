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

use Flarum\Http\Rememberer;
use Flarum\Http\SessionAccessToken;
use Flarum\Http\SessionAuthenticator;
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use JiushuTech\DingtalkLogin\Event\DingtalkLoginSucceeded;
use JiushuTech\DingtalkLogin\Model\DingtalkLoginLog;
use JiushuTech\DingtalkLogin\Service\DingtalkApiClient;
use JiushuTech\DingtalkLogin\Service\DingtalkUserService;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkCallbackController implements RequestHandlerInterface
{
    protected DingtalkApiClient $apiClient;
    protected DingtalkUserService $userService;
    protected SessionAuthenticator $authenticator;
    protected Rememberer $rememberer;
    protected UrlGenerator $url;
    protected SettingsRepositoryInterface $settings;
    protected Dispatcher $events;

    public function __construct(
        DingtalkApiClient $apiClient,
        DingtalkUserService $userService,
        SessionAuthenticator $authenticator,
        Rememberer $rememberer,
        UrlGenerator $url,
        SettingsRepositoryInterface $settings,
        Dispatcher $events
    ) {
        $this->apiClient = $apiClient;
        $this->userService = $userService;
        $this->authenticator = $authenticator;
        $this->rememberer = $rememberer;
        $this->url = $url;
        $this->settings = $settings;
        $this->events = $events;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $session = $request->getAttribute('session');
        
        $code = $queryParams['authCode'] ?? $queryParams['code'] ?? null;
        $state = $queryParams['state'] ?? null;
        $error = $queryParams['error'] ?? null;

        // 获取客户端信息用于日志
        $ip = $this->getClientIp($request);
        $userAgent = $request->getHeaderLine('User-Agent');

        // 处理错误
        if ($error) {
            DingtalkLoginLog::logFailed(
                null,
                DingtalkLoginLog::LOGIN_TYPE_SCAN,
                $ip,
                $userAgent,
                'Authorization cancelled: ' . $error
            );
            return $this->errorResponse('授权已取消');
        }

        // 验证code
        if (empty($code)) {
            DingtalkLoginLog::logFailed(
                null,
                DingtalkLoginLog::LOGIN_TYPE_SCAN,
                $ip,
                $userAgent,
                'Missing authorization code'
            );
            return $this->errorResponse('授权码缺失，请重试');
        }

        // 验证state防止CSRF
        $savedState = $session->get('dingtalk_oauth_state');
        if (empty($state) || $state !== $savedState) {
            DingtalkLoginLog::logFailed(
                null,
                DingtalkLoginLog::LOGIN_TYPE_SCAN,
                $ip,
                $userAgent,
                'Invalid state parameter (CSRF protection)'
            );
            return $this->errorResponse('安全验证失败，请重试');
        }

        // 清除state
        $session->remove('dingtalk_oauth_state');

        try {
            // 获取用户Token
            $tokenData = $this->apiClient->getUserAccessTokenByCode($code);
            $userAccessToken = $tokenData['accessToken'];

            // 获取用户信息
            $userInfo = $this->apiClient->getUserInfo($userAccessToken);
            
            // 检查企业限制
            $corpId = $userInfo['corpId'] ?? null;
            if ($corpId && !$this->apiClient->validateEnterpriseRestriction($corpId)) {
                DingtalkLoginLog::logFailed(
                    $userInfo['unionId'] ?? null,
                    DingtalkLoginLog::LOGIN_TYPE_SCAN,
                    $ip,
                    $userAgent,
                    'Enterprise restriction: User not in allowed enterprise',
                    $corpId
                );
                return $this->errorResponse('您不属于允许登录的企业');
            }

            // 检查是否为绑定模式
            $isBind = $session->get('dingtalk_oauth_bind', false);
            $session->remove('dingtalk_oauth_bind');
            
            if ($isBind) {
                // 绑定模式：需要已登录用户
                $actor = \Flarum\Http\RequestUtil::getActor($request);
                
                if ($actor->isGuest()) {
                    return $this->errorResponse('绑定失败：请先登录');
                }
                
                // 绑定钉钉账号到当前用户
                $this->userService->bindDingtalkAccount($actor, $userInfo);
                
                // 记录成功日志
                DingtalkLoginLog::logSuccess(
                    $actor->id,
                    $userInfo['unionId'] ?? $userInfo['unionid'],
                    DingtalkLoginLog::LOGIN_TYPE_SCAN,
                    $ip,
                    $userAgent,
                    $corpId
                );
                
                // 绑定成功，关闭弹窗并刷新父页面
                return $this->successResponse('', '绑定成功，窗口即将关闭...', true);
            }

            // 登录模式：查找或创建用户
            $user = $this->userService->findOrCreateUser($userInfo);

            // 记录成功日志
            DingtalkLoginLog::logSuccess(
                $user->id,
                $userInfo['unionId'],
                DingtalkLoginLog::LOGIN_TYPE_SCAN,
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

            // 清除跳转URL
            $session->remove('dingtalk_oauth_referer');

            // 返回成功页面（关闭弹窗并刷新父页面）
            return $this->successResponse('', '登录成功，窗口即将关闭...', true);

        } catch (\Exception $e) {
            DingtalkLoginLog::logFailed(
                null,
                DingtalkLoginLog::LOGIN_TYPE_SCAN,
                $ip,
                $userAgent,
                $e->getMessage()
            );
            return $this->errorResponse('登录失败：' . $e->getMessage());
        }
    }

    /**
     * 获取客户端IP
     */
    protected function getClientIp(ServerRequestInterface $request): string
    {
        $serverParams = $request->getServerParams();
        
        // 检查代理头
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP'];
        
        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ips = explode(',', $serverParams[$header]);
                return trim($ips[0]);
            }
        }

        return $serverParams['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * 返回错误页面
     */
    protected function errorResponse(string $message): ResponseInterface
    {
        $html = $this->generateCallbackHtml(false, $message);
        return new HtmlResponse($html);
    }

    /**
     * 返回成功页面
     */
    protected function successResponse(string $redirectUrl, string $message = '登录成功，正在跳转...', bool $isPopup = false): ResponseInterface
    {
        $html = $this->generateCallbackHtml(true, $message, $redirectUrl, $isPopup);
        return new HtmlResponse($html);
    }

    /**
     * 生成回调页面HTML
     */
    protected function generateCallbackHtml(bool $success, string $message, ?string $redirectUrl = null, bool $isPopup = false): string
    {
        $statusClass = $success ? 'success' : 'error';
        $icon = $success ? '✓' : '✕';
        
        // 如果是弹窗模式（绑定），关闭窗口并刷新父页面
        if ($isPopup && $success) {
            $script = <<<JS
                setTimeout(function() {
                    if (window.opener) {
                        window.opener.location.reload();
                    }
                    window.close();
                }, 1000);
JS;
        } else if ($success && $redirectUrl) {
            $script = "setTimeout(function() { window.location.href = '{$redirectUrl}'; }, 1500);";
        } else {
            $script = "setTimeout(function() { window.close(); }, 3000);";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>钉钉登录</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 48px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
        }
        .icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            font-size: 40px;
            color: white;
        }
        .icon.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .icon.error {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }
        .message {
            font-size: 18px;
            color: #333;
            margin-bottom: 16px;
        }
        .hint {
            font-size: 14px;
            color: #666;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #ddd;
            border-top-color: #007FFF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
            vertical-align: middle;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon {$statusClass}">{$icon}</div>
        <div class="message">{$message}</div>
        <div class="hint">
            {$this->getHintText($success, $isPopup)}
            {$this->getLoadingSpinner($success)}
        </div>
    </div>
    <script>
        {$script}
    </script>
</body>
</html>
HTML;
    }

    protected function getHintText(bool $success, bool $isPopup = false): string
    {
        if ($isPopup && $success) {
            return '窗口即将关闭...';
        }
        return $success ? '请稍候...' : '窗口将自动关闭';
    }

    protected function getLoadingSpinner(bool $success): string
    {
        return $success ? '<span class="loading"></span>' : '';
    }
}

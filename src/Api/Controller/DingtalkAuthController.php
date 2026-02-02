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
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Str;
use JiushuTech\DingtalkLogin\Service\DingtalkApiClient;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkAuthController implements RequestHandlerInterface
{
    protected DingtalkApiClient $apiClient;
    protected UrlGenerator $url;
    protected SettingsRepositoryInterface $settings;

    public function __construct(
        DingtalkApiClient $apiClient,
        UrlGenerator $url,
        SettingsRepositoryInterface $settings
    ) {
        $this->apiClient = $apiClient;
        $this->url = $url;
        $this->settings = $settings;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // 检查配置是否完整
        if (!$this->apiClient->isConfigured()) {
            throw new \RuntimeException('DingTalk login is not configured properly.');
        }

        // 生成state参数防止CSRF攻击
        $state = Str::random(32);
        
        // 获取回调URL
        $callbackUrl = $this->settings->get('jiushutech-dingtalk-login.callback_url');
        
        if (empty($callbackUrl) || $callbackUrl === 'auto') {
            $callbackUrl = $this->url->to('forum')->route('auth.dingtalk.callback');
        }

        // 存储state到session
        $session = $request->getAttribute('session');
        $session->put('dingtalk_oauth_state', $state);
        
        // 检查是否为绑定模式
        $queryParams = $request->getQueryParams();
        $isBind = isset($queryParams['bind']) && $queryParams['bind'] === '1';
        $session->put('dingtalk_oauth_bind', $isBind);
        
        // 存储来源页面，用于登录后跳转
        $referer = $request->getHeaderLine('Referer');
        if ($referer) {
            $session->put('dingtalk_oauth_referer', $referer);
        }

        // 生成授权URL
        $authUrl = $this->apiClient->generateQRCodeLoginUrl($callbackUrl, $state);

        return new RedirectResponse($authUrl);
    }
}

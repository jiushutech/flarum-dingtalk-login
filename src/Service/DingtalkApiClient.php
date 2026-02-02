<?php

/*
 * This file is part of jiushutech/flarum-dingtalk-login.
 *
 * Copyright (c) JiushuTech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JiushuTech\DingtalkLogin\Service;

use Flarum\Settings\SettingsRepositoryInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;
use Psr\Log\LoggerInterface;

class DingtalkApiClient
{
    protected SettingsRepositoryInterface $settings;
    protected Client $httpClient;
    protected LoggerInterface $logger;

    protected ?string $accessToken = null;
    protected ?int $tokenExpireTime = null;

    const API_BASE_URL = 'https://api.dingtalk.com';
    const OAPI_BASE_URL = 'https://oapi.dingtalk.com';

    public function __construct(
        SettingsRepositoryInterface $settings,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->logger = $logger;
        $this->httpClient = new Client([
            'timeout' => 30,
            'verify' => true,
        ]);
    }

    /**
     * 获取AppKey
     */
    public function getAppKey(): string
    {
        return $this->settings->get('jiushutech-dingtalk-login.app_key', '');
    }

    /**
     * 获取AppSecret
     */
    public function getAppSecret(): string
    {
        return $this->settings->get('jiushutech-dingtalk-login.app_secret', '');
    }

    /**
     * 获取AgentId
     */
    public function getAgentId(): string
    {
        return $this->settings->get('jiushutech-dingtalk-login.agent_id', '');
    }

    /**
     * 获取CorpId
     */
    public function getCorpId(): string
    {
        return $this->settings->get('jiushutech-dingtalk-login.corp_id', '');
    }

    /**
     * 获取Access Token（新版API）
     */
    public function getAccessToken(): string
    {
        // 检查缓存的token是否有效
        if ($this->accessToken && $this->tokenExpireTime && time() < $this->tokenExpireTime) {
            return $this->accessToken;
        }

        try {
            $response = $this->httpClient->post(self::API_BASE_URL . '/v1.0/oauth2/accessToken', [
                'json' => [
                    'appKey' => $this->getAppKey(),
                    'appSecret' => $this->getAppSecret(),
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['accessToken'])) {
                $this->accessToken = $data['accessToken'];
                // Token有效期7200秒，提前5分钟刷新
                $this->tokenExpireTime = time() + ($data['expireIn'] ?? 7200) - 300;
                return $this->accessToken;
            }

            throw new \RuntimeException('Failed to get access token: ' . json_encode($data));
        } catch (GuzzleException $e) {
            $this->logger->error('DingTalk API error: ' . $e->getMessage());
            throw new \RuntimeException('Failed to get access token: ' . $e->getMessage());
        }
    }

    /**
     * 获取旧版API的Access Token
     */
    public function getOldAccessToken(): string
    {
        try {
            $response = $this->httpClient->get(self::OAPI_BASE_URL . '/gettoken', [
                'query' => [
                    'appkey' => $this->getAppKey(),
                    'appsecret' => $this->getAppSecret(),
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (Arr::get($data, 'errcode') === 0) {
                return $data['access_token'];
            }

            throw new \RuntimeException('Failed to get old access token: ' . Arr::get($data, 'errmsg', 'Unknown error'));
        } catch (GuzzleException $e) {
            $this->logger->error('DingTalk OAPI error: ' . $e->getMessage());
            throw new \RuntimeException('Failed to get old access token: ' . $e->getMessage());
        }
    }

    /**
     * 通过授权码获取用户Token（扫码登录）
     */
    public function getUserAccessTokenByCode(string $code): array
    {
        try {
            $response = $this->httpClient->post(self::API_BASE_URL . '/v1.0/oauth2/userAccessToken', [
                'json' => [
                    'clientId' => $this->getAppKey(),
                    'clientSecret' => $this->getAppSecret(),
                    'code' => $code,
                    'grantType' => 'authorization_code',
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['accessToken'])) {
                return $data;
            }

            throw new \RuntimeException('Failed to get user access token: ' . json_encode($data));
        } catch (GuzzleException $e) {
            $this->logger->error('DingTalk user token error: ' . $e->getMessage());
            throw new \RuntimeException('Failed to get user access token: ' . $e->getMessage());
        }
    }

    /**
     * 获取用户信息（通过用户Token）
     * 优先使用新版API，失败时尝试使用旧版API
     */
    public function getUserInfo(string $userAccessToken): array
    {
        // 首先尝试新版API
        try {
            $response = $this->httpClient->get(self::API_BASE_URL . '/v1.0/contact/users/me', [
                'headers' => [
                    'x-acs-dingtalk-access-token' => $userAccessToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['unionId'])) {
                return $data;
            }
        } catch (GuzzleException $e) {
            $this->logger->warning('DingTalk new API failed, trying old API: ' . $e->getMessage());
        }

        // 新版API失败，尝试使用 /v1.0/contact/users/{unionId} 接口
        // 或者使用 SNS API 获取用户信息
        try {
            // 使用 SNS 接口获取用户信息（适用于扫码登录场景）
            $response = $this->httpClient->get(self::OAPI_BASE_URL . '/sns/getuserinfo_bycode', [
                'query' => [
                    'accessKey' => $this->getAppKey(),
                    'timestamp' => (string)(time() * 1000),
                    'signature' => $this->generateSnsSignature(),
                ],
                'json' => [
                    'tmp_auth_code' => $userAccessToken,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (Arr::get($data, 'errcode') === 0 && isset($data['user_info'])) {
                $userInfo = $data['user_info'];
                // 转换为统一格式
                return [
                    'unionId' => $userInfo['unionid'] ?? '',
                    'openId' => $userInfo['openid'] ?? '',
                    'nick' => $userInfo['nick'] ?? '',
                    'avatarUrl' => $userInfo['avatarUrl'] ?? '',
                ];
            }
        } catch (GuzzleException $e) {
            $this->logger->warning('DingTalk SNS API also failed: ' . $e->getMessage());
        }

        throw new \RuntimeException('Failed to get user info: Unable to retrieve user information from DingTalk API. Please check your app permissions in DingTalk Open Platform.');
    }

    /**
     * 生成SNS接口签名
     */
    protected function generateSnsSignature(): string
    {
        $timestamp = (string)(time() * 1000);
        $signature = base64_encode(hash_hmac('sha256', $timestamp, $this->getAppSecret(), true));
        return urlencode($signature);
    }

    /**
     * H5免登获取用户信息（企业内部应用）
     */
    public function getUserInfoByH5Code(string $code): array
    {
        try {
            $accessToken = $this->getOldAccessToken();

            $response = $this->httpClient->post(self::OAPI_BASE_URL . '/topapi/v2/user/getuserinfo', [
                'query' => [
                    'access_token' => $accessToken,
                ],
                'json' => [
                    'code' => $code,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (Arr::get($data, 'errcode') === 0) {
                $result = $data['result'];
                
                // 获取用户详细信息
                $userDetail = $this->getUserDetailByUserId($result['userid']);
                
                return array_merge($result, $userDetail);
            }

            throw new \RuntimeException('Failed to get H5 user info: ' . Arr::get($data, 'errmsg', 'Unknown error'));
        } catch (GuzzleException $e) {
            $this->logger->error('DingTalk H5 user info error: ' . $e->getMessage());
            throw new \RuntimeException('Failed to get H5 user info: ' . $e->getMessage());
        }
    }

    /**
     * 根据用户ID获取用户详细信息
     */
    public function getUserDetailByUserId(string $userId): array
    {
        try {
            $accessToken = $this->getOldAccessToken();

            $response = $this->httpClient->post(self::OAPI_BASE_URL . '/topapi/v2/user/get', [
                'query' => [
                    'access_token' => $accessToken,
                ],
                'json' => [
                    'userid' => $userId,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (Arr::get($data, 'errcode') === 0) {
                return $data['result'];
            }

            throw new \RuntimeException('Failed to get user detail: ' . Arr::get($data, 'errmsg', 'Unknown error'));
        } catch (GuzzleException $e) {
            $this->logger->error('DingTalk user detail error: ' . $e->getMessage());
            throw new \RuntimeException('Failed to get user detail: ' . $e->getMessage());
        }
    }

    /**
     * 生成PC扫码登录URL
     */
    public function generateQRCodeLoginUrl(string $redirectUri, string $state): string
    {
        $params = [
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'client_id' => $this->getAppKey(),
            'scope' => 'openid corpid',
            'state' => $state,
            'prompt' => 'consent',
        ];

        return 'https://login.dingtalk.com/oauth2/auth?' . http_build_query($params);
    }

    /**
     * 生成内嵌二维码登录参数
     */
    public function getQRCodeLoginParams(string $redirectUri, string $state): array
    {
        return [
            'redirect_uri' => urlencode($redirectUri),
            'response_type' => 'code',
            'client_id' => $this->getAppKey(),
            'scope' => 'openid corpid',
            'state' => $state,
            'prompt' => 'consent',
        ];
    }

    /**
     * 验证企业限制
     */
    public function validateEnterpriseRestriction(string $corpId): bool
    {
        $enterpriseOnly = (bool) $this->settings->get('jiushutech-dingtalk-login.enterprise_only', false);
        
        if (!$enterpriseOnly) {
            return true;
        }

        $allowedCorpIds = $this->settings->get('jiushutech-dingtalk-login.allowed_corp_ids', '');
        
        if (empty($allowedCorpIds)) {
            return true;
        }

        $allowedList = array_map('trim', explode(',', $allowedCorpIds));
        
        return in_array($corpId, $allowedList);
    }

    /**
     * 检查配置是否完整
     */
    public function isConfigured(): bool
    {
        return !empty($this->getAppKey()) && !empty($this->getAppSecret());
    }
}

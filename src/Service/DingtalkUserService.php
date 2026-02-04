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
use Flarum\User\User;
use Flarum\User\UserRepository;
use Flarum\User\Event\Registered;
use Flarum\Locale\Translator;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Str;
use JiushuTech\DingtalkLogin\Model\DingtalkUser;
use JiushuTech\DingtalkLogin\Event\DingtalkUserLinked;
use JiushuTech\DingtalkLogin\Event\DingtalkUserUnlinked;

class DingtalkUserService
{
    protected SettingsRepositoryInterface $settings;
    protected UserRepository $users;
    protected Dispatcher $events;
    protected DingtalkApiClient $apiClient;
    protected Translator $translator;

    public function __construct(
        SettingsRepositoryInterface $settings,
        UserRepository $users,
        Dispatcher $events,
        DingtalkApiClient $apiClient,
        Translator $translator
    ) {
        $this->settings = $settings;
        $this->users = $users;
        $this->events = $events;
        $this->apiClient = $apiClient;
        $this->translator = $translator;
    }

    /**
     * 通过钉钉用户信息查找或创建Flarum用户
     */
    public function findOrCreateUser(array $dingtalkUserInfo): User
    {
        $unionId = $dingtalkUserInfo['unionId'] ?? $dingtalkUserInfo['unionid'] ?? null;
        
        if (!$unionId) {
            throw new \RuntimeException($this->translator->trans('jiushutech-dingtalk-login.api.missing_union_id'));
        }

        // 先查找是否已绑定
        $dingtalkUser = DingtalkUser::findByDingtalkUserId($unionId);
        
        if ($dingtalkUser) {
            // 更新用户信息
            $this->updateDingtalkUserInfo($dingtalkUser, $dingtalkUserInfo);
            return $dingtalkUser->user;
        }

        // 检查是否允许自动注册
        $autoRegisterSetting = $this->settings->get('jiushutech-dingtalk-login.auto_register');
        // 处理各种可能的值：true, '1', 1, 'true' 都视为启用
        $autoRegister = $autoRegisterSetting === true || 
                        $autoRegisterSetting === '1' || 
                        $autoRegisterSetting === 1 ||
                        $autoRegisterSetting === 'true' ||
                        ($autoRegisterSetting === null); // 默认启用
        
        if (!$autoRegister) {
            throw new \RuntimeException($this->translator->trans('jiushutech-dingtalk-login.api.auto_register_disabled'));
        }

        // 创建新用户
        $user = $this->createNewUser($dingtalkUserInfo);
        
        // 绑定钉钉账号
        $this->bindDingtalkAccount($user, $dingtalkUserInfo);

        return $user;
    }

    /**
     * 创建新的Flarum用户
     */
    protected function createNewUser(array $dingtalkUserInfo): User
    {
        $nickname = $dingtalkUserInfo['nick'] ?? $dingtalkUserInfo['name'] ?? 'DingTalk User';
        $username = $this->generateUsername($nickname);
        $email = $this->generateEmail($dingtalkUserInfo);
        $avatar = $dingtalkUserInfo['avatarUrl'] ?? $dingtalkUserInfo['avatar'] ?? null;

        $user = User::register($username, $email, '');
        
        // 设置头像
        if ($avatar && $this->settings->get('jiushutech-dingtalk-login.sync_avatar', true)) {
            $user->avatar_url = $avatar;
        }

        // 标记邮箱已验证（因为是通过钉钉登录的）
        $user->is_email_confirmed = true;
        
        $user->save();

        $this->events->dispatch(new Registered($user, null, []));

        return $user;
    }

    /**
     * 生成用户名
     */
    protected function generateUsername(string $nickname): string
    {
        $rule = $this->settings->get('jiushutech-dingtalk-login.username_rule', 'nickname');
        
        if ($rule === 'random') {
            return 'dingtalk_' . Str::random(8);
        }

        // 使用昵称作为用户名
        $username = preg_replace('/[^a-zA-Z0-9_\x{4e00}-\x{9fa5}]/u', '', $nickname);
        
        if (empty($username)) {
            $username = 'dingtalk_' . Str::random(6);
        }

        // 确保用户名唯一
        $originalUsername = $username;
        $counter = 1;
        
        while (User::where('username', $username)->exists()) {
            $username = $originalUsername . '_' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * 生成邮箱
     */
    protected function generateEmail(array $dingtalkUserInfo): string
    {
        // 优先使用钉钉邮箱
        if (!empty($dingtalkUserInfo['email']) && $this->settings->get('jiushutech-dingtalk-login.sync_email', false)) {
            $email = $dingtalkUserInfo['email'];
            
            if (!User::where('email', $email)->exists()) {
                return $email;
            }
        }

        // 生成随机邮箱
        $unionId = $dingtalkUserInfo['unionId'] ?? $dingtalkUserInfo['unionid'] ?? Str::random(16);
        return 'dingtalk_' . substr(md5($unionId), 0, 12) . '@dingtalk.local';
    }

    /**
     * 绑定钉钉账号到现有用户
     */
    public function bindDingtalkAccount(User $user, array $dingtalkUserInfo): DingtalkUser
    {
        $unionId = $dingtalkUserInfo['unionId'] ?? $dingtalkUserInfo['unionid'];
        $openId = $dingtalkUserInfo['openId'] ?? $dingtalkUserInfo['openid'] ?? null;
        $corpId = $dingtalkUserInfo['corpId'] ?? $dingtalkUserInfo['associated_unionid'] ?? null;

        // 检查是否已绑定其他账号
        if (DingtalkUser::isUserBound($user->id)) {
            throw new \RuntimeException($this->translator->trans('jiushutech-dingtalk-login.api.already_bound'));
        }

        if (DingtalkUser::isDingtalkBound($unionId)) {
            throw new \RuntimeException($this->translator->trans('jiushutech-dingtalk-login.api.dingtalk_already_bound'));
        }

        $dingtalkUser = new DingtalkUser([
            'user_id' => $user->id,
            'dingtalk_user_id' => $unionId,
            'dingtalk_openid' => $openId,
            'dingtalk_nickname' => $dingtalkUserInfo['nick'] ?? $dingtalkUserInfo['name'] ?? null,
            'dingtalk_avatar' => $dingtalkUserInfo['avatarUrl'] ?? $dingtalkUserInfo['avatar'] ?? null,
            'dingtalk_mobile' => $this->encryptSensitiveData($dingtalkUserInfo['mobile'] ?? null),
            'dingtalk_email' => $this->encryptSensitiveData($dingtalkUserInfo['email'] ?? null),
            'corp_id' => $corpId,
        ]);

        $dingtalkUser->save();

        // 同步用户信息到Flarum用户
        $this->syncUserInfo($user, $dingtalkUserInfo);

        $this->events->dispatch(new DingtalkUserLinked($user, $dingtalkUser));

        return $dingtalkUser;
    }

    /**
     * 解绑钉钉账号
     */
    public function unbindDingtalkAccount(User $user): void
    {
        $dingtalkUser = DingtalkUser::findByUserId($user->id);
        
        if (!$dingtalkUser) {
            throw new \RuntimeException($this->translator->trans('jiushutech-dingtalk-login.api.not_bound'));
        }

        $this->events->dispatch(new DingtalkUserUnlinked($user, $dingtalkUser));

        $dingtalkUser->delete();
    }

    /**
     * 更新钉钉用户信息
     */
    protected function updateDingtalkUserInfo(DingtalkUser $dingtalkUser, array $dingtalkUserInfo): void
    {
        $dingtalkUser->dingtalk_nickname = $dingtalkUserInfo['nick'] ?? $dingtalkUserInfo['name'] ?? $dingtalkUser->dingtalk_nickname;
        $dingtalkUser->dingtalk_avatar = $dingtalkUserInfo['avatarUrl'] ?? $dingtalkUserInfo['avatar'] ?? $dingtalkUser->dingtalk_avatar;
        
        if (!empty($dingtalkUserInfo['mobile'])) {
            $dingtalkUser->dingtalk_mobile = $this->encryptSensitiveData($dingtalkUserInfo['mobile']);
        }
        
        if (!empty($dingtalkUserInfo['email'])) {
            $dingtalkUser->dingtalk_email = $this->encryptSensitiveData($dingtalkUserInfo['email']);
        }

        $dingtalkUser->save();

        // 同步用户信息到Flarum用户
        $this->syncUserInfo($dingtalkUser->user, $dingtalkUserInfo);
    }

    /**
     * 同步用户信息到Flarum用户
     */
    protected function syncUserInfo(User $user, array $dingtalkUserInfo): void
    {
        $updated = false;

        if ($this->settings->get('jiushutech-dingtalk-login.sync_avatar', true)) {
            $avatar = $dingtalkUserInfo['avatarUrl'] ?? $dingtalkUserInfo['avatar'] ?? null;
            if ($avatar && $user->avatar_url !== $avatar) {
                $user->avatar_url = $avatar;
                $updated = true;
            }
        }

        if ($updated) {
            $user->save();
        }
    }

    /**
     * 加密敏感数据
     */
    protected function encryptSensitiveData(?string $data): ?string
    {
        if (empty($data)) {
            return null;
        }

        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * 解密敏感数据
     */
    public function decryptSensitiveData(?string $encryptedData): ?string
    {
        if (empty($encryptedData)) {
            return null;
        }

        $key = $this->getEncryptionKey();
        $data = base64_decode($encryptedData);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * 获取加密密钥
     */
    protected function getEncryptionKey(): string
    {
        // 使用Flarum的应用密钥
        $appKey = $this->settings->get('mail_encryption_key') ?? 
                  $this->settings->get('jiushutech-dingtalk-login.app_secret') ?? 
                  'default_encryption_key';
        
        return hash('sha256', $appKey, true);
    }

    /**
     * 检查用户是否已绑定钉钉
     */
    public function isUserBound(User $user): bool
    {
        return DingtalkUser::isUserBound($user->id);
    }

    /**
     * 获取用户的钉钉绑定信息
     */
    public function getDingtalkUser(User $user): ?DingtalkUser
    {
        return DingtalkUser::findByUserId($user->id);
    }

    /**
     * 检查用户是否为豁免用户
     */
    public function isExemptUser(User $user): bool
    {
        // 管理员默认豁免
        if ($user->isAdmin()) {
            return true;
        }

        $exemptUsers = $this->settings->get('jiushutech-dingtalk-login.exempt_users', '');
        
        if (empty($exemptUsers)) {
            return false;
        }

        $exemptList = array_map('trim', explode(',', $exemptUsers));
        
        return in_array($user->username, $exemptList);
    }
}

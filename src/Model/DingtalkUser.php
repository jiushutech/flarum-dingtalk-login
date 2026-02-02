<?php

/*
 * This file is part of jiushutech/flarum-dingtalk-login.
 *
 * Copyright (c) JiushuTech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JiushuTech\DingtalkLogin\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $dingtalk_user_id
 * @property string|null $dingtalk_openid
 * @property string|null $dingtalk_nickname
 * @property string|null $dingtalk_avatar
 * @property string|null $dingtalk_mobile
 * @property string|null $dingtalk_email
 * @property string|null $corp_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property User $user
 */
class DingtalkUser extends AbstractModel
{
    protected $table = 'dingtalk_users';

    protected $dates = ['created_at', 'updated_at'];

    protected $fillable = [
        'user_id',
        'dingtalk_user_id',
        'dingtalk_openid',
        'dingtalk_nickname',
        'dingtalk_avatar',
        'dingtalk_mobile',
        'dingtalk_email',
        'corp_id',
    ];

    /**
     * 关联的Flarum用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 根据钉钉用户ID查找
     */
    public static function findByDingtalkUserId(string $dingtalkUserId): ?self
    {
        return static::where('dingtalk_user_id', $dingtalkUserId)->first();
    }

    /**
     * 根据Flarum用户ID查找
     */
    public static function findByUserId(int $userId): ?self
    {
        return static::where('user_id', $userId)->first();
    }

    /**
     * 检查用户是否已绑定钉钉
     */
    public static function isUserBound(int $userId): bool
    {
        return static::where('user_id', $userId)->exists();
    }

    /**
     * 检查钉钉账号是否已被绑定
     */
    public static function isDingtalkBound(string $dingtalkUserId): bool
    {
        return static::where('dingtalk_user_id', $dingtalkUserId)->exists();
    }
}

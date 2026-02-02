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
 * @property int|null $user_id
 * @property string|null $dingtalk_user_id
 * @property string|null $login_ip
 * @property string|null $user_agent
 * @property string $login_type
 * @property string $status
 * @property string|null $error_message
 * @property string|null $corp_id
 * @property \Carbon\Carbon $created_at
 * @property User|null $user
 */
class DingtalkLoginLog extends AbstractModel
{
    protected $table = 'dingtalk_login_logs';

    public $timestamps = false;

    protected $dates = ['created_at'];

    protected $fillable = [
        'user_id',
        'dingtalk_user_id',
        'login_ip',
        'user_agent',
        'login_type',
        'status',
        'error_message',
        'corp_id',
    ];

    const LOGIN_TYPE_SCAN = 'scan';
    const LOGIN_TYPE_H5 = 'h5';
    const LOGIN_TYPE_REDIRECT = 'redirect';

    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    /**
     * 关联的Flarum用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 记录成功登录日志
     */
    public static function logSuccess(
        ?int $userId,
        string $dingtalkUserId,
        string $loginType,
        string $ip,
        string $userAgent,
        ?string $corpId = null
    ): self {
        return static::create([
            'user_id' => $userId,
            'dingtalk_user_id' => $dingtalkUserId,
            'login_type' => $loginType,
            'login_ip' => $ip,
            'user_agent' => substr($userAgent, 0, 500),
            'status' => self::STATUS_SUCCESS,
            'corp_id' => $corpId,
        ]);
    }

    /**
     * 记录失败登录日志
     */
    public static function logFailed(
        ?string $dingtalkUserId,
        string $loginType,
        string $ip,
        string $userAgent,
        string $errorMessage,
        ?string $corpId = null
    ): self {
        return static::create([
            'user_id' => null,
            'dingtalk_user_id' => $dingtalkUserId,
            'login_type' => $loginType,
            'login_ip' => $ip,
            'user_agent' => substr($userAgent, 0, 500),
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'corp_id' => $corpId,
        ]);
    }

    /**
     * 清理过期日志
     */
    public static function cleanupOldLogs(int $retentionDays): int
    {
        return static::where('created_at', '<', now()->subDays($retentionDays))->delete();
    }
}

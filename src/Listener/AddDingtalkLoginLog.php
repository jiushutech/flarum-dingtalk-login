<?php

/*
 * This file is part of jiushutech/flarum-dingtalk-login.
 *
 * Copyright (c) JiushuTech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JiushuTech\DingtalkLogin\Listener;

use JiushuTech\DingtalkLogin\Event\DingtalkLoginSucceeded;
use Psr\Log\LoggerInterface;

class AddDingtalkLoginLog
{
    protected LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function handle(DingtalkLoginSucceeded $event): void
    {
        $this->logger->info('DingTalk login succeeded', [
            'user_id' => $event->user->id,
            'username' => $event->user->username,
            'dingtalk_user_id' => $event->dingtalkUserInfo['unionId'] ?? $event->dingtalkUserInfo['unionid'] ?? null,
        ]);
    }
}

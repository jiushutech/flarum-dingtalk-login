<?php

/*
 * This file is part of jiushutech/flarum-dingtalk-login.
 *
 * Copyright (c) JiushuTech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JiushuTech\DingtalkLogin\Event;

use Flarum\User\User;

class DingtalkLoginSucceeded
{
    public User $user;
    public array $dingtalkUserInfo;

    public function __construct(User $user, array $dingtalkUserInfo)
    {
        $this->user = $user;
        $this->dingtalkUserInfo = $dingtalkUserInfo;
    }
}

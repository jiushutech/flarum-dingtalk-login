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
use JiushuTech\DingtalkLogin\Model\DingtalkUser;

class DingtalkUserLinked
{
    public User $user;
    public DingtalkUser $dingtalkUser;

    public function __construct(User $user, DingtalkUser $dingtalkUser)
    {
        $this->user = $user;
        $this->dingtalkUser = $dingtalkUser;
    }
}

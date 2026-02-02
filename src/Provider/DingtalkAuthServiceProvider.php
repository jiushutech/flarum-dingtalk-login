<?php

/*
 * This file is part of jiushutech/flarum-dingtalk-login.
 *
 * Copyright (c) JiushuTech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JiushuTech\DingtalkLogin\Provider;

use Flarum\Foundation\AbstractServiceProvider;
use JiushuTech\DingtalkLogin\Service\DingtalkApiClient;
use JiushuTech\DingtalkLogin\Service\DingtalkUserService;

class DingtalkAuthServiceProvider extends AbstractServiceProvider
{
    public function register(): void
    {
        $this->container->singleton(DingtalkApiClient::class);
        $this->container->singleton(DingtalkUserService::class);
    }
}

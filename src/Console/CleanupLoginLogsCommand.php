<?php

/*
 * This file is part of jiushutech/flarum-dingtalk-login.
 *
 * Copyright (c) JiushuTech
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JiushuTech\DingtalkLogin\Console;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Console\Command;
use JiushuTech\DingtalkLogin\Model\DingtalkLoginLog;

class CleanupLoginLogsCommand extends Command
{
    protected $signature = 'dingtalk:cleanup-logs';
    protected $description = 'Clean up old DingTalk login logs';

    protected SettingsRepositoryInterface $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        parent::__construct();
        $this->settings = $settings;
    }

    public function handle(): int
    {
        $retentionDays = (int) $this->settings->get('jiushutech-dingtalk-login.log_retention_days', 30);
        
        $this->info("Cleaning up login logs older than {$retentionDays} days...");
        
        $deleted = DingtalkLoginLog::cleanupOldLogs($retentionDays);
        
        $this->info("Deleted {$deleted} old log entries.");
        
        return 0;
    }
}

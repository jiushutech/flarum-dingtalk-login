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
use Flarum\Settings\SettingsRepositoryInterface;
use JiushuTech\DingtalkLogin\Model\DingtalkLoginLog;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkLogsExportController implements RequestHandlerInterface
{
    protected SettingsRepositoryInterface $settings;

    public function __construct(SettingsRepositoryInterface $settings)
    {
        $this->settings = $settings;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        
        // 必须是管理员
        if (!$actor->isAdmin()) {
            return new JsonResponse([
                'success' => false,
                'message' => '权限不足',
            ], 403);
        }

        // 检查是否允许导出
        if (!$this->settings->get('jiushutech-dingtalk-login.allow_log_export', true)) {
            return new JsonResponse([
                'success' => false,
                'message' => '日志导出功能已禁用',
            ], 403);
        }

        $body = $request->getParsedBody();
        $startDate = $body['startDate'] ?? null;
        $endDate = $body['endDate'] ?? null;
        $status = $body['status'] ?? null;

        $query = DingtalkLoginLog::query()
            ->with('user')
            ->orderBy('created_at', 'desc');

        // 状态筛选
        if ($status && in_array($status, ['success', 'failed'])) {
            $query->where('status', $status);
        }

        // 日期筛选
        if ($startDate) {
            $query->where('created_at', '>=', $startDate . ' 00:00:00');
        }
        if ($endDate) {
            $query->where('created_at', '<=', $endDate . ' 23:59:59');
        }

        $logs = $query->get();

        // 生成CSV内容
        $csv = $this->generateCsv($logs);

        $response = new Response();
        $response = $response
            ->withHeader('Content-Type', 'text/csv; charset=utf-8')
            ->withHeader('Content-Disposition', 'attachment; filename="dingtalk_login_logs_' . date('Y-m-d_His') . '.csv"');
        
        $response->getBody()->write($csv);

        return $response;
    }

    protected function generateCsv($logs): string
    {
        $output = chr(0xEF) . chr(0xBB) . chr(0xBF); // UTF-8 BOM
        
        // 表头
        $headers = [
            'ID',
            '用户ID',
            '用户名',
            '钉钉用户ID',
            '登录IP',
            '登录类型',
            '状态',
            '错误信息',
            '企业ID',
            '登录时间',
        ];
        $output .= implode(',', $headers) . "\n";

        // 数据行
        foreach ($logs as $log) {
            $row = [
                $log->id,
                $log->user_id ?? '',
                $log->user ? $log->user->username : '',
                $log->dingtalk_user_id ?? '',
                $log->login_ip ?? '',
                $this->getLoginTypeLabel($log->login_type),
                $log->status === 'success' ? '成功' : '失败',
                $this->escapeCsvField($log->error_message ?? ''),
                $log->corp_id ?? '',
                $log->created_at->format('Y-m-d H:i:s'),
            ];
            $output .= implode(',', $row) . "\n";
        }

        return $output;
    }

    protected function getLoginTypeLabel(string $type): string
    {
        $labels = [
            'scan' => '扫码登录',
            'h5' => 'H5免登',
            'redirect' => '跳转登录',
        ];
        return $labels[$type] ?? $type;
    }

    protected function escapeCsvField(?string $field): string
    {
        if (empty($field)) {
            return '';
        }
        // 转义双引号并用双引号包裹
        return '"' . str_replace('"', '""', $field) . '"';
    }
}

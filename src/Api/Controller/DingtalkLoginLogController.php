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
use JiushuTech\DingtalkLogin\Model\DingtalkLoginLog;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkLoginLogController implements RequestHandlerInterface
{
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

        $queryParams = $request->getQueryParams();
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($queryParams['perPage'] ?? 20)));
        $status = $queryParams['status'] ?? null;
        $startDate = $queryParams['startDate'] ?? null;
        $endDate = $queryParams['endDate'] ?? null;
        $search = $queryParams['search'] ?? null;

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

        // 搜索
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('login_ip', 'like', "%{$search}%")
                  ->orWhere('dingtalk_user_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('username', 'like', "%{$search}%");
                  });
            });
        }

        $total = $query->count();
        $logs = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $data = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'userId' => $log->user_id,
                'username' => $log->user ? $log->user->username : null,
                'dingtalkUserId' => $log->dingtalk_user_id,
                'loginIp' => $log->login_ip,
                'userAgent' => $log->user_agent,
                'loginType' => $log->login_type,
                'status' => $log->status,
                'errorMessage' => $log->error_message,
                'corpId' => $log->corp_id,
                'createdAt' => $log->created_at->toIso8601String(),
            ];
        });

        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage),
            ],
        ]);
    }
}

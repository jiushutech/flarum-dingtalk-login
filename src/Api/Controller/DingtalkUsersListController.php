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
use JiushuTech\DingtalkLogin\Model\DingtalkUser;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DingtalkUsersListController implements RequestHandlerInterface
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
        $search = $queryParams['search'] ?? null;

        $query = DingtalkUser::query()
            ->with('user')
            ->orderBy('created_at', 'desc');

        // 搜索
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('dingtalk_nickname', 'like', "%{$search}%")
                  ->orWhere('dingtalk_user_id', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('username', 'like', "%{$search}%");
                  });
            });
        }

        $total = $query->count();
        $users = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        $data = $users->map(function ($dingtalkUser) {
            return [
                'id' => $dingtalkUser->id,
                'userId' => $dingtalkUser->user_id,
                'username' => $dingtalkUser->user ? $dingtalkUser->user->username : null,
                'userAvatar' => $dingtalkUser->user ? $dingtalkUser->user->avatar_url : null,
                'dingtalkUserId' => $dingtalkUser->dingtalk_user_id,
                'dingtalkNickname' => $dingtalkUser->dingtalk_nickname,
                'dingtalkAvatar' => $dingtalkUser->dingtalk_avatar,
                'corpId' => $dingtalkUser->corp_id,
                'createdAt' => $dingtalkUser->created_at->toIso8601String(),
                'updatedAt' => $dingtalkUser->updated_at->toIso8601String(),
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

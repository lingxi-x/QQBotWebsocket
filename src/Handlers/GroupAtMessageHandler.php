<?php
namespace App\Handlers;

use App\QQBot\Http;
use App\QQBot\Logger;

class GroupAtMessageHandler implements HandlerInterface
{
    public function supports(?string $eventType): bool
    {
        return $eventType === 'GROUP_AT_MESSAGE_CREATE';
    }

    public function handle(string $eventType, $data, Http $http, Logger $logger): void
    {
        $groupOpenId = $data['group_openid'] ?? '';
        $content = trim($data['content'] ?? '');
        $msgId = $data['id'] ?? '';
        if (!$groupOpenId || !$msgId) return;

        $payload = [
            'msg_type' => 0,
            'content'  => "群里有人@我：{$content}",
            'msg_id'   => $msgId,
            'msg_seq'  => 1,
        ];
        $logger->info('回复群消息', ['group' => $groupOpenId, 'content' => $payload['content']]);
        $http->replyGroup($groupOpenId, $payload);
    }
}
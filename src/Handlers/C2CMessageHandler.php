<?php
namespace App\Handlers;

use App\QQBot\Http;
use App\QQBot\Logger;

class C2CMessageHandler implements HandlerInterface
{
    public function supports(?string $eventType): bool
    {
        return $eventType === 'C2C_MESSAGE_CREATE';
    }

    public function handle(string $eventType, $data, Http $http, Logger $logger): void
    {
        $openid = $data['author']['user_openid'] ?? '';
        $content = trim($data['content'] ?? '');
        $msgId = $data['id'] ?? '';

        if (!$openid || !$msgId) return;

        // 被动回复：文本
        $payload = [
            'msg_type' => 0,
            'content'  => "你说：{$content}",
            'msg_id'   => $msgId,
            'msg_seq'  => 1,
        ];
        $logger->info('Reply C2C', ['to' => $openid, 'content' => $payload['content']]);
        $http->replyC2C($openid, $payload);
    }
}
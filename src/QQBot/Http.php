<?php
namespace App\QQBot;

class Http
{
    private array $config;
    private Logger $logger;
    private ?string $accessToken = null;
    private int $tokenExpireAt = 0;

    public function __construct(array $config, Logger $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    /** 获取 AccessToken */
    public function getAccessToken(): string
    {
        if ($this->accessToken && time() < $this->tokenExpireAt - 60) {
            return $this->accessToken;
        }
        $url = 'https://bots.qq.com/app/getAppAccessToken';
        $payload = json_encode([
            'appId' => $this->config['app_id'],
            'clientSecret' => $this->config['client_secret'],
        ]);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        if ($resp === false) {
            throw new \RuntimeException('get token failed: '.curl_error($ch));
        }
        $data = json_decode($resp, true) ?: [];
        if (empty($data['access_token'])) {
            throw new \RuntimeException('invalid token response: '.$resp);
        }
        $this->accessToken = $data['access_token'];
        $ttl = (int)($data['expires_in'] ?? 7200);
        $this->tokenExpireAt = time() + $ttl;
        $this->logger->info('Fetched access token', ['ttl' => $ttl]);
        return $this->accessToken;
    }

    private function api(string $method, string $path, array $body = null): array
    {
        $url = rtrim($this->config['api_base'], '/') . $path;
        $ch = curl_init($url);
        $headers = [
            'Authorization: QQBot ' . $this->getAccessToken(),
            'Content-Type: application/json',
        ];
        $opts = [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_UNICODE);
        }
        curl_setopt_array($ch, $opts);
        $resp = curl_exec($ch);

        if ($resp === false) {
            $this->logger->error('HTTP 请求失败: ' . curl_error($ch));
            return [];
        }
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($code >= 400) {
            $this->logger->error("HTTP {$code} 错误: {$resp}");
            return [];
        }
        return json_decode($resp, true) ?: [];
    }

    // 被动回复：单聊
    public function replyC2C(string $openid, array $payload): array
    {
        return $this->api('POST', "/v2/users/{$openid}/messages", $payload);
    }

    // 被动回复：群聊
    public function replyGroup(string $groupOpenId, array $payload): array
    {
        return $this->api('POST', "/v2/groups/{$groupOpenId}/messages", $payload);
    }
}
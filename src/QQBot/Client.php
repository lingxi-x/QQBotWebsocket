<?php
namespace App\QQBot;

use Ratchet\Client\WebSocket;
use Ratchet\Client\Connector;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use App\Handlers\HandlerInterface;

class Client
{
    private array $config;
    private Logger $logger;
    private Http $http;
    private LoopInterface $loop;
    private ?WebSocket $conn = null;

    private ?string $sessionId = null;
    private ?int $lastSeq = null;
    private int $heartbeatIntervalMs = 45000;
    private $heartbeatTimer = null;

    private const AUTH_FAIL_CODE = 4004;
    private const INVALID_RECONNECT_CODES = [9001, 9005];
    private const RESUME_FAIL_CODE = 4902;
    
    private bool $canReconnect = true;
    private bool $isResuming = false;
    private $reconnectTimer = null;

    /** @var HandlerInterface[] */
    private array $handlers = [];

    public function __construct(array $config, Logger $logger, Http $http, LoopInterface $loop)
    {
        $this->config = $config;
        $this->logger = $logger;
        $this->http = $http;
        $this->loop = $loop;
        $this->canReconnect = true;
        $this->isResuming = false;
    }

    public function addHandler(HandlerInterface $h): void
    {
        $this->handlers[] = $h;
    }

    private function connect(): void
    {
        if ($this->reconnectTimer) {
            $this->loop->cancelTimer($this->reconnectTimer);
            $this->reconnectTimer = null;
        }
        
        $this->logger->info('[BotPHP] 启动中...');
        
        $reactConnector = new \React\Socket\Connector($this->loop, [
            'tls' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ]
        ]);

        $connector = new Connector($this->loop, $reactConnector);

        $headers = [
            'User-Agent' => 'QQBot-PHP74/1.0 (ReactPHP)'
        ];

        $connector($this->config['wss_url'], [], $headers)
            ->then(function (WebSocket $conn) {
                $this->conn = $conn;
                $this->logger->info('[BotPHP] WebSocket连接已建立');

                $conn->on('message', function ($msg) use ($conn) {
                    $payload = json_decode($msg, true) ?: [];
                    $this->handleMessage($payload);
                });

                $conn->on('close', function ($code = null, $reason = null) {
                    $this->logger->info("[BotPHP] WebSocket连接关闭，代码: {$code}，原因: {$reason}");
                    
                    // 处理关闭代码
                    if ($code === self::AUTH_FAIL_CODE) {
                        $this->logger->info('[BotPHP] 鉴权失败，重置token...');
                        $this->http->resetAccessToken();
                    }
                    
                    // 处理会话恢复失败
                    if ($code === self::RESUME_FAIL_CODE) {
                        $this->logger->info('[BotPHP] 会话恢复失败，重置会话信息');
                        $this->sessionId = null;
                        $this->lastSeq = null;
                        $this->isResuming = false;
                    }
                    
                    if (in_array($code, self::INVALID_RECONNECT_CODES) || !$this->canReconnect) {
                        $this->logger->info('[BotPHP] 无法重连，创建新连接!');
                        $this->sessionId = "";
                        $this->lastSeq = 0;
                    }
                    
                    $this->reconnect();
                });

                $conn->on('error', function (\Exception $e) {
                    $this->logger->error('[BotPHP] WebSocket错误: ' . $e->getMessage());
                    $this->reconnect();
                });
            })
            ->otherwise(function (\Exception $e) {
                $this->logger->error('[BotPHP] 连接失败: ' . $e->getMessage());
                $this->reconnect();
            });
    }

    private function handleMessage(array $payload): void
    {
        $op = $payload['op'] ?? null;
        $t  = $payload['t'] ?? null;
        $d  = $payload['d'] ?? null;
        $s  = $payload['s'] ?? null;
        
        if ($s !== null) {
            $this->lastSeq = (int)$s;
        }

        switch ($op) {
            case 10:
                $this->heartbeatIntervalMs = (int)($d['heartbeat_interval'] ?? 45000);
                $this->logger->info('[BotPHP] 收到Hello消息', ['心跳间隔ms' => $this->heartbeatIntervalMs]);
                
                if ($this->sessionId && $this->lastSeq !== null && $this->isResuming) {
                    $this->sendJson([
                        'op' => 6,
                        'd'  => [
                            'token' => 'QQBot ' . $this->http->getAccessToken(),
                            'session_id' => $this->sessionId,
                            'seq' => $this->lastSeq,
                        ],
                    ]);
                    $this->logger->info('[BotPHP] 重连启动...');
                } else {
                    $this->isResuming = false;
                    $this->sendJson([
                        'op' => 2,
                        'd'  => [
                            'token' => 'QQBot ' . $this->http->getAccessToken(),
                            'intents' => $this->config['intents'],
                            'shard' => $this->config['shard'],
                            'properties' => [
                                '$os' => PHP_OS,
                                '$browser' => 'php74-ws',
                                '$device' => 'php74-ws'
                            ]
                        ],
                    ]);
                    $this->logger->info('[BotPHP] 鉴权中...');
                }
                
                $this->setupHeartbeat();
                break;
                
            case 0:
                if ($t === 'READY') {
                    $this->sessionId = $d['session_id'] ?? null;
                    $this->isResuming = false;
                    $this->logger->info("[BotPHP] 机器人 {$d['user']['username']} 登录成功", ['session_id' => $this->sessionId]);
                    $this->logger->info('[BotPHP] 心跳维持启动...');
                    $this->sendJson(['op' => 1, 'd' => null]);
                    $this->logger->info('[BotPHP] 发送初次心跳包', ['seq' => null]);
                } elseif ($t === 'RESUMED') {
                    $this->isResuming = false;
                    $this->logger->info('[BotPHP] 机器人重连成功!');
                    $this->logger->info('[BotPHP] 心跳维持启动...');
                    $this->sendJson(['op' => 1, 'd' => null]);
                    $this->logger->info('[BotPHP] 发送重连后初次心跳包', ['seq' => null]);
                } else {
                    $this->dispatch($t, $d);
                }
                break;
                
            case 7:
                $this->logger->info('[BotPHP] 服务器请求重连');
                $this->canReconnect = true;
                $this->isResuming = true;
                $this->reconnect();
                break;
                
            case 9:
                $this->logger->error('[BotPHP] 无效会话，需要重新连接');
                $this->canReconnect = false;
                $this->sessionId = null;
                $this->isResuming = false;
                $this->reconnect();
                break;
                
            case 11:
                $this->logger->info('[BotPHP] 心跳ACK');
                break;
                
            default:
                $this->logger->info('[BotPHP] 收到未处理的opcode', ['op' => $op]);
        }
    }

    private function setupHeartbeat(): void
    {
        if ($this->heartbeatTimer) {
            $this->loop->cancelTimer($this->heartbeatTimer);
        }
        
        $this->heartbeatTimer = $this->loop->addPeriodicTimer(
            $this->heartbeatIntervalMs / 1000,
            function () {
                $this->sendJson(['op' => 1, 'd' => $this->lastSeq]);
                $this->logger->info('[BotPHP] 发送心跳包', ['seq' => $this->lastSeq]);
            }
        );
    }

    private function sendJson(array $payload): void
    {
        if ($this->conn) {
            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            $this->conn->send($json);
            $this->logger->debug('[BotPHP] 发送消息: ' . $json);
        }
    }

    private function reconnect(): void
    {

        if ($this->heartbeatTimer) {
            $this->loop->cancelTimer($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }
        
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
        
        if ($this->reconnectTimer) {
            $this->loop->cancelTimer($this->reconnectTimer);
        }
        
        $delay = Util::jitter(1500) / 1000;
        $this->logger->info("[BotPHP] 将在 {$delay} 秒后重连");
        
        $this->reconnectTimer = $this->loop->addTimer($delay, function () {
            $this->connect();
        });
    }

    public function run(): void
    {
        $this->connect();
        $this->loop->run();
    }

    public function stop(): void
    {
        if ($this->heartbeatTimer) {
            $this->loop->cancelTimer($this->heartbeatTimer);
            $this->heartbeatTimer = null;
        }
        
        if ($this->reconnectTimer) {
            $this->loop->cancelTimer($this->reconnectTimer);
            $this->reconnectTimer = null;
        }
        
        if ($this->conn) {
            $this->conn->close();
            $this->conn = null;
        }
    }

    private function dispatch(?string $t, $d): void
    {
        foreach ($this->handlers as $h) {
            if ($h->supports($t)) {
                try {
                    $h->handle($t, $d, $this->http, $this->logger);
                } catch (\Throwable $e) {
                    $this->logger->error('[BotPHP] 事件处理异常: '.$e->getMessage());
                }
            }
        }
    }
}
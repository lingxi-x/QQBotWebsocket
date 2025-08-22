<?php
namespace App\QQBot;

class Logger
{
    private string $level;

    public function __construct(string $level = 'info')
    {
        $this->level = $level;
    }

    private function allow(string $lvl): bool
    {
        $order = ['debug' => 0, 'info' => 1, 'error' => 2];
        return $order[$lvl] >= $order[$this->level];
    }

    public function debug(string $msg, array $ctx = []): void
    {
        if ($this->allow('debug')) $this->log('DEBUG', $msg, $ctx);
    }

    public function info(string $msg, array $ctx = []): void
    {
        if ($this->allow('info')) $this->log('INFO', $msg, $ctx);
    }

    public function error(string $msg, array $ctx = []): void
    {
        if ($this->allow('error')) $this->log('ERROR', $msg, $ctx);
    }

    private function log(string $level, string $msg, array $ctx): void
    {
        $time = date('c');
        $json = $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE) : '';
        fwrite(STDOUT, "[$time][$level] $msg $json\n");
    }
}
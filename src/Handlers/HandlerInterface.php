<?php
namespace App\Handlers;

use App\QQBot\Http;
use App\QQBot\Logger;

interface HandlerInterface
{
    public function supports(?string $eventType): bool;

    /** @param mixed $data */
    public function handle(string $eventType, $data, Http $http, Logger $logger): void;
}
<?php

namespace App\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class TransactionReporter
{
    private Logger $logger;
    private CrispService $crispService;

    public function __construct(CrispService $crispService, Logger $logger)
    {
        $this->crispService = $crispService;
        $this->logger = $logger;
    }
    

    private function sendMessage(string|array $message, string $sessionId, ?string $websiteId = null): void
    {
        $this->crispService->sendMessage($message, $sessionId, $websiteId);
    }

    
}

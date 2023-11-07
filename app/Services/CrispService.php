<?php 
namespace App\Services;

use Crisp\CrispClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CrispService
{
    private CrispClient $crispClient;
    private string $websiteId;
    private Logger $logger;

    public function __construct()
    {
        $this->crispClient = new CrispClient();
        $this->websiteId = config('crisp.website_id');
        $this->crispClient->setTier('plugin');
        $this->crispClient->authenticate(config('crisp.api_identifier'), config('crisp.api_key'));

        $this->logger = new Logger('crisp-service');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    }

    public function logMessage(string $message): void
    {
        $this->logger->info($message);
    }

}

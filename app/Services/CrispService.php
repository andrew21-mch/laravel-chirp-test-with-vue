<?php

namespace App\Services;
use BotMan\BotMan\BotMan;
use BotMan\BotMan\BotManFactory;
use BotMan\BotMan\Drivers\DriverManager;
use BotMan\BotMan\Drivers\HttpDriver;
use Crisp\CrispClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class CrispService
{
    protected $crispClient;
    protected $botman;

    public function __construct(CrispClient $crispClient)
    {
        $this->crispClient = $crispClient;
        $this->initializeBotMan();
    }

    protected function initializeBotMan()
    {
        DriverManager::loadDriver(HttpDriver::class);

        $config = [
            'web' => [
                'matchingData' => [
                    'driver' => 'web',
                ],
            ],
        ];

        $this->botman = BotManFactory::create($config);
    }

    public function handleWebhookEvents(array $crispWebhookData): void
    {
        // Your existing Crisp webhook handling logic...

        // Process the event
        $content = $crispWebhookData["data"]["content"];

        // Use BotMan to handle the message
        $this->handleBotManMessage($content);
    }

    protected function handleBotManMessage(string $message): void
    {
        $this->botman->hears('hello|hey|hi|yo|good*|bonjour|bjr', function (BotMan $bot) {
            $bot->reply('Hello! How can I assist you today?');
        });

        // Define more BotMan conversation logic here...

        $this->botman->listen();
    }

    // Your other CrispService methods...
}
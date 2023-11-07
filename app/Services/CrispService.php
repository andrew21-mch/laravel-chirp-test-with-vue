<?php

namespace App\Services;

use Crisp\CrispClient;

class CrispService
{
    private CrispClient $crispClient;
    private string $websiteId;

    public function __construct()
    {
        $this->crispClient = new CrispClient();
        $this->websiteId = config('crisp.website_id');
        $this->crispClient->setTier('plugin');
        $this->crispClient->authenticate(config('crisp.api_identifier'), config('crisp.api_key'));
    }

    public function createConversation(string $message, string $email, ?string $nickname = null): void
    {
        $conversation = $this->crispClient->websiteConversations->create($this->websiteId);
        $sessionId = $conversation["session_id"];
        $this->crispClient->websiteConversations->updateMeta($this->websiteId, $sessionId, [
            "email" => $email,
            "nickname" => $nickname,
        ]);
        $message = ["type" => "text", "from" => "user", "origin" => "email", "content" => $message];
        $this->crispClient->websiteConversations->sendMessage($this->websiteId, $sessionId, $message);
    }

    public function sendMessage(string|array $message, string $sessionId, ?string $websiteId = null): void
    {
        if (!is_array($message)) {
            $message = [
                "content" => $message,
                "type" => "text",
                "from" => "operator",
                "origin" => "chat",
            ];
        }
        $websiteId = $websiteId ?? $this->websiteId;
        $this->crispClient->websiteConversations->sendMessage($websiteId, $sessionId, $message);
    }

    public function handleWebhookEvents(array $crispWebhookData): void
    {
        $input = $crispWebhookData;

        if ($input["event"] == "message:send") {
            $websiteId = $input["data"]["website_id"];
            $sessionId = $input["data"]["session_id"];
            if (!in_array($input['data']['type'], ['text', 'file'])) {
                return;
            }
            $content = $input["data"]["content"];

            if ($input["data"]["type"] === 'file') {
                // Handle file messages if needed.
            }

            if ($input['data']['type'] === 'text') {
                // Handle text messages using the chatbot logic
                $feedbackMessage = $this->handleChatbotQueries($content);

                if ($feedbackMessage) {
                    $message = [
                        "content" => $feedbackMessage,
                        "type" => "text",
                        "from" => "operator",
                        "origin" => "chat",
                    ];
                    $this->crispClient->websiteConversations->sendMessage($websiteId, $sessionId, $message);
                }
            }

            // Handle other actions based on your business requirements.
        }
    }

    protected function handleChatbotQueries($message)
    {
        // Sample chatbot responses for common queries
        $lowerMessage = strtolower($message);

        if (strpos($lowerMessage, 'balance') !== false) {
            return 'Your current airtime balance is $50.';
        }

        if (strpos($lowerMessage, 'transfer') !== false) {
            return 'To transfer airtime, dial *123#, select "Transfer Airtime," and follow the prompts.';
        }

        if (strpos($lowerMessage, 'help') !== false) {
            return 'I can assist you with various tasks. Please select a category: 
                    1. Account Management
                    2. Airtime Transfer
                    3. Technical Support
                    4. FAQs
                    5. Contact Support';
        }

        if (strpos($lowerMessage, 'complaint') !== false) {
            return 'I\'m sorry to hear about your issue. Please provide more details about your complaint, and I will forward it to our support team for assistance.';
        }

        return;
    }

    // Add more methods and logic for specific actions as needed
}

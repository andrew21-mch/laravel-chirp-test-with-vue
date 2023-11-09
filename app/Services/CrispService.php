<?php

namespace App\Services;

use Crisp\CrispClient;
use Crisp\CrispException;
use Illuminate\Http\Client\Response;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;


class CrispService
{
    // public readonly CrispClient $crispClient;
    private CrispClient $crispClient;
    private string $websiteId;
    private Logger $logger;

    public function __construct()
    {
        $this->crispClient = new CrispClient();
        $this->websiteId = config('crisp.website_id');
        $this->crispClient->setTier('plugin');
        $this->crispClient->authenticate(config('crisp.api_identifier'), config('crisp.api_key'));

        // Initialize the logger property here
        $this->logger = new Logger('crisp-service');
        $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));
    }

    /**
     * @throws CrispException
     * @throws ClientExceptionInterface
     */
    public function createConversation(string $message, string $email, ?string $nickname = null): void
    {
        $conversation = $this->crispClient->websiteConversations->create($this->websiteId);
        $sessionId = $conversation["session_id"];
        $this->crispClient->websiteConversations->updateMeta(
            $this->websiteId,
            $sessionId,
            ["email" => $email, "nickname" => $nickname]
        );
        $message = ["type" => "text", "from" => "user", "origin" => "email", "content" => $message];
        $this->crispClient->websiteConversations->sendMessage($this->websiteId, $sessionId, $message);
    }

    public function sendMessage(string|array $message, string $sessionId, ?string $websiteId = null): void
    {
        if (!is_array($message)) {
            $message = $message = [
                "content" => $message,
                "type" => "text",
                "from" => "operator",
                "origin" => "chat"
            ];
            ;
        }
        $websiteId = $websiteId ?? $this->websiteId;
        $this->crispClient->websiteConversations->sendMessage($websiteId, $sessionId, $message);
        $this->handleWebhookEvents($message);
    }

    public function handleWebhookEvents(array $crispWebhookData): void
    {
        // $this->crispClient->setTier('plugin');
        $input = $crispWebhookData;
        // $input looks like this: {"website_id": "42286ab3-b29a-4fde-8538-da0ae501d825","event": "message:send","data": {"type": "text","origin": "chat","content": "Hello Crisp, this is a message from a visitor!","timestamp": 1632396148646,"fingerprint": 163239614854320,"website_id": "42286ab3-b29a-4fde-8538-da0ae501d825","session_id": "session_36ba3566-9651-4790-afc8-ffedbccc317f","from": "user","user": {"nickname": "visitor607","user_id": "session_36ba3566-9651-4790-afc8-ffedbccc317f"},"stamped": true},"timestamp": 1632396148743}
        if ($input["event"] == "message:send") {
            $websiteId = $input["data"]["website_id"];
            $sessionId = $input["data"]["session_id"];
            if (!in_array($input['data']['type'], ['text', 'file'])) {
                return;
            }
            $content = $input["data"]["content"]; // for text messages

            $this->handleUserInteraction($input, $sessionId, $websiteId, $content);
        }
    }

    public function handleUserInteraction(array $crispWebhookData, string $sessionId, string $websiteId, string $message): void
    {
        // Define the available options and their descriptions.
        $options = [
            '1' => 'Report a bug',
            '2' => 'Report airtime not received',
            '3' => 'Check balance',
            '4' => 'Purchase airtime',
            '5' => 'View transactions',
            '6' => 'Talk to an Agent',
        ];

        // Check if the user's last message is in the options list.
        if (array_key_exists($message, $options)) {
            $selectedOption = $message;

            // Handle the selected option.
            switch ($selectedOption) {
                case '1':
                    // Handle "Report a bug"
                    $this->handleBugReport($sessionId, $websiteId);
                    break;
                case '2':
                    // Handle "Report airtime not received"
                    $this->reportAirtimeNotReceived($sessionId, $websiteId);
                    break;
                case '3':
                    // Handle "Check balance"
                    $this->checkBalance($sessionId, $websiteId);
                    break;
                case '4':
                    // Handle "Purchase airtime"
                    $this->purchaseAirtime($sessionId, $websiteId);
                    break;
                case '5':
                    // Handle "View transactions"
                    $this->viewTransactions($sessionId, $websiteId);
                    break;
                case '6':
                    // Handle "Talk to an Agent" (You can implement a mechanism to route to live agent support)
                    $this->talkToAgent($sessionId, $websiteId);
                    break;
            }
        } else {
            // The user's input is not in the options list, so ask them to select a valid option.
            $menuMessage = "Hello, nice to have you, please select from our menu, how may we help you! :) \n";
            foreach ($options as $key => $description) {
                $menuMessage .= "$key. $description\n";
            }
            $this->sendMessage($menuMessage, $sessionId, $websiteId);
        }
    }



    private function handleBugReport(string $sessionId, string $websiteId): void
    {
        // Implement bug reporting logic here
        $this->sendMessage("You have selected 'Report a bug'. Please provide details about the bug.", $sessionId, $websiteId);
    }

    private function reportAirtimeNotReceived(string $sessionId, string $websiteId): void
    {
        // Implement airtime not received reporting logic here
        $this->sendMessage("You have selected 'Report airtime not received'. Please provide details.", $sessionId, $websiteId);
    }

    private function checkBalance($sessionId, $websiteId): void
    {
        // Implement logic to check the user's balance
        // You can call the relevant services or APIs here
        $this->sendMessage("Your current balance is 100 FCFA.", $sessionId, $websiteId);
    }

    private function purchaseAirtime($sessionId, $websiteId): void
    {
        // Implement logic to allow the user to purchase airtime
        // You can guide the user through the purchase process
        $this->sendMessage("To purchase airtime, please follow the steps...", $sessionId, $websiteId);
    }

    private function viewTransactions($sessionId, $websiteId): void
    {
        // Implement logic to allow the user to view their transaction history
        // Retrieve and display the user's transaction history
        $this->sendMessage("Here is your transaction history...", $sessionId, $websiteId);
    }

    public function talkToAgent(string $sessionId, string $websiteId)
    {

        // Send the message to Crisp to tag/mention the agent
        $this->sendMessage('Give us few mins to get you connected', $sessionId, $websiteId);

        $this->checkOperatorAvailability($sessionId, $websiteId);
    }

    public function logMessage(): void
    {
        // Get a list of conversations from Crisp
        $conversations = $this->crispClient->websiteConversations->getList($this->websiteId, 1);

        // Check if there are conversations
        if (count($conversations) > 0) {
            foreach ($conversations as $conversation) {
                // Log details of each conversation
                $this->logger->info("Last Message: {$conversation['last_message']}");
                // Add more conversation details as needed
                $this->logger->info('---'); // Separator for readability
            }
        } else {
            // No conversations found
            $this->logger->info('No conversations found in Crisp.');
        }


    }

    private function checkOperatorAvailability(string $sessionId, string $websiteId): void
    {
        // Fetch the list of team members using the Crisp API
        $teamMembers = $this->crispClient->websiteOperators->getList($websiteId);

        $this->logger->info("I am here, lets check it out!");
        // Check each team member's availability
        foreach ($teamMembers as $teamMember) {
            $operatorId = $teamMember['id'];


            $this->notifyOperator($operatorId, $sessionId, $websiteId);

        }
    }

    private function notifyOperator(string $operatorId, string $sessionId, string $websiteId): void
    {
        // Implement the logic to notify a specific operator
        // You can send a message to the operator using their ID
        $operatorMessage = "A user has requested to talk to you. Please assist them.";

        // Use the Crisp API to send a message to the specific operator
        $this->crispClient->websiteConversations->sendMessage($websiteId, $sessionId, [
            "content" => $operatorMessage,
            "type" => "text",
            "from" => "operator",
            "origin" => "chat",
            "to" => $operatorId,
            // Include the operatorId as the recipient
        ]);
    }


}
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
        try {
            $this->crispClient = new CrispClient();
            $this->websiteId = config('crisp.website_id');
            $this->crispClient->setTier('plugin');
            $this->crispClient->authenticate(config('crisp.api_identifier'), config('crisp.api_key'));

            $this->logger = new Logger('crisp-service');
            $this->logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
        } catch (\Exception $e) {
            // Log initialization error
            $this->logger->error("Error initializing CrispService: " . $e->getMessage());
        }
    }


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
        $input = $crispWebhookData;

        // Debugging output
        $this->logger->debug("Webhook data received: " . json_encode($input));

        if (!isset($input["event"])) {
            $this->logger->error("Missing 'event' key in webhook data.");
            // No valid event, return early
            return;
        }

        if ($input["event"] == "message:send") {

            // Continue handling the event
            $websiteId = $input["data"]["website_id"];
            $sessionId = $input["data"]["session_id"];

            if (!in_array($input['data']['type'], ['text', 'file'])) {
                return;
            }

            // Debugging output
            $this->logger->debug("Processing message: " . json_encode($input));

            // Process the event
            $content = $input["data"]["content"];
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

        try {
            // Normalize the user's input (convert to lowercase for case-insensitive comparison)
            $normalizedMessage = strtolower(trim($message));

            // Check if the user's last message is a numeric option.
            if (is_numeric($normalizedMessage) && array_key_exists($normalizedMessage, $options)) {
                $this->handleSelectedOption($normalizedMessage, $sessionId, $websiteId);
            } else {
                // Find the closest matching option using Levenshtein distance.
                $selectedOption = null;
                $minDistance = PHP_INT_MAX;

                foreach ($options as $key => $description) {
                    $distance = levenshtein($normalizedMessage, strtolower($description));

                    if ($distance < $minDistance) {
                        $minDistance = $distance;
                        $selectedOption = $key;
                    }
                }

                // You can set a threshold for the minimum acceptable similarity.
                $threshold = 10; // Adjust as needed

                if ($minDistance <= $threshold) {
                    $this->handleSelectedOption($selectedOption, $sessionId, $websiteId);
                } else {
                    // No exact match or close match, ask the user to select a valid option.
                    $menuMessage = "Hello, nice to have you, please select from our menu, how may we help you! :) \n";
                    foreach ($options as $key => $description) {
                        $menuMessage .= "$key. $description\n";
                    }
                    $this->sendMessage($menuMessage, $sessionId, $websiteId);
                }
            }
        } catch (\Exception $e) {
            // Respond with a user-friendly message including the exception details
            $errorMessage = "Oops! Something went wrong. We're sorry, but we are still under development. Details: " . $e->getMessage();
            $this->sendMessage($errorMessage, $sessionId, $websiteId);
        }
    }

    // Rest of the code...

    private function handleSelectedOption(string $selectedOption, string $sessionId, string $websiteId): void
    {
        // Implement the logic for handling the selected option.
        // You can switch on the selected option and perform the appropriate action.

        switch ($selectedOption) {
            case '1':
                $this->handleBugReport($sessionId, $websiteId);
                break;
            case '2':
                $this->reportAirtimeNotReceived($sessionId, $websiteId);
                break;
            case '3':
                $this->checkBalance($sessionId, $websiteId);
                break;
            case '4':
                $this->purchaseAirtime($sessionId, $websiteId);
                break;
            case '5':
                $this->viewTransactions($sessionId, $websiteId);
                break;
            case '6':
                $this->talkToAgent($sessionId, $websiteId);
                break;
            // Add more cases as needed for other options
            default:
                // Handle the case where the selected option is not recognized.
                $this->sendMessage("Invalid option selected. Please try again.", $sessionId, $websiteId);
                break;
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
    }



    private function checkOperatorAvailability(string $sessionId, string $websiteId): void
    {
        try {
            // Fetch the list of team members using the Crisp API
            $teamMembers = $this->crispClient->websiteOperators->getList($websiteId);

            foreach ($teamMembers as $teamMember) {
                $operatorId = $teamMember['user_id'];
                $operatorName = $teamMember['first_name']; // Add this line to get the operator's name
                $this->notifyOperator($operatorId, $sessionId, $websiteId, $name = $operatorName);
            }
        } catch (\Exception $e) {
            // Log error if an exception occurs during operator availability check
            $this->logger->error("Error checking operator availability: " . $e->getMessage());
        }
    }

    private function notifyOperator(string $operatorId, string $sessionId, string $websiteId, string $name): void
    {
        // Implement the logic to notify a specific operator
        // You can send a message to the operator using their ID
        $operatorMessage = "Hi $name, A user has requested to talk to you. Please assist them.";

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
<?php

namespace App\Services;

use App\Models\Chirp;
use App\Models\User;
use Carbon\Carbon;
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

    private string $userId;

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
        // $this->logger->debug("Webhook data received: " . json_encode($input));

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
            $userId = $input["data"]["user"]["user_id"];
            $this->handleUserInteraction($input, $sessionId, $websiteId, $content, $userId);
        }

    }


    public function handleUserInteraction(array $crispWebhookData, string $sessionId, string $websiteId, string $message, $userId = null): void
    {
        // Define the available options and their descriptions.
        $options = [
            '1' => 'Report a bug',
            '2' => 'Report airtime not received',
            '3' => 'Check balance',
            '4' => 'Purchase airtime',
            '5' => 'View transactions',
            '6' => 'Talk to an Agent',
            '7' => 'Search users',
            '8' => 'Check interesting chirps',
            '9' => 'Count users',
            '10' => 'List users',
        ];

        try {
            // Normalize the user's input (convert to lowercase for case-insensitive comparison)
            $normalizedMessage = strtolower(trim($message));

            // use wit.ai to check message internt and log it to console
            $accessToken = env('WIT_AI_CLIENT_ACCESS_TOKEN');
            $witApiUrl = 'https://api.wit.ai/message';

            $headers = [
                'Authorization: Bearer ' . $accessToken,
            ];
            
            $queryParams = [
                'q' => $message,
            ];
            
            // Initialize cURL session
            $ch = curl_init($witApiUrl . '?' . http_build_query($queryParams));
            
            // Set cURL options
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // Execute cURL session and get the response
            $response = curl_exec($ch);
            
            // Close cURL session
            curl_close($ch);
            
            // Decode the JSON response
            $witData = json_decode($response, true);

            $this->logger->debug($response);

            // Check for common greetings
            $greetings = ['hello', 'hi', 'hey', 'start'];
            $thankYouPhrases = ['thanks', 'thank you'];

            if (in_array($normalizedMessage, $greetings)) {
                $this->sendMessage("Hello! I am your assistant, How can I assist you today?, you can start with sometihng like 'what is on our menu'", $sessionId, $websiteId);
                return;
            } elseif (in_array($normalizedMessage, $thankYouPhrases)) {
                $this->sendMessage("You're welcome! If you have more questions, feel free to ask.", $sessionId, $websiteId);
                return;
            }

            // Check if the user's last message is a numeric option.
            if (is_numeric($normalizedMessage) && array_key_exists($normalizedMessage, $options)) {
                $this->logger->debug($userId);
                $this->handleSelectedOption($normalizedMessage, $sessionId, $websiteId, $userId);

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
                    $this->handleSelectedOption($selectedOption, $sessionId, $websiteId, $userId);
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

    private function handleSelectedOption(string $selectedOption, string $sessionId, string $websiteId, string $userId = null): void
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

                $this->talkToAgent($sessionId, $websiteId, $userId);
                break;
            case '7':
                $this->findUsers($sessionId, $websiteId);
                break;
            case '8':
                $this->getChirps($sessionId, $websiteId);
                break;
            case '9':
                $this->countUsers($sessionId, $websiteId);
                break;
            case '10':
                $this->listUsers($sessionId, $websiteId);
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

    public function talkToAgent(string $sessionId, string $websiteId, string $userId = ull)
    {

        // Send the message to Crisp to tag/mention the agent
        $this->sendMessage('Give us few mins to get you connected' . $userId, $sessionId, $websiteId);


        $params = [
            "assigned" => [
                "user_id" => $userId,
            ]
        ];

        $this->crispClient->websiteConversations->assignRouting($websiteId, $sessionId, $params);
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

    private function findUsers(string $sessionId, string $websiteId): void
    {
        // Ask the user for a name or email
        $this->sendMessage("Please enter a user name or email to search:", $sessionId, $websiteId);

        // Get the user's response
        $userInput = $this->getUserInput($sessionId, $websiteId);

        // Find users by name or email
        $users = User::where('name', 'like', "%$userInput%")
            ->orWhere('email', 'like', "%$userInput%")
            ->get();

        if ($users->isEmpty()) {
            $message = "No users found for the search term: $userInput";
        } else {
            $message = "Users found:\n";
            foreach ($users as $user) {
                $message .= "ID: $user->id, Name: $user->name, Email: $user->email\n";
            }
        }

        $this->sendMessage($message, $sessionId, $websiteId);
    }

    private function getUserInput(string $sessionId, string $websiteId): string
    {
        // Provide instructions to the user
        $instructionMessage = "To search for a user, please type their name or email. Example: 'search John'";

        // Send the instruction message
        $this->sendMessage($instructionMessage, $sessionId, $websiteId);

        // Initialize user input to an empty string
        $userInput = '';

        // Implement logic to wait for the user to send a message
        while (empty($userInput)) {
            // Retrieve the conversation history
            $conversationHistory = $this->crispClient->websiteConversations->getMessages($websiteId, $sessionId);

            // Check if there are new messages
            if (!empty($conversationHistory) && end($conversationHistory)['from'] === 'user') {
                // Extract the content of the last user message
                $userInput = end($conversationHistory)['content'];
            }

            // Add a short delay to avoid excessive API calls
            sleep(2); // You can adjust the sleep duration as needed
        }

        // Return the user's input after trimming
        return trim($userInput);
    }


    private function getChirps(string $sessionId, string $websiteId): void
    {
        // Get chirps for the current day
        $chirps = Chirp::whereDate('created_at', Carbon::today())->get();

        if ($chirps->isEmpty()) {
            $message = "No chirps found for today.";
        } else {
            $message = "Chirps for today:\n";
            foreach ($chirps as $chirp) {
                $message .= "ID: $chirp->id, User ID: $chirp->user_id, Message: $chirp->message\n";
            }
        }

        $this->sendMessage($message, $sessionId, $websiteId);
    }

    private function countUsers(string $sessionId, string $websiteId): void
    {
        // Count all users in the database
        $userCount = User::count();

        $message = "Total number of users: $userCount";

        $this->sendMessage($message, $sessionId, $websiteId);
    }

    private function listUsers(string $sessionId, string $websiteId): void
    {
        // Retrieve all users from the database
        $users = User::all();

        // Check if there are users in the database
        if ($users->isEmpty()) {
            $message = "No users found in the database.";
        } else {
            // Build a message listing all users
            $message = "Users in the database:\n";
            foreach ($users as $user) {
                $message .= "ID: $user->id, Name: $user->name, Email: $user->email\n";
            }
        }

        // Send the message to the user
        $this->sendMessage($message, $sessionId, $websiteId);
    }



}
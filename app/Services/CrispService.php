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
    private const EXIT_COMMAND = 'exit';

    private TransactionReporter $transactionReporter;

    private bool $exitRequested = false;


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

            // Use Wit.ai to check the message intent and log it to console
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


            if (is_numeric($normalizedMessage) && array_key_exists($normalizedMessage, $options)) {
                $this->handleSelectedOption($normalizedMessage, $sessionId, $websiteId, $userId);
            } else if (isset($witData['intents']) && !empty($witData['intents'])) {
                $intentName = $witData['intents'][0]['name'];
                $this->logger->debug($witData['intents'][0]['name']);

                // Now you can use $intentName in your logic
                switch ($intentName) {
                    case 'greetings':
                        $this->sendMessage("Hello! I am your assistant, how may I help you?", $sessionId, $websiteId);
                        break;
                    case 'appreciation':
                        $this->sendMessage("You're welcome! If you have more questions...", $sessionId, $websiteId);
                        break;
                    case 'transaction_message':
                        $this->handleTransactionMessage($sessionId, $websiteId);
                        break;
                    case 'inquiries':
                        $this->handleInquiries($sessionId, $websiteId);
                        break;
                    case 'airtime_not_received':
                        $this->reportAirtimeNotReceived($sessionId, $websiteId);
                        break;

                    case 'bug_report':
                        $this->handleBugReport($sessionId, $websiteId);
                        break;
                    case 'talk_to_agent':
                        $this->talkToAgent($sessionId, $websiteId, $userId);
                        break;
                    case 'menu':
                        $this->sendMenu($sessionId, $websiteId, $options);
                        break;
                    // Add more cases as needed for other intents
                    default:

                        // No recognized intent, ask the user to provide more information
                        $this->sendMessage("I'm sorry, I didn't understand that. How may I assist you?", $sessionId, $websiteId);

                        break;
                }
            } else {
                // Check if the user's last message is a numeric option.

                $this->sendMessage("I'm sorry, I didn't understand that. How may I assist you?", $sessionId, $websiteId);
            }
        } catch (\Exception $e) {
            $this->logger->debug($e->getMessage());
            // Respond with a user-friendly message including the exception details
            $errorMessage = "Oops! Something went wrong. We're sorry, but we are still under development. Details: " . $e->getMessage();
            $this->sendMessage($errorMessage, $sessionId, $websiteId);
        }
    }

    // Add the following method to handle the 'check_menu' intent

    private function sendMenu(string $sessionId, string $websiteId, array $options): void
    {
        // Define your menu items or send a pre-defined menu
        $menuMessage = "Our menu:\n";
        foreach ($options as $key => $description) {
            $menuMessage .= "$key. $description\n";
        }

        $this->sendMessage($menuMessage, $sessionId, $websiteId);
    }

    public function reportAirtimeNotReceived(string $sessionId, string $websiteId): void
    {
        try {
            $userInput = $this->getUserInput($sessionId, $websiteId, "You have selected 'Report airtime not received'. Please send us your transaction message received from switchn, mtn, orange, or camtel.");

            $transactionDetails = $this->scanMessage($userInput);

            if (!empty($transactionDetails)) {
                $this->handleSuccessfulTransaction($transactionDetails, $sessionId, $websiteId);
            } else {
                $this->handleFailedTransaction($sessionId, $websiteId);
            }
        } catch (\Exception $e) {
            // Log or handle the exception as needed
            $this->logger->error("Error during 'reportAirtimeNotReceived' handling: " . $e->getMessage());
        }
    }


    private function handleSuccessfulTransaction(array $transactionDetails, string $sessionId, string $websiteId): void
    {
        // Handle a successful transaction
        $sender = $transactionDetails['external_transaction_id'];
        $amount = $transactionDetails['amount'];
        $date = $transactionDetails['date'];
        $transactionId = $transactionDetails['transaction_id'];

        $response = "Transaction details received:\n"
            . "Extrernal ID: $sender\n"
            . "Transaction ID : $transactionId\n"
            . "Amount: $amount\n"
            . "Date: $date";

        $this->sendMessage($response, $sessionId, $websiteId);
    }


    private function handleFailedTransaction(string $sessionId, string $websiteId): void
    {
        // Handle a failed transaction
        $this->sendMessage("Unable to extract transaction details. Please provide a valid transaction message.", $sessionId, $websiteId);
    }
    private function scanMessage(string $message): array
    {
        $pattern = '/Hello, the transaction with amount (?P<amount>\d+ \w+) for (?P<payment_method>[\w\s]+) \((?P<payment_id>\d+)\) with message: (?P<message>.+?) at (?P<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}), reason: (?P<reason>[\w\s.]+) .Please check your balance or contact system admin for more information. Financial Transaction Id: (?P<transaction_id>\d+). External Transaction Id: (?P<external_transaction_id>\d+)./';

        preg_match($pattern, $message, $matches);

        // Provide default values in case of no match
        $defaultValues = [
            'amount' => 'N/A',
            'payment_method' => 'N/A',
            'payment_id' => 'N/A',
            'message' => 'N/A',
            'date' => 'N/A',
            'reason' => 'N/A',
            'transaction_id' => 'N/A',
            'external_transaction_id' => 'N/A',
        ];

        // If there is a match, use the matched values; otherwise, use default values
        $transactionInfo = $matches ? array_intersect_key($matches, $defaultValues) : $defaultValues;

        return $transactionInfo;
    }



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




    private function setState(bool $state): void
    {
        $this->exitRequested = $state;
    }

    public function handleBugReport(string $sessionId, string $websiteId): void
    {
        $bugDetails = [];
        $this->setState(false);

        while (true) {
            $userInput = $this->getUserInput($sessionId, $websiteId, 'Enter details for bug please!');

            if (strtolower(trim($userInput)) == self::EXIT_COMMAND) {
                $this->sendMessage("Bug details updated. Type 'exit' to submit the bug report or provide additional details:", $sessionId, $websiteId);
                $this->setState(true); // Set state to exit
                break;
            } else {
                $bugDetails[] = $userInput;

                $confirmationMessage = "Bug details collected so far:\n" . implode("\n", $bugDetails) . "\n\nType 'exit' to submit the bug report or provide additional details:";
                $this->sendMessage($confirmationMessage, $sessionId, $websiteId);

                sleep(1);
            }
        }
    }

    private function getUserInput(string $sessionId, string $websiteId, string $instructionMessage = "To search for a user, please type their name or email. Example: 'search John'", int $timeout = 60): string
    {
        $this->sendMessage($instructionMessage, $sessionId, $websiteId);

        $userInput = '';
        $startTime = time();

        do {
            $conversationHistory = $this->crispClient->websiteConversations->getMessages($websiteId, $sessionId);

            if (!empty($conversationHistory) && end($conversationHistory)['from'] === 'user') {
                $userInput = end($conversationHistory)['content'];

                // Check for the exit condition
                if (strtolower(trim($userInput)) === self::EXIT_COMMAND) {
                    $this->setState(true);
                }
            }

            if (!empty($userInput) || (time() - $startTime) > $timeout || $this->exitRequested) {
                break;
            }

            usleep(250000);

        } while (true);

        return trim($userInput);
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
        $this->sendMessage("Here is your transaction history...", $sessionId, $websiteId);
    }

    public function talkToAgent(string $sessionId, string $websiteId, string $userId = null)
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
    private function handleInquiries(string $sessionId, string $websiteId): void
    {
        // Welcome message
        $this->sendMessage("Welcome to the inquiries section. How may I assist you?", $sessionId, $websiteId);

        // You can use a loop to continuously handle inquiries
        while (true) {
            // Get user input
            $userInput = $this->getUserInput($sessionId, $websiteId, "Type your inquiry:", 120);

            // Process the user's inquiry
            $response = $this->processInquiry($userInput);

            // Send the response to the user
            $this->sendMessage($response, $sessionId, $websiteId);

            // You can add more conditions to exit the loop based on user input
            if (strtolower($userInput) === 'exit') {
                $this->sendMessage("Exiting the inquiries section. Have a great day!", $sessionId, $websiteId);
                break;
            }
        }
    }

    private function processInquiry(string $inquiry): string
    {
        // Normalize the inquiry (convert to lowercase for case-insensitive comparison)
        $normalizedInquiry = strtolower($inquiry);

        // Check for specific inquiries related to airtime and data services
        if (strpos($normalizedInquiry, 'airtime') !== false) {
            return "For airtime inquiries, you can check your balance, report issues, or purchase airtime. How can I assist you?";
        } elseif (strpos($normalizedInquiry, 'data') !== false || strpos($normalizedInquiry, 'bundle') !== false) {
            return "If you have questions about data bundles, I can help with information on available plans, usage, and more. What do you need assistance with?";
        }

        // For other inquiries, provide a general response
        return "Thank you for your inquiry. We will get back to you shortly.";
    }


    private function handleTransactionMessage(string $sessionId, string $websiteId): void
    {
        // Replace this with your actual logic to handle transaction messages
        $transactionResponse = "Thank you for your transaction message! We are processing your request.";
        $this->sendMessage($transactionResponse, $sessionId, $websiteId);
    }
}
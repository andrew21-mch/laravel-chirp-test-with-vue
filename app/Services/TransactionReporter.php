<?php

namespace App\Services;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class TransactionReporter extends CrispService
{
    private Logger $logger;
    private CrispService $crispService;


    public function reportAirtimeNotReceived(string $sessionId, string $websiteId): void
    {
        $userInput = $this->crispService->getUserInput($sessionId, $websiteId, "You have selected 'Report airtime not received'. Please send us your transaction message received from switchn, mtn, orange, or camtel.");

        $transactionDetails = $this->scanMessage($userInput);

        if (!empty($transactionDetails)) {
            $this->handleSuccessfulTransaction($transactionDetails, $sessionId, $websiteId);
        } else {
            $this->handleFailedTransaction($sessionId, $websiteId);
        }
    }

    private function handleSuccessfulTransaction(array $transactionDetails, string $sessionId, string $websiteId): void
    {
        // Handle a successful transaction
        $sender = $transactionDetails['external_transaction_id'];
        $amount = $transactionDetails['amount'];
        $date = $transactionDetails['date'];
        $transactionId = $transactionDetails['transaction_id'];

        $response = "Transaction details received:
                    \n## Extrernal ID: $sender
                    \n## Transaction ID : $transactionId
                    \n## Amount: $amount
                    \n## Date: $date";
        $this->crispService->sendMessage($response, $sessionId, $websiteId);
    }

    private function handleFailedTransaction(string $sessionId, string $websiteId): void
    {
        // Handle a failed transaction
        $this->crispService->sendMessage("Unable to extract transaction details. Please provide a valid transaction message.", $sessionId, $websiteId);
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

        // Combine default values with matched values
        $transactionInfo = array_merge($defaultValues, $matches);

        // Remove the full match from the result
        unset($transactionInfo[0]);

        return $transactionInfo;
    }
}

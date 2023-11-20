<?php

namespace App\Services;

class BugReportHandler extends CrispService
{
    private const EXIT_COMMAND = 'exit';

    public function handleBugReport(string $sessionId, string $websiteId): void
    {
        $this->sendMessage("You have selected 'Report a bug'. Please provide details about the bug. You can type 'exit' to exit the bug reporting process.", $sessionId, $websiteId);

        // Initialize bug report details
        $bugDetails = [];

        // Start a loop to collect bug details
        while (true) {
            // Get user input for bug details
            $userInput = $this->getUserInput($sessionId, $websiteId);

            // Check if the user wants to exit the bug reporting process
            if (strtolower($userInput) === self::EXIT_COMMAND) {
                $this->sendMessage("Bug report process has been exited.", $sessionId, $websiteId);
                return;
            }

            // Add the user input to bug details
            $bugDetails[] = $userInput;

            // Ask the user for additional details or confirm submission
            $confirmationMessage = "Bug details collected so far:\n" . implode("\n", $bugDetails) . "\n\nType 'exit' to submit the bug report or provide additional details:";
            $this->sendMessage($confirmationMessage, $sessionId, $websiteId);

            // Wait for user response
            $confirmation = $this->getUserInput($sessionId, $websiteId);

            // Check if the user wants to exit or submit the bug report
            if (strtolower($confirmation) === self::EXIT_COMMAND) {
                $this->sendMessage("Bug report process has been exited.", $sessionId, $websiteId);
                return;
            } elseif (empty(trim($confirmation))) {
                // If the user provides no input, ask for details again
                $this->sendMessage("Please provide additional details or type 'exit' to submit the bug report:", $sessionId, $websiteId);
            } else {
                // Continue collecting bug details
                $this->sendMessage("Bug details updated. Type 'exit' to submit the bug report or provide additional details:", $sessionId, $websiteId);
            }
        }
    }
}

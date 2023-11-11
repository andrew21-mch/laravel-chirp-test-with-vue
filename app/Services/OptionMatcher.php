<?php

namespace App\Services;

use Emojione\Client;
use Emojione\Ruleset;

class OptionMatcher
{
    private $emojione;

    public function __construct()
    {
        // Initialize the Emojione client
        $this->emojione = new Client(new Ruleset());
    }

    public function isGreetings(string $message): bool
    {
        $greetings = ['hello', 'hi', 'hey', 'greetings'];

        return $this->containsSimilar($message, $greetings);
    }

    public function isAppreciation(string $message): bool
    {
        $appreciations = ['thanks', 'thank you', 'appreciate it', 'grateful'];

        return $this->containsSimilar($message, $appreciations);
    }

    public function isDisappointment(string $message): bool
    {
        $disappointments = ['disappointed', 'not happy', 'unhappy', 'let down'];

        return $this->containsSimilar($message, $disappointments);
    }

    public function isPositiveFeedback(string $message): bool
    {
        $positiveFeedback = ['great', 'excellent', 'awesome', 'fantastic'];

        return $this->containsSimilar($message, $positiveFeedback);
    }

    public function containsSimilar(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            // Use Levenshtein distance to check similarity
            $distance = levenshtein($this->removeEmojis($haystack), $this->removeEmojis($needle));

            // You can adjust the threshold based on your requirements
            $threshold = 3;

            if ($distance <= $threshold) {
                return true;
            }
        }

        return false;
    }

    private function removeEmojis(string $text): string
    {
        // Convert emojis to shortcodes
        $shortcodes = $this->emojione->getClient()->getShortcodeArray();
        $text = $this->emojione->toShort($text);

        // Remove shortcodes
        $text = preg_replace("/:[^:]+:/u", '', $text);

        return $text;
    }

    public function findClosestOption(string $normalizedMessage, array $options): ?string
    {
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

        return ($minDistance <= $threshold) ? $selectedOption : null;
    }
}

<?php


namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SendMessageReceivedReply
{
    use Dispatchable, SerializesModels;

    public $conversationId;
    public $userId;
    public $messageContent;

    public function __construct($conversationId, $userId, $messageContent)
    {
        $this->conversationId = $conversationId;
        $this->userId = $userId;
        $this->messageContent = $messageContent;
    }
}


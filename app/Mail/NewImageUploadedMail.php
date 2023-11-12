<?php

namespace App\Mail;

use App\Models\Galery;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class NewImageUploadedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $image;
    public $subject;
    public $user;

    /**
     * Create a new message instance.
     *
     * @param Galery $image
     * @param User $user
     */
    public function __construct(Galery $image, $user)
    {
        $this->image = $image;
        $this->subject = 'New Image Uploaded: ' . $this->image->title;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return 
            // $this->to($this->user->email)
            //     ->markdown('emails.mark_down_email', [
            //         'subject' => $this->subject,
            //         'image' => $this->image,
            //         'userName' => $this->user->name,
            //     ]);

            $this->to($this->user->email)
            ->subject($this->subject)
            ->view('emails.new_image_uploaded', ['user'=> $this->user, 'subject' => $this->subject]);
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserReactivated extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */

    // Class used to set the template and the subject of the email that is going to be sent when a user reactivates his account. The email creation takes place in UserController, sendReactivateEmail method.
    public function build()
    {
        return $this->text('emails.reactivate')->subject('Account reactivation');
    }
}

<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserMailChanged extends Mailable
{
    use Queueable, SerializesModels;

    public $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */

    // Class used to set the template and the subject of the email that is going to be sent when a user changes his email. The email creation takes place in AppServiceProvider
    public function build()
    {
        return $this->text('emails.confirm')->subject('Please confirm your new email');
    }
}

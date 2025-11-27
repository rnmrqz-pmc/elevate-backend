<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class RegisterMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
    public function build() {
        return $this->subject('Password Reset Request')
                    ->view('emails.registration')
                    ->with([
                        'username' => $this->payload->username ?? 'User',
                        'password' => $this->payload->password ?? 'Password1234',
                        'app_url' => $this->payload->app_url ?? 'https://pmcie.com',
                    ]);
    }
}
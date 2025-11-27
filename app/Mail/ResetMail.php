<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ResetMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
    public function build() {
        return $this->subject('Password Reset Request')
                    ->view('emails.reset')
                    ->with([
                        'resetLink' => $this->payload->resetLink ?? 'https://pmcie.com',
                        'name' => $this->payload->name ?? 'User',
                    ]);
    }
}
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class TrainingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $payload;

    public function __construct($payload) {
        $this->payload = $payload;
    }
    public function build() {
        return $this->subject('Password Reset Request')
                    ->view('emails.training')
                    ->with([
                        'name' => $this->payload->username ?? 'Juan Dela Cruz',
                        'training_name' => $this->payload->training_name ?? 'PMC Orientation',
                        'training_date' => $this->payload->training_date ?? 'January 1, 2025',
                        'training_time' => $this->payload->training_time ?? '08:00 AM',
                        'training_location' => $this->payload->training_location ?? 'Virtual via Zoom',
                        'trainer' => $this->payload->trainer ?? 'John Doe',
                    ]);
    }
}
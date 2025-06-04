<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NextMonthExamsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $upcoming;
    public $expired;

    /**
     * Create a new message instance.
     */
    public function __construct($upcoming, $expired)
    {
        $this->upcoming = $upcoming;
        $this->expired = $expired;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Nadolazeći ljekarski pregledi za idući mjesec')
            ->view('emails.next_month_exams')
            ->with([
                'upcoming' => $this->upcoming,
                'expired' => $this->expired,
            ]);
    }
}

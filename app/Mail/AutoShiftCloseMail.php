<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AutoShiftCloseMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  list<array{name: string, missing: string}>  $rows
     */
    public function __construct(
        public string $shiftCode,
        public string $cutoffLabel,
        public array $rows,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Automatska odjava, smjena ' . $this->shiftCode . ', ' . $this->cutoffLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.auto_shift_close',
        );
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AttendanceMismatchMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, \App\Models\AttendanceMismatchNotification>  $notifications
     */
    public function __construct(
        public string $periodLabel,
        public Collection $notifications,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Neslaganja dolaska i odlaska za ' . $this->periodLabel,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.attendance_mismatch',
        );
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderProductionApprovedInfoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $orderNumber;
    public ?string $description;
    public string $approvedByName;
    public string $approvedByFunkcija;
    public bool $approvedAsProxy;
    public string $approvedAt;
    public ?string $comment;

    public function __construct(
        string $orderNumber,
        ?string $description,
        string $approvedByName,
        string $approvedByFunkcija,
        bool $approvedAsProxy,
        string $approvedAt,
        ?string $comment = null
    ) {
        $this->orderNumber = $orderNumber;
        $this->description = $description;
        $this->approvedByName = $approvedByName;
        $this->approvedByFunkcija = $approvedByFunkcija;
        $this->approvedAsProxy = $approvedAsProxy;
        $this->approvedAt = $approvedAt;
        $this->comment = $comment;
    }

    public function build()
    {
        $suffix = $this->approvedAsProxy ? ' (proxy)' : '';

        return $this->subject('Odobrenje proizvodnje' . $suffix . ' — ' . $this->orderNumber)
            ->view('emails.production_approved_info')
            ->with([
                'orderNumber' => $this->orderNumber,
                'description' => $this->description,
                'approvedByName' => $this->approvedByName,
                'approvedByFunkcija' => $this->approvedByFunkcija,
                'approvedAsProxy' => $this->approvedAsProxy,
                'approvedAt' => $this->approvedAt,
                'comment' => $this->comment,
            ]);
    }
}

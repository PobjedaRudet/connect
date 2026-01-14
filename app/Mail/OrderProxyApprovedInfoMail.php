<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderProxyApprovedInfoMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public string $orderNumber;
    public ?string $description;
    public string $proxyFromFunkcija;
    public string $proxyByName;
    public string $proxyTargetFunkcija;
    public string $approvedAt;
    public ?string $comment;

    public function __construct(
        string $orderNumber,
        ?string $description,
        string $proxyFromFunkcija,
        string $proxyByName,
        string $proxyTargetFunkcija,
        string $approvedAt,
        ?string $comment = null
    ) {
        $this->orderNumber = $orderNumber;
        $this->description = $description;
        $this->proxyFromFunkcija = $proxyFromFunkcija;
        $this->proxyByName = $proxyByName;
        $this->proxyTargetFunkcija = $proxyTargetFunkcija;
        $this->approvedAt = $approvedAt;
        $this->comment = $comment;
    }

    public function build()
    {
        return $this->subject('Proxy odobrenje — ' . $this->orderNumber)
            ->view('emails.proxy_approved_info')
            ->with([
                'orderNumber' => $this->orderNumber,
                'description' => $this->description,
                'proxyFromFunkcija' => $this->proxyFromFunkcija,
                'proxyByName' => $this->proxyByName,
                'proxyTargetFunkcija' => $this->proxyTargetFunkcija,
                'approvedAt' => $this->approvedAt,
                'comment' => $this->comment,
            ]);
    }
}

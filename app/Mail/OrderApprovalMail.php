<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderApprovalMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public array $orders; // each: ['OrderNumber','Description','partner','total_qty'] minimal
    public string $funkcija;
    public int $userId;
    public string $directUrl;
    public string $openUrl;

    /**
     * @param array $orders Array of associative items: ['OrderNumber'=>string,'Description'=>string,'approval_ids'=>int[],'partner'=>string|null,'total_qty'=>float|null]
     * @param string $funkcija Target funkcija of recipient
     * @param int $userId Recipient user id (for auth check in signed URL)
     * @param int $ttlMinutes Link lifetime minutes (default 4320 = 3 days)
     */
    public function __construct(array $orders, string $funkcija, int $userId, int $ttlMinutes = 4320)
    {
        $this->orders = $orders;
        $this->funkcija = $funkcija;
        $this->userId = $userId;

        $approvalIds = [];
        foreach ($orders as $o) {
            if (!empty($o['approval_ids']) && is_array($o['approval_ids'])) {
                foreach ($o['approval_ids'] as $id) {
                    $approvalIds[] = (int)$id;
                }
            }
        }
        $approvalIds = array_values(array_unique(array_filter($approvalIds)));

        $expires = now()->addMinutes($ttlMinutes);
        $this->directUrl = URL::temporarySignedRoute(
            'approvals.email.direct',
            $expires,
            [
                'uid' => $this->userId,
                // pass CSV to keep URL compact
                'approval_ids' => implode(',', $approvalIds),
            ]
        );

        $this->openUrl = URL::temporarySignedRoute(
            'approvals.email.open',
            $expires,
            [
                'uid' => $this->userId,
            ]
        );
    }

    public function build()
    {
        return $this->subject('Novi nalozi na odobrenje')
            ->view('emails.approval')
            ->with([
                'orders' => $this->orders,
                'funkcija' => $this->funkcija,
                'directUrl' => $this->directUrl,
                'openUrl' => $this->openUrl,
            ]);
    }
}

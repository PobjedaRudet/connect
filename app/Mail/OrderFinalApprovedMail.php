<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class OrderFinalApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @var array<int,array<string,mixed>> */
    public array $orders;

    /**
     * @param array<int,array<string,mixed>> $orders Each: ['OrderNumber','Description','partner','total_qty','created_at','creator']
     */
    public function __construct(array $orders)
    {
        $this->orders = $orders;
    }

    public function build()
    {
        $subject = count($this->orders) > 1 ? 'Nalozi odobreni' : 'Nalog odobren';
        return $this->subject($subject)
            ->view('emails.final_approved')
            ->with([
                'orders' => $this->orders,
            ]);
    }
}

<?php

namespace App\Mail;

use App\Models\ProductionOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderStatusUpdated extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    /**
     * Create a new message instance.
     */
    public function __construct(ProductionOrder $order)
    {
        $this->order = $order;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('Status naloga ažuriran')
            ->view('emails.order_status_updated')
            ->with([
                'order' => $this->order,
            ]);
    }

}

<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\StoreSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A copy of the till receipt, emailed to the customer after a sale.
 */
class PosReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public $payments = null)
    {
    }

    public function envelope(): Envelope
    {
        $store = StoreSetting::getSettings();
        $name = $store?->store_name ?: config('app.name');

        return new Envelope(
            subject: "Your receipt from {$name} – {$this->order->id}",
            replyTo: $store?->email ? [new Address($store->email, $name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.pos-receipt',
            with: [
                'order' => $this->order,
                'payments' => collect($this->payments ?? []),
                'store' => StoreSetting::getSettings(),
                'footer' => \App\Models\SystemSetting::get('receipt_footer'),
            ],
        );
    }
}

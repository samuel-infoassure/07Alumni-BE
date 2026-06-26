<?php

namespace App\Notifications;

use App\Models\DuesPayment;
use Illuminate\Notifications\Notification;

class DuesPaymentNotification extends Notification
{
    public function __construct(public readonly DuesPayment $payment) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $months = $this->payment->months_count;
        $amount = number_format($this->payment->amount_kobo / 100, 0, '.', ',');

        return [
            'type'       => 'dues_paid',
            'title'      => 'Dues Payment Confirmed',
            'body'       => "Your payment of ₦{$amount} for {$months} month(s) dues has been confirmed.",
            'payment_id' => $this->payment->id,
            'months'     => $months,
            'amount'     => $this->payment->amount_kobo / 100,
            'reference'  => $this->payment->paystack_reference,
        ];
    }
}

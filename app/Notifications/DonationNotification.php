<?php

namespace App\Notifications;

use App\Models\Donation;
use Illuminate\Notifications\Notification;

class DonationNotification extends Notification
{
    public function __construct(
        public readonly Donation $donation,
        public readonly string $context = 'donor'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $amount = number_format($this->donation->amount, 0, '.', ',');

        return $this->context === 'treasurer'
            ? [
                'type'        => 'donation_received',
                'title'       => 'Donation Received',
                'body'        => "A donation of ₦{$amount} has been recorded.",
                'donation_id' => $this->donation->id,
                'amount'      => $this->donation->amount,
                'reference'   => $this->donation->reference,
              ]
            : [
                'type'        => 'donation_confirmed',
                'title'       => 'Donation Confirmed',
                'body'        => "Your donation of ₦{$amount} has been recorded. Thank you!",
                'donation_id' => $this->donation->id,
                'amount'      => $this->donation->amount,
                'reference'   => $this->donation->reference,
              ];
    }
}

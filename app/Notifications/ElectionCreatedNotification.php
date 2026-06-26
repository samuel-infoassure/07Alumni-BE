<?php

namespace App\Notifications;

use App\Models\Election;
use Illuminate\Notifications\Notification;

class ElectionCreatedNotification extends Notification
{
    public function __construct(public readonly Election $election) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'        => 'election_created',
            'title'       => 'Election Announced',
            'body'        => "An election has been announced: {$this->election->title}",
            'election_id' => $this->election->id,
            'start_date'  => $this->election->start_date,
            'end_date'    => $this->election->end_date,
        ];
    }
}

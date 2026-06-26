<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Notifications\Notification;

class EventCreatedNotification extends Notification
{
    public function __construct(public readonly Event $event) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'event_created',
            'title'      => 'New Event',
            'body'       => "A new event has been announced: {$this->event->title}",
            'event_id'   => $this->event->id,
            'event_date' => $this->event->event_date,
            'venue'      => $this->event->venue,
        ];
    }
}

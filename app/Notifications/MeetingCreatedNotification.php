<?php

namespace App\Notifications;

use App\Models\Meeting;
use Illuminate\Notifications\Notification;

class MeetingCreatedNotification extends Notification
{
    public function __construct(public readonly Meeting $meeting) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'         => 'meeting_created',
            'title'        => 'New Meeting Scheduled',
            'body'         => "A meeting has been scheduled: {$this->meeting->title}",
            'meeting_id'   => $this->meeting->id,
            'meeting_date' => $this->meeting->meeting_date,
            'venue'        => $this->meeting->venue,
        ];
    }
}

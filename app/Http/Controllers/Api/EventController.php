<?php

namespace App\Http\Controllers\Api;

use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\User;
use App\Notifications\EventCreatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class EventController extends ApiController
{
    public function index(Request $request)
    {
        $events = Event::with('creator:id,first_name,last_name')
            ->withCount('registrations')
            ->orderByDesc('event_date')
            ->get()
            ->map(fn (Event $e) => [
                ...$e->toArray(),
                'is_registered' => $e->registrations->contains('user_id', $request->user()->id),
            ]);

        return $this->success($events->toArray(), 'Events loaded.');
    }

    public function store(Request $request)
    {
        if ($guard = $this->gate('events.create')) {
            return $guard;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'event_date' => ['required', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:physical,virtual,hybrid'],
            'event_link' => ['nullable', 'string', 'url'],
            'cover_image' => ['nullable', 'string', 'url'],
            'max_attendees' => ['nullable', 'integer', 'min:1'],
            'registration_deadline' => ['nullable', 'date'],
            'status' => ['nullable', 'in:upcoming,ongoing,completed,cancelled'],
        ]);

        $event = Event::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        try {
            $users = User::where('id', '!=', $request->user()->id)->get();
            Notification::send($users, new EventCreatedNotification($event));
        } catch (\Throwable) {
        }

        return $this->success($event->toArray(), 'Event created.', 201);
    }

    public function show(int $id)
    {
        $event = Event::with([
            'creator:id,first_name,last_name',
            'registrations.user:id,first_name,last_name',
        ])->withCount('registrations')->findOrFail($id);

        $isRegistered = $event->registrations->contains('user_id', request()->user()->id);

        return $this->success([
            ...$event->toArray(),
            'is_registered' => $isRegistered,
        ], 'Event details loaded.');
    }

    public function update(Request $request, int $id)
    {
        if ($guard = $this->gate('events.update')) {
            return $guard;
        }

        $event = Event::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'event_date' => ['sometimes', 'date'],
            'event_time' => ['nullable', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'in:physical,virtual,hybrid'],
            'event_link' => ['nullable', 'string', 'url'],
            'cover_image' => ['nullable', 'string', 'url'],
            'max_attendees' => ['nullable', 'integer', 'min:1'],
            'registration_deadline' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:upcoming,ongoing,completed,cancelled'],
        ]);

        $event->update($validated);

        return $this->success($event->toArray(), 'Event updated.');
    }

    public function destroy(int $id)
    {
        if ($guard = $this->gate('events.delete')) {
            return $guard;
        }

        Event::findOrFail($id)->delete();

        return $this->success([], 'Event deleted.');
    }

    public function register(Request $request, int $id)
    {
        $event = Event::withCount(['registrations' => fn ($q) => $q->where('status', '!=', 'cancelled')])->findOrFail($id);
        $userId = $request->user()->id;

        $existing = EventRegistration::where('event_id', $id)->where('user_id', $userId)->first();

        if ($existing) {
            if ($existing->status === 'cancelled') {
                $existing->update(['status' => 'registered', 'registered_at' => now()]);

                return $this->success($existing->toArray(), 'Re-registered for event.');
            }

            return $this->failure('Already registered for this event.', 409);
        }

        if ($event->max_attendees && $event->registrations_count >= $event->max_attendees) {
            return $this->failure('Event is at full capacity.', 400);
        }

        $registration = EventRegistration::create([
            'event_id' => $id,
            'user_id' => $userId,
            'status' => 'registered',
        ]);

        return $this->success($registration->toArray(), 'Registered for event.', 201);
    }

    public function cancelRegistration(Request $request, int $id)
    {
        $registration = EventRegistration::where('event_id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $registration->update(['status' => 'cancelled']);

        return $this->success([], 'Registration cancelled.');
    }
}

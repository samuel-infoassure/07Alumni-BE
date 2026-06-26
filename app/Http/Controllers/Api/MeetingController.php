<?php

namespace App\Http\Controllers\Api;

use App\Models\Meeting;
use App\Models\MeetingAttendance;
use App\Models\MeetingMinute;
use App\Notifications\MeetingCreatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class MeetingController extends ApiController
{
    public function index()
    {
        $meetings = Meeting::with(['creator:id,first_name,last_name', 'minutes'])
            ->withCount('attendances')
            ->orderByDesc('meeting_date')
            ->get()
            ->map(fn (Meeting $m) => [
                ...$m->toArray(),
                'has_minutes' => $m->minutes !== null,
            ]);

        return $this->success($meetings->toArray(), 'Meetings loaded.');
    }

    public function store(Request $request)
    {
        if ($guard = $this->gate('meetings.create')) {
            return $guard;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_date' => ['required', 'date'],
            'meeting_time' => ['required', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:physical,virtual,hybrid'],
            'meeting_link' => ['nullable', 'string', 'url'],
            'agenda' => ['nullable', 'string'],
            'status' => ['nullable', 'in:scheduled,ongoing,completed,cancelled'],
        ]);

        $meeting = Meeting::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        try {
            $users = \App\Models\User::where('id', '!=', $request->user()->id)->get();
            \Illuminate\Support\Facades\Notification::send($users, new \App\Notifications\MeetingCreatedNotification($meeting));
        } catch (\Throwable) {}

        return $this->success($meeting->toArray(), 'Meeting created.', 201);
    }

    public function show(int $id)
    {
        $meeting = Meeting::with([
            'creator:id,first_name,last_name',
            'attendances.user:id,first_name,last_name',
            'minutes.recorder:id,first_name,last_name',
        ])->findOrFail($id);

        return $this->success($meeting->toArray(), 'Meeting details loaded.');
    }

    public function update(Request $request, int $id)
    {
        if ($guard = $this->gate('meetings.update')) {
            return $guard;
        }

        $meeting = Meeting::findOrFail($id);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'meeting_date' => ['sometimes', 'date'],
            'meeting_time' => ['sometimes', 'date_format:H:i'],
            'venue' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'in:physical,virtual,hybrid'],
            'meeting_link' => ['nullable', 'string', 'url'],
            'agenda' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:scheduled,ongoing,completed,cancelled'],
        ]);

        $meeting->update($validated);

        return $this->success($meeting->toArray(), 'Meeting updated.');
    }

    public function destroy(int $id)
    {
        if ($guard = $this->gate('meetings.delete')) {
            return $guard;
        }

        Meeting::findOrFail($id)->delete();

        return $this->success([], 'Meeting deleted.');
    }

    public function markAttendance(Request $request, int $id)
    {
        if ($guard = $this->gate('meetings.attend')) {
            return $guard;
        }

        $meeting = Meeting::findOrFail($id);

        $validated = $request->validate([
            'attendances' => ['required', 'array'],
            'attendances.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'attendances.*.status' => ['required', 'in:present,absent,excused'],
            'attendances.*.remarks' => ['nullable', 'string'],
        ]);

        foreach ($validated['attendances'] as $record) {
            MeetingAttendance::updateOrCreate(
                ['meeting_id' => $meeting->id, 'user_id' => $record['user_id']],
                [
                    'status' => $record['status'],
                    'remarks' => $record['remarks'] ?? null,
                    'signed_at' => now(),
                ]
            );
        }

        $meeting->load('attendances.user:id,first_name,last_name');

        return $this->success($meeting->attendances->toArray(), 'Attendance recorded.');
    }

    public function saveMinutes(Request $request, int $id)
    {
        if ($guard = $this->gate('meetings.minutes')) {
            return $guard;
        }

        $meeting = Meeting::findOrFail($id);

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $minutes = MeetingMinute::updateOrCreate(
            ['meeting_id' => $meeting->id],
            [
                'recorded_by' => $request->user()->id,
                'content' => $validated['content'],
                'published_at' => ($validated['publish'] ?? false) ? now() : null,
            ]
        );

        return $this->success($minutes->toArray(), 'Minutes saved.');
    }
}

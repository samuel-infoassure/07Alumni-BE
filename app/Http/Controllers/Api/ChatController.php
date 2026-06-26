<?php

namespace App\Http\Controllers\Api;

use App\Models\ChatGroup;
use App\Models\Message;
use Illuminate\Http\Request;

class ChatController extends ApiController
{
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $groups = ChatGroup::with(['creator:id,first_name,last_name'])
            ->withCount('members')
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (ChatGroup $g) use ($userId) {
                $latestMessage = $g->messages()->with('user:id,first_name,last_name')->latest()->first();

                return [
                    ...$g->toArray(),
                    'is_member' => $g->members()->where('user_id', $userId)->exists(),
                    'latest_message' => $latestMessage,
                ];
            });

        return $this->success($groups->toArray(), 'Chat groups loaded.');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'in:public,private'],
        ]);

        $group = ChatGroup::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        $group->members()->attach($request->user()->id, ['role' => 'admin', 'joined_at' => now()]);

        return $this->success($group->toArray(), 'Chat group created.', 201);
    }

    public function messages(Request $request, int $groupId)
    {
        $group = ChatGroup::findOrFail($groupId);

        $messages = Message::with(['user:id,first_name,last_name', 'replyTo.user:id,first_name,last_name'])
            ->where('group_id', $groupId)
            ->orderBy('created_at')
            ->paginate(50);

        return $this->success([
            'group' => $group->only(['id', 'name', 'type', 'description']),
            'messages' => $messages->items(),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'total' => $messages->total(),
            ],
        ], 'Messages loaded.');
    }

    public function sendMessage(Request $request, int $groupId)
    {
        ChatGroup::findOrFail($groupId);

        $validated = $request->validate([
            'content' => ['required', 'string'],
            'type' => ['nullable', 'in:text,image,file,announcement'],
            'file_url' => ['nullable', 'string', 'url'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);

        $message = Message::create([
            ...$validated,
            'group_id' => $groupId,
            'user_id' => $request->user()->id,
            'type' => $validated['type'] ?? 'text',
        ]);

        $message->load('user:id,first_name,last_name', 'replyTo.user:id,first_name,last_name');

        return $this->success($message->toArray(), 'Message sent.', 201);
    }

    public function joinGroup(Request $request, int $groupId)
    {
        $group = ChatGroup::findOrFail($groupId);
        $userId = $request->user()->id;

        if ($group->members()->where('user_id', $userId)->exists()) {
            return $this->failure('Already a member of this group.', 409);
        }

        $group->members()->attach($userId, ['role' => 'member', 'joined_at' => now()]);

        return $this->success([], 'Joined group successfully.');
    }
}

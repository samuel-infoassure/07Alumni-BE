<?php

namespace App\Http\Controllers\Api;

use App\Models\Election;
use App\Models\ElectionCandidate;
use App\Models\ElectionVote;
use App\Models\Exco;
use App\Notifications\ElectionCreatedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class ExcoController extends ApiController
{
    public function index()
    {
        $current = Exco::with('user:id,first_name,last_name,email')
            ->where('status', 'current')
            ->orderBy('position')
            ->get();

        $past = Exco::with('user:id,first_name,last_name,email')
            ->where('status', 'past')
            ->orderByDesc('term_end')
            ->get();

        return $this->success([
            'current' => $current->toArray(),
            'past' => $past->toArray(),
        ], 'EXCO members loaded.');
    }

    public function store(Request $request)
    {
        if ($guard = $this->gate('excos.manage')) {
            return $guard;
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'position' => ['required', 'string', 'max:255'],
            'term_start' => ['required', 'date'],
            'term_end' => ['nullable', 'date', 'after:term_start'],
            'bio' => ['nullable', 'string'],
            'photo' => ['nullable', 'string', 'url'],
            'status' => ['nullable', 'in:current,past'],
        ]);

        $exco = Exco::create($validated);

        return $this->success($exco->toArray(), 'EXCO member added.', 201);
    }

    public function show(int $id)
    {
        $exco = Exco::with('user:id,first_name,last_name,email')->findOrFail($id);

        return $this->success($exco->toArray(), 'EXCO details loaded.');
    }

    public function update(Request $request, int $id)
    {
        if ($guard = $this->gate('excos.manage')) {
            return $guard;
        }

        $exco = Exco::findOrFail($id);

        $validated = $request->validate([
            'position' => ['sometimes', 'string', 'max:255'],
            'term_start' => ['sometimes', 'date'],
            'term_end' => ['nullable', 'date'],
            'bio' => ['nullable', 'string'],
            'photo' => ['nullable', 'string', 'url'],
            'status' => ['sometimes', 'in:current,past'],
        ]);

        $exco->update($validated);

        return $this->success($exco->toArray(), 'EXCO updated.');
    }

    public function destroy(int $id)
    {
        if ($guard = $this->gate('excos.manage')) {
            return $guard;
        }

        Exco::findOrFail($id)->delete();

        return $this->success([], 'EXCO record deleted.');
    }

    public function elections()
    {
        $elections = Election::with(['creator:id,first_name,last_name', 'candidates.user:id,first_name,last_name'])
            ->withCount('votes')
            ->orderByDesc('start_date')
            ->get();

        return $this->success($elections->toArray(), 'Elections loaded.');
    }

    public function createElection(Request $request)
    {
        if ($guard = $this->gate('elections.create')) {
            return $guard;
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'candidates' => ['nullable', 'array'],
            'candidates.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'candidates.*.position' => ['required', 'string', 'max:255'],
            'candidates.*.manifesto' => ['nullable', 'string'],
        ]);

        $election = DB::transaction(function () use ($validated, $request) {
            $election = Election::create([
                'created_by' => $request->user()->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'status' => 'upcoming',
            ]);

            foreach ($validated['candidates'] ?? [] as $candidate) {
                ElectionCandidate::create([
                    'election_id' => $election->id,
                    'user_id' => $candidate['user_id'],
                    'position' => $candidate['position'],
                    'manifesto' => $candidate['manifesto'] ?? null,
                ]);
            }

            return $election;
        });

        try {
            $users = \App\Models\User::where('id', '!=', $request->user()->id)->get();
            Notification::send($users, new ElectionCreatedNotification($election));
        } catch (\Throwable) {}

        return $this->success($election->load('candidates.user:id,first_name,last_name')->toArray(), 'Election created.', 201);
    }

    public function vote(Request $request, int $electionId)
    {
        $election = Election::findOrFail($electionId);

        if ($election->status !== 'ongoing') {
            return $this->failure('This election is not currently open for voting.', 400);
        }

        $validated = $request->validate([
            'candidate_id' => ['required', 'integer', 'exists:election_candidates,id'],
        ]);

        $candidate = ElectionCandidate::where('id', $validated['candidate_id'])
            ->where('election_id', $electionId)
            ->firstOrFail();

        $alreadyVoted = ElectionVote::where('election_id', $electionId)
            ->where('voter_id', $request->user()->id)
            ->whereHas('candidate', fn ($q) => $q->where('position', $candidate->position))
            ->exists();

        if ($alreadyVoted) {
            return $this->failure('You have already voted for this position.', 409);
        }

        DB::transaction(function () use ($electionId, $validated, $request, $candidate) {
            ElectionVote::create([
                'election_id' => $electionId,
                'candidate_id' => $validated['candidate_id'],
                'voter_id' => $request->user()->id,
            ]);

            $candidate->increment('vote_count');
        });

        return $this->success([], 'Vote cast successfully.');
    }
}

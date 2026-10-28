<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request)
    {
        $this->authorize('docs.logs.view');

        $validated = $request->validate([
            'action' => ['nullable', Rule::in(ActivityLog::ACTIONS)],
        ]);
        $action = $validated['action'] ?? null;

        // Only the snapshot fields the page renders — never user content,
        // tokens, or storage paths (none of which exist on this table).
        $logs = ActivityLog::query()
            ->select([
                'id',
                'actor_name',
                'actor_email',
                'action',
                'document_id',
                'document_name',
                'created_at',
            ])
            ->when($action, fn ($query) => $query->where('action', $action))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Docs/Logs', [
            'logs' => $logs,
            'filters' => ['action' => $action ?? ''],
        ])->toResponse($request)
            ->header('Cache-Control', 'private, no-store');
    }
}

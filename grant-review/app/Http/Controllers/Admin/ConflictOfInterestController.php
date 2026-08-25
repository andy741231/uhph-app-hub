<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConflictOfInterestDeclaration;
use App\Models\Round;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConflictOfInterestController extends Controller
{
    public function index(Request $request): View
    {
        $roundId = $request->integer('round_id') ?: null;
        $status = in_array($request->query('status'), ['conflicts', 'clear'], true)
            ? $request->query('status')
            : null;
        $search = trim((string) $request->query('q', ''));

        $query = ConflictOfInterestDeclaration::query()
            ->with(['reviewer', 'round', 'entries.submission.submitter'])
            ->when($roundId, fn ($query) => $query->where('round_id', $roundId))
            ->when($status === 'conflicts', fn ($query) => $query->whereHas('entries'))
            ->when($status === 'clear', fn ($query) => $query->whereDoesntHave('entries'))
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereHas('reviewer', function ($query) use ($search): void {
                        $query->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })->orWhereHas('entries.submission', fn ($query) => $query->where('title', 'like', "%{$search}%"));
                });
            })
            ->latest('declared_at');

        $declarations = $query->get();
        $rounds = Round::query()->latest('opens_at')->get(['id', 'name']);
        $stats = [
            'declarations' => $declarations->count(),
            'with_conflicts' => $declarations->filter(fn ($declaration) => $declaration->entries->isNotEmpty())->count(),
            'clear' => $declarations->filter(fn ($declaration) => $declaration->entries->isEmpty())->count(),
            'conflicts' => $declarations->sum(fn ($declaration) => $declaration->entries->count()),
        ];

        return view('admin.conflicts.index', compact('declarations', 'rounds', 'roundId', 'status', 'search', 'stats'));
    }
}

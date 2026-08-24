<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoundRequest;
use App\Http\Requests\UpdateRoundRequest;
use App\Models\Round;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoundController extends Controller
{
    public function index(): View
    {
        $rounds = Round::orderBy('created_at', 'desc')->get();

        return view('admin.rounds.index', compact('rounds'));
    }

    public function create(): View
    {
        return view('admin.rounds.create');
    }

    public function store(StoreRoundRequest $request): RedirectResponse
    {
        Round::create($request->validated());

        return redirect()->route('admin.rounds.index')->with('status', 'Round created.');
    }

    public function edit(Round $round): View
    {
        return view('admin.rounds.edit', compact('round'));
    }

    public function update(UpdateRoundRequest $request, Round $round): RedirectResponse
    {
        $round->update($request->validated());

        return redirect()->route('admin.rounds.index')->with('status', 'Round updated.');
    }

    public function destroy(Round $round): RedirectResponse
    {
        $round->delete();

        return redirect()->route('admin.rounds.index')->with('status', 'Round deleted.');
    }
}

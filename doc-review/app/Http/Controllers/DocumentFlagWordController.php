<?php

namespace App\Http\Controllers;

use App\Models\DocumentFlagWord;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentFlagWordController extends Controller
{
    use AuthorizesRequests;

    /** Columns that may be used for sorting. */
    protected const SORTABLE = ['word', 'suggested_replacement', 'created_at'];

    public function index(Request $request)
    {
        $this->authorize('docs.flagword.manage');

        $search = trim((string) $request->query('search', ''));
        $sort = (string) $request->query('sort', 'word');
        $direction = strtolower((string) $request->query('direction', 'asc')) === 'desc' ? 'desc' : 'asc';

        if (! in_array($sort, self::SORTABLE, true)) {
            $sort = 'word';
        }

        $words = DocumentFlagWord::query()
            ->with('creator')
            ->when($search !== '', function ($query) use ($search) {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $query->where(function ($q) use ($term) {
                    $q->where('word', 'like', $term)
                        ->orWhere('suggested_replacement', 'like', $term);
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Docs/FlagWords', [
            'flagWords' => $words,
            'filters' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('docs.flagword.manage');

        $validated = $request->validate([
            'word' => ['required', 'string', 'max:255'],
            'suggested_replacement' => ['nullable', 'string', 'max:255'],
        ]);

        $word = trim($validated['word']);
        $replacement = trim($validated['suggested_replacement'] ?? '') ?: null;

        // Case-insensitive uniqueness
        $exists = DocumentFlagWord::query()
            ->whereRaw('LOWER(word) = ?', [strtolower($word)])
            ->exists();

        if ($exists) {
            return redirect()
                ->back()
                ->withErrors(['word' => 'This flag word already exists.'])
                ->withInput();
        }

        DocumentFlagWord::create([
            'word' => $word,
            'suggested_replacement' => $replacement,
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('docs.flag-words.index')
            ->with('success', 'Flag word added.');
    }

    public function update(Request $request, DocumentFlagWord $flagWord)
    {
        $this->authorize('docs.flagword.manage');

        $validated = $request->validate([
            'word' => ['required', 'string', 'max:255'],
            'suggested_replacement' => ['nullable', 'string', 'max:255'],
        ]);

        $word = trim($validated['word']);
        $replacement = trim($validated['suggested_replacement'] ?? '') ?: null;

        // Case-insensitive uniqueness (excluding the current record)
        $exists = DocumentFlagWord::query()
            ->whereRaw('LOWER(word) = ?', [strtolower($word)])
            ->where('id', '!=', $flagWord->id)
            ->exists();

        if ($exists) {
            return redirect()
                ->back()
                ->withErrors(['word' => 'This flag word already exists.'])
                ->withInput();
        }

        $flagWord->update([
            'word' => $word,
            'suggested_replacement' => $replacement,
        ]);

        return redirect()
            ->route('docs.flag-words.index')
            ->with('success', 'Flag word updated.');
    }

    public function destroy(Request $request, DocumentFlagWord $flagWord)
    {
        $this->authorize('docs.flagword.manage');

        $flagWord->delete();

        return redirect()
            ->route('docs.flag-words.index')
            ->with('success', 'Flag word removed.');
    }

    public function bulkDestroy(Request $request)
    {
        $this->authorize('docs.flagword.manage');

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:document_flag_words,id'],
        ]);

        $count = DocumentFlagWord::query()
            ->whereIn('id', $validated['ids'])
            ->delete();

        return redirect()
            ->route('docs.flag-words.index')
            ->with('success', $count.' flag word'.($count === 1 ? '' : 's').' removed.');
    }
}

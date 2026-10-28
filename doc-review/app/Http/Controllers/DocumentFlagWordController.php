<?php

namespace App\Http\Controllers;

use App\Models\DocumentFlagWord;
use App\Services\FlagWordImporter;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Throwable;

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
            'match_type' => ['nullable', Rule::in(DocumentFlagWord::MATCH_TYPES)],
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
            'match_type' => $validated['match_type'] ?? DocumentFlagWord::MATCH_WHOLE_WORD,
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
            'match_type' => ['nullable', Rule::in(DocumentFlagWord::MATCH_TYPES)],
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

        $attributes = [
            'word' => $word,
            'suggested_replacement' => $replacement,
        ];
        // An omitted match_type keeps the existing mode.
        if (array_key_exists('match_type', $validated) && $validated['match_type'] !== null) {
            $attributes['match_type'] = $validated['match_type'];
        }

        $flagWord->update($attributes);

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

    public function export()
    {
        $this->authorize('docs.flagword.manage');

        $words = DocumentFlagWord::query()
            ->orderBy('word')
            ->get(['word', 'suggested_replacement', 'match_type']);

        return response()->streamDownload(function () use ($words) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['word', 'suggested_replacement', 'match_type'], escape: '');
            foreach ($words as $word) {
                fputcsv($out, [$word->word, $word->suggested_replacement, $word->match_type], escape: '');
            }
            fclose($out);
        }, 'flag-words-'.now()->toDateString().'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function template()
    {
        $this->authorize('docs.flagword.manage');

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['word', 'suggested_replacement', 'match_type'], escape: '');
            fputcsv($out, ['ai', '', 'whole_word'], escape: '');
            fputcsv($out, ['divers', 'inclusive language', 'word_root'], escape: '');
            fclose($out);
        }, 'flag-words-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function import(Request $request, FlagWordImporter $importer)
    {
        $this->authorize('docs.flagword.manage');

        $validated = $request->validate([
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:2048'],
        ]);

        try {
            $result = $importer->importCsv(
                $validated['file']->getRealPath(),
                $request->user()->id
            );
        } catch (Throwable) {
            return redirect()
                ->back()
                ->withErrors(['file' => 'The file could not be read as CSV.'])
                ->withInput();
        }

        $imported = $result['imported'];
        $skipped = count($result['skipped']);

        return redirect()
            ->route('docs.flag-words.index')
            ->with('success', 'Import complete: '.$imported.' flag word'.($imported === 1 ? '' : 's').' added, '.$skipped.' skipped.')
            ->with('import_result', $result);
    }
}

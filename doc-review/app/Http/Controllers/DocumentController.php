<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentFlag;
use App\Models\DocumentFlagWord;
use App\Services\DocumentTextExtractor;
use App\Services\DocxToPdfConverter;
use App\Services\FlagScanner;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends Controller
{
    use AuthorizesRequests;

    /** Max upload size in KB */
    protected const MAX_UPLOAD_KB = 10240;

    /** User-facing message for extraction failures (details stay in the log). */
    protected const EXTRACTION_ERROR = 'Text extraction failed for this document. Try a different file or contact an administrator.';

    public function index(Request $request)
    {
        $this->authorize('viewAny', Document::class);

        $canManage = auth()->user()->can('docs.document.manage');

        $documents = Document::query()
            ->with('user')
            ->when(! $canManage, function ($query) {
                $query->where('user_id', auth()->id());
            })
            ->when($request->search, function ($query, $search) {
                $query->where('original_name', 'like', "%{$search}%");
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Docs/Index', [
            'documents' => $documents,
            'filters' => $request->only(['search', 'status']),
            'canManage' => $canManage,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Document::class);

        return Inertia::render('Docs/Create');
    }

    public function store(Request $request, DocumentTextExtractor $extractor, DocxToPdfConverter $converter, FlagScanner $scanner)
    {
        $this->authorize('create', Document::class);

        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.self::MAX_UPLOAD_KB,
                // Extension-based check (not mimes): fileinfo content-sniffs
                // e.g. .txt files containing markup text as text/html and
                // would wrongly reject them. Content is always treated as
                // untrusted data downstream — extracted text is escaped and
                // Word files are re-encoded by LibreOffice.
                'extensions:txt,pdf,docx,doc',
            ],
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Store on the private local disk (not web-accessible). The local
        // adapter normally throws on failure, but guard the contract so a
        // false return can never create a row with a bogus path.
        $storedPath = $file->store('documents', 'local');
        if (! is_string($storedPath) || $storedPath === '') {
            Log::error('Document upload could not be stored: '.$originalName);

            return redirect()
                ->route('docs.create')
                ->withErrors(['file' => 'The file could not be stored. Try again or contact an administrator.']);
        }

        $document = Document::create([
            'user_id' => auth()->id(),
            'original_name' => $originalName,
            'file_path' => $storedPath,
            'mime_type' => $mimeType,
            'size' => $size,
            'status' => 'pending',
            'flag_count' => 0,
        ]);

        // Extract text + scan for flag words
        try {
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $isWord = in_array($ext, ['docx', 'doc']) || str_contains(strtolower($mimeType), 'word');

            // For Word documents, convert to PDF first so that pagination
            // matches the original document (LibreOffice has a real layout
            // engine, unlike PHPWord which only splits on explicit page breaks).
            $pdfPreviewPath = null;
            if ($isWord) {
                try {
                    $pdfPreviewPath = $converter->convert('local', $storedPath);
                } catch (\Throwable $e) {
                    Log::error('LibreOffice docx→PDF conversion failed during upload: '.$e->getMessage());
                }
            }

            if ($pdfPreviewPath) {
                // Extract per-page text from the converted PDF (accurate pagination)
                $pages = $extractor->extractPdfPerPageFromDisk('local', $pdfPreviewPath);
            } else {
                $pages = $extractor->extractPerPage('local', $storedPath, $mimeType, $originalName);
            }

            $text = implode("\n\n", $pages);

            $flagWords = DocumentFlagWord::query()->pluck('word')->all();
            $perPageScan = $scanner->scanPerPage($pages, $flagWords);

            // For Word docs without a PDF preview, keep the plain-text pages so
            // the viewer can page through escaped text (never document-supplied
            // HTML).
            $renderedPages = ($isWord && ! $pdfPreviewPath) ? $pages : null;

            $document->update([
                'extracted_text' => $text,
                'rendered_pages' => $renderedPages,
                'pdf_preview_path' => $pdfPreviewPath,
                'status' => 'scanned',
                'flag_count' => $perPageScan['total'],
                'flagged_pages' => $perPageScan['pages'],
            ]);

            $this->persistFlags($document, $perPageScan);
        } catch (\Throwable $e) {
            Log::error('Document extraction failed for document '.$document->id.': '.$e->getMessage());
            $document->update([
                'status' => 'failed',
                'error' => self::EXTRACTION_ERROR,
            ]);

            return redirect()
                ->route('docs.show', $document->id)
                ->with('warning', 'Document uploaded, but '.lcfirst(self::EXTRACTION_ERROR));
        }

        return redirect()
            ->route('docs.show', $document->id)
            ->with('success', 'Document uploaded and reviewed.');
    }

    public function show(Request $request, Document $document, FlagScanner $scanner)
    {
        $this->authorize('view', $document);

        $document->load(['user', 'flags.flagWord']);

        // Build the list of flag words that actually appear (for the summary)
        $flaggedWords = $document->flags->map(function ($flag) {
            return [
                'word' => $flag->flagWord->word,
                'occurrences' => $flag->occurrences,
                'suggested_replacement' => $flag->flagWord->suggested_replacement,
            ];
        })->sortByDesc('occurrences')->values()->all();

        // Build a regex pattern the frontend can use to highlight matches in the viewer
        $highlightPattern = $scanner->buildHighlightPattern(
            $document->flags->map(fn ($f) => $f->flagWord->word)->all()
        );

        // Build a word → suggested_replacement map for all flag words so the
        // right panel can display replacements even for words stored only in
        // the document's flagged_pages JSON (which has no replacement column).
        $flagWordMap = DocumentFlagWord::query()
            ->whereNotNull('suggested_replacement')
            ->pluck('suggested_replacement', 'word')
            ->all();

        return Inertia::render('Docs/Show', [
            'document' => [
                'id' => $document->id,
                'original_name' => $document->original_name,
                'mime_type' => $document->mime_type,
                'size' => $document->size,
                'status' => $document->status,
                'flag_count' => $document->flag_count,
                'flagged_pages' => $document->flagged_pages ?? [],
                'error' => $document->error,
                'extracted_text' => $document->extracted_text,
                'pdf_preview_path' => $document->pdf_preview_path,
                'created_at' => $document->created_at,
                'user' => [
                    'id' => $document->user->id,
                    'name' => $document->user->name,
                ],
            ],
            'flaggedWords' => $flaggedWords,
            'flagWordMap' => $flagWordMap,
            'highlightPattern' => $highlightPattern,
            'can' => [
                'delete' => auth()->user()->can('delete', $document),
                'update' => auth()->user()->can('update', $document),
            ],
        ]);
    }

    public function destroy(Request $request, Document $document)
    {
        $this->authorize('delete', $document);

        $document->delete();

        return redirect()
            ->route('docs.index')
            ->with('success', 'Document deleted.');
    }

    public function rescan(Request $request, Document $document, DocumentTextExtractor $extractor, DocxToPdfConverter $converter, FlagScanner $scanner)
    {
        $this->authorize('update', $document);

        // A freshly converted preview is only staged here — it replaces the
        // existing preview pointer (and the old file is deleted) solely
        // after the full extract/scan/persist pipeline commits. A staged
        // file that is never adopted is removed as an orphan.
        $stagedPreviewPath = null;
        $previousPreviewPath = $document->pdf_preview_path;
        $committed = false;

        try {
            $ext = strtolower(pathinfo($document->original_name, PATHINFO_EXTENSION));
            $isWord = in_array($ext, ['docx', 'doc']) || str_contains(strtolower($document->mime_type), 'word');

            if ($isWord) {
                try {
                    $stagedPreviewPath = $converter->convert('local', $document->file_path);
                } catch (\Throwable $e) {
                    Log::error('LibreOffice docx→PDF conversion failed during rescan: '.$e->getMessage());
                    $stagedPreviewPath = null;
                }
            }

            // Extract and scan BEFORE mutating any document state, so a
            // parse failure leaves the prior scan results untouched.
            $pdfPreviewPath = $stagedPreviewPath ?? $document->pdf_preview_path;
            if ($pdfPreviewPath) {
                $pages = $extractor->extractPdfPerPageFromDisk('local', $pdfPreviewPath);
            } else {
                $pages = $extractor->extractPerPage('local', $document->file_path, $document->mime_type, $document->original_name);
            }

            $text = implode("\n\n", $pages);

            $flagWords = DocumentFlagWord::query()->pluck('word')->all();
            $perPageScan = $scanner->scanPerPage($pages, $flagWords);

            // Refresh the plain-text fallback pages for Word docs without a
            // PDF preview (escaped text only — never document-supplied HTML).
            $renderedPages = ($isWord && ! $pdfPreviewPath) ? $pages : null;

            DB::transaction(function () use ($document, $text, $renderedPages, $pdfPreviewPath, $perPageScan) {
                $document->flags()->delete();

                $document->update([
                    'extracted_text' => $text,
                    'rendered_pages' => $renderedPages,
                    'pdf_preview_path' => $pdfPreviewPath,
                    'status' => 'scanned',
                    'flag_count' => $perPageScan['total'],
                    'flagged_pages' => $perPageScan['pages'],
                    'error' => null,
                ]);

                $this->persistFlags($document, $perPageScan);
            });
            $committed = true;

            // The staged preview is adopted by the committed update; only
            // now may the superseded file be deleted. Cleanup is
            // best-effort — a deletion failure must not misreport a
            // successful rescan as failed.
            $adoptedPreviewPath = $stagedPreviewPath;
            $stagedPreviewPath = null;
            if ($adoptedPreviewPath && $previousPreviewPath && $previousPreviewPath !== $adoptedPreviewPath) {
                try {
                    Storage::disk('local')->delete($previousPreviewPath);
                } catch (\Throwable $cleanupError) {
                    Log::warning('Document rescan committed but old preview deletion failed for '.$previousPreviewPath.': '.$cleanupError->getMessage());
                }
            }
        } catch (\Throwable $e) {
            // The in-memory model may carry rolled-back attributes (update()
            // syncs originals before the transaction commits), so compare
            // against the pre-request path and refresh before marking failed.
            if (! $committed && $stagedPreviewPath && $stagedPreviewPath !== $previousPreviewPath) {
                Storage::disk('local')->delete($stagedPreviewPath);
            }

            $document->refresh();

            Log::error('Document rescan failed for document '.$document->id.': '.$e->getMessage());
            $document->update([
                'status' => 'failed',
                'error' => self::EXTRACTION_ERROR,
            ]);

            return redirect()
                ->route('docs.show', $document->id)
                ->with('warning', 'Rescan failed. Try again or contact an administrator.');
        }

        return redirect()
            ->route('docs.show', $document->id)
            ->with('success', 'Document rescanned successfully.');
    }

    /**
     * Stream the private file to the browser (gated by view policy).
     */
    public function download(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'File not found.');
        }

        return response()->streamDownload(function () use ($document) {
            echo Storage::disk('local')->get($document->file_path);
        }, $document->original_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    /**
     * Stream the generated PDF preview (for Word documents) to the browser.
     */
    public function pdfPreview(Request $request, Document $document)
    {
        $this->authorize('view', $document);

        if (! $document->pdf_preview_path || ! Storage::disk('local')->exists($document->pdf_preview_path)) {
            abort(404, 'PDF preview not available.');
        }

        return response()->streamDownload(function () use ($document) {
            echo Storage::disk('local')->get($document->pdf_preview_path);
        }, pathinfo($document->original_name, PATHINFO_FILENAME).'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * Persist the per-flag-word summary rows (aggregated across pages).
     */
    protected function persistFlags(Document $document, array $perPageScan): void
    {
        if ($perPageScan['total'] <= 0) {
            return;
        }

        // Aggregate word → total occurrences across all pages
        $wordTotals = [];
        foreach ($perPageScan['pages'] as $pageData) {
            foreach ($pageData['words'] as $w) {
                $wordTotals[$w['word']] = ($wordTotals[$w['word']] ?? 0) + $w['occurrences'];
            }
        }

        $wordToId = DocumentFlagWord::query()
            ->whereIn('word', array_keys($wordTotals))
            ->pluck('id', 'word');

        $rows = [];
        foreach ($wordTotals as $word => $occurrences) {
            $flagWordId = $wordToId[$word] ?? null;
            if (! $flagWordId) {
                continue;
            }
            $rows[] = [
                'document_id' => $document->id,
                'flag_word_id' => $flagWordId,
                'occurrences' => $occurrences,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (! empty($rows)) {
            DocumentFlag::insert($rows);
        }
    }
}

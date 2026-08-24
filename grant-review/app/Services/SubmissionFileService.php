<?php

namespace App\Services;

use App\Models\Submission;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SubmissionFileService
{
    /**
     * The storage disk for submission PDFs. Points to storage/app/private
     * (outside the public web root, no public URL, no symlink).
     */
    private const DISK = 'local';

    /**
     * Store an uploaded PDF for a submission and return its storage path.
     *
     * The stored filename is always a server-generated UUID — never the
     * original client filename — so nothing derived from user input ever
     * reaches the filesystem path. This forecloses path traversal and
     * filename-injection attacks by construction, not just validation.
     */
    public function store(UploadedFile $file, int $roundId, int $submitterId): string
    {
        $filename = Str::uuid()->toString().'.pdf';
        $directory = "submissions/{$roundId}/{$submitterId}";

        return $file->storeAs($directory, $filename, self::DISK);
    }

    /**
     * Replace the PDF for an existing submission, deleting the old file.
     */
    public function replace(Submission $submission, UploadedFile $file): string
    {
        $oldPath = $submission->pdf_path;
        $submitterId = $submission->submitter_id;

        $newPath = $this->store($file, $submission->round_id, $submitterId);

        if ($oldPath && Storage::disk(self::DISK)->exists($oldPath)) {
            Storage::disk(self::DISK)->delete($oldPath);
        }

        return $newPath;
    }

    /**
     * Stream the submission's PDF as an authenticated download response.
     *
     * Callers MUST authorize the request (e.g. via SubmissionPolicy::view)
     * before calling this — it performs no authorization itself.
     */
    public function download(Submission $submission)
    {
        if (! Storage::disk(self::DISK)->exists($submission->pdf_path)) {
            abort(404);
        }

        return Storage::disk(self::DISK)->response(
            $submission->pdf_path,
            "submission-{$submission->id}.pdf",
            ['Content-Type' => 'application/pdf']
        );
    }

    public function delete(Submission $submission): void
    {
        if ($submission->pdf_path && Storage::disk(self::DISK)->exists($submission->pdf_path)) {
            Storage::disk(self::DISK)->delete($submission->pdf_path);
        }
    }
}

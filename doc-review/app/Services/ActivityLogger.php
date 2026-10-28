<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\User;

class ActivityLogger
{
    /**
     * Append an immutable audit entry. Actor identity and the document
     * filename are snapshotted so the row stays meaningful after the user
     * is renamed/deleted or the document is removed.
     */
    public function record(string $action, User $actor, ?Document $document = null): ActivityLog
    {
        return ActivityLog::create([
            'actor_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => $action,
            'document_id' => $document?->id,
            'document_name' => $document?->original_name,
            'created_at' => now(),
        ]);
    }
}

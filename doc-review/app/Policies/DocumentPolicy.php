<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Any active hub-assigned user may list their documents.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('docs.document.view');
    }

    /**
     * Admins (docs.document.manage) can view any document; owners can view
     * their own.
     */
    public function view(User $user, Document $document): bool
    {
        return $user->isActive()
            && ($user->can('docs.document.manage') || $document->user_id === $user->id);
    }

    /**
     * Any active hub-assigned user may upload documents.
     */
    public function create(User $user): bool
    {
        return $user->can('docs.document.create');
    }

    public function update(User $user, Document $document): bool
    {
        return $user->isActive()
            && ($user->can('docs.document.manage') || $document->user_id === $user->id);
    }

    public function delete(User $user, Document $document): bool
    {
        return $user->isActive()
            && ($user->can('docs.document.manage') || $document->user_id === $user->id);
    }
}

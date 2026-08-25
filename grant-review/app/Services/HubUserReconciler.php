<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Uh\AppHub\Services\HubClient;

class HubUserReconciler
{
    public function reconcile(Request $request, HubClient $hub, HubIdentityService $identities): void
    {
        $actorToken = (string) $request->session()->get(config('hub.actor_token_session_key', 'hub_actor_token'));
        $hubUsers = $hub->listManagedUsers($actorToken);
        $activeSubjects = [];
        $activeEmails = [];

        foreach ($hubUsers as $identity) {
            $identities->resolve($identity);
            $activeSubjects[] = $identity['subject'];
            $activeEmails[] = $identity['email'];
        }

        $archivedIds = User::query()
            ->where('status', '!=', 'disabled')
            ->where(function ($query) use ($activeSubjects, $activeEmails): void {
                $query->where(fn ($linked) => $linked
                    ->whereNotNull('sso_sub')
                    ->whereNotIn('sso_sub', $activeSubjects))
                    ->orWhere(fn ($unlinked) => $unlinked
                        ->whereNull('sso_sub')
                        ->whereNotIn('email', $activeEmails));
            })
            ->pluck('id');

        if ($archivedIds->isNotEmpty()) {
            User::whereKey($archivedIds)->update(['status' => 'disabled']);
            DB::table('sessions')->whereIn('user_id', $archivedIds)->delete();
        }
    }
}

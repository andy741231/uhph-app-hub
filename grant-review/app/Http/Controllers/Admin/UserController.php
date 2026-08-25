<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSubmittersCsvRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Mail\InviteUser;
use App\Models\Round;
use App\Models\RoundInvitation;
use App\Models\User;
use App\Services\HubIdentityService;
use App\Services\HubUserReconciler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Uh\AppHub\Services\HubClient;

class UserController extends Controller
{
    public function index(
        Request $request,
        HubClient $hub,
        HubIdentityService $identities,
        HubUserReconciler $reconciler,
    ): View {
        if (config('hub.enabled') && $request->session()->has(config('hub.actor_token_session_key', 'hub_actor_token'))) {
            $reconciler->reconcile($request, $hub, $identities);
        }
        $showArchived = $request->boolean('archived');
        $search = trim($request->get('search', ''));
        $role = $request->get('role', '');
        $sort = $request->get('sort', 'created_at');
        $direction = $request->get('direction', 'desc');

        $allowedSorts = ['first_name', 'last_name', 'email', 'role', 'department', 'status', 'created_at'];
        if (! in_array($sort, $allowedSorts)) {
            $sort = 'created_at';
        }
        if (! in_array($direction, ['asc', 'desc'])) {
            $direction = 'desc';
        }

        $query = User::query();
        if (config('hub.enabled')) {
            $query->where('status', $showArchived ? 'disabled' : 'active');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if (in_array($role, ['admin', 'submitter', 'reviewer'])) {
            $query->where('role', $role);
        }

        $users = $query->orderBy($sort, $direction)->paginate(50)->withQueryString();
        $rounds = Round::orderBy('name')->get();

        return view('admin.users.index', compact('users', 'rounds', 'search', 'role', 'sort', 'direction', 'showArchived'));
    }

    public function create(): View
    {
        $rounds = Round::where('status', '!=', 'closed')->orderBy('name')->get();

        return view('admin.users.create', compact('rounds'));
    }

    public function store(
        StoreUserRequest $request,
        HubClient $hub,
        HubIdentityService $identities,
    ): RedirectResponse {
        if (config('hub.enabled')) {
            $identity = $hub->manageUser(
                (string) $request->session()->get(config('hub.actor_token_session_key', 'hub_actor_token')),
                [
                    'name' => trim($request->first_name.' '.$request->last_name),
                    'email' => $request->email,
                    'role' => $request->role,
                ],
            );
            $user = $identities->resolve($identity);
            $this->syncRoundInvitations($user, $request->input('round_ids', []));
            $message = $identity['created']
                ? "User {$user->email} created in UHPH App Hub and assigned to Grant Review."
                : "Existing UHPH App Hub user {$user->email} assigned to Grant Review.";
            if ($identity['created'] && ! $identity['invitation_sent']) {
                $message .= ' The UHPH App Hub invitation could not be sent.';
            }

            return redirect()->route('admin.users.index')->with('status', $message);
        }

        $user = User::create([
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'role' => $request->role,
            'status' => 'invited',
            'invite_token_hash' => hash('sha256', $token = Str::random(64)),
            'invite_expires_at' => now()->addDays(7),
        ]);

        // Invite to selected rounds
        if ($request->has('round_ids')) {
            foreach ($request->round_ids as $roundId) {
                RoundInvitation::create([
                    'round_id' => $roundId,
                    'user_id' => $user->id,
                ]);
            }
        }

        // Send invite email (Phase 1-1 implementation uses raw SMTP)
        $this->sendInviteEmail($user, $token);

        return redirect()->route('admin.users.index')->with('status', "User {$user->email} invited.");
    }

    public function import(ImportSubmittersCsvRequest $request): RedirectResponse
    {
        $roundId = $request->round_id;
        $handle = fopen($request->file('csv')->path(), 'r');
        $header = fgetcsv($handle);
        $count = 0;
        $invalidEmails = [];
        $allowedDomains = ['@uh.edu', '@central.uh.edu', '@cougarnet.uh.edu'];

        while (($row = fgetcsv($handle)) !== false) {
            $data = array_combine($header, $row);
            $data['email'] = strtolower(trim($data['email']));

            // Validate UH email domain
            $emailDomain = $data['email'];
            $validDomain = false;
            foreach ($allowedDomains as $domain) {
                if (str_ends_with($emailDomain, $domain)) {
                    $validDomain = true;
                    break;
                }
            }
            if (! $validDomain) {
                $invalidEmails[] = $data['email'];

                continue;
            }

            $token = Str::random(64);

            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'] ?? '',
                    'last_name' => $data['last_name'] ?? '',
                    'role' => 'submitter',
                    'status' => 'invited',
                    'invite_token_hash' => hash('sha256', $token),
                    'invite_expires_at' => now()->addDays(7),
                ]
            );

            RoundInvitation::firstOrCreate([
                'round_id' => $roundId,
                'user_id' => $user->id,
            ]);

            // Re-generate token for existing users
            if ($user->wasRecentlyCreated) {
                $this->sendInviteEmail($user, $token);
            }

            $count++;
        }

        fclose($handle);

        $message = "$count submitters imported and invited.";
        if ($invalidEmails) {
            $message .= ' Skipped '.count($invalidEmails).' non-UH email(s): '.implode(', ', $invalidEmails);
        }

        return redirect()->route('admin.users.index')->with('status', $message);
    }

    public function resendInvite(User $user): RedirectResponse
    {
        $token = Str::random(64);
        $user->update([
            'invite_token_hash' => hash('sha256', $token),
            'invite_expires_at' => now()->addDays(7),
        ]);

        $this->sendInviteEmail($user, $token);

        return back()->with('status', "Invite resent to {$user->email}.");
    }

    public function edit(User $user): View
    {
        $rounds = Round::orderBy('name')->get();
        $invitedRoundIds = $user->roundsInvitedTo()->pluck('rounds.id')->toArray();

        return view('admin.users.edit', compact('user', 'rounds', 'invitedRoundIds'));
    }

    public function show(User $user): View
    {
        $rounds = $user->roundsInvitedTo()->orderBy('name')->get();
        $submissions = $user->submissions()->with('round')->latest()->get();
        $reviewAssignments = $user->reviewAssignments()->with('submission.round')->orderByDesc('assigned_at')->get();

        return view('admin.users.show', compact('user', 'rounds', 'submissions', 'reviewAssignments'));
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        HubClient $hub,
        HubIdentityService $identities,
    ): RedirectResponse {
        $data = $request->validated();

        if (config('hub.enabled')) {
            $identity = $hub->manageUser(
                (string) $request->session()->get(config('hub.actor_token_session_key', 'hub_actor_token')),
                [
                    'subject' => $user->sso_sub,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $data['role'],
                ],
            );
            $user = $identities->resolve($identity);
            $data['email'] = $identity['email'];
            $data['role'] = $identity['role'];
            $data['status'] = 'active';
        }

        // Sync round invitations if checkboxes were submitted
        if (array_key_exists('round_ids', $data)) {
            $currentIds = $user->roundsInvitedTo()->pluck('rounds.id')->toArray();
            $newIds = $data['round_ids'] ?? [];

            RoundInvitation::where('user_id', $user->id)
                ->whereIn('round_id', array_diff($currentIds, $newIds))
                ->delete();

            foreach (array_diff($newIds, $currentIds) as $roundId) {
                RoundInvitation::firstOrCreate([
                    'round_id' => $roundId,
                    'user_id' => $user->id,
                ]);
            }
        }

        unset($data['round_ids']);
        $user->update($data);

        return redirect()->route('admin.users.index')
            ->with('status', "User {$user->full_name} updated.");
    }

    public function revoke(Request $request, User $user, HubClient $hub): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'You cannot revoke your own Grant Review access.']);
        }
        abort_unless(config('hub.enabled') && is_string($user->sso_sub), 404);
        $hub->revokeManagedUser(
            (string) $request->session()->get(config('hub.actor_token_session_key', 'hub_actor_token')),
            $user->sso_sub,
        );
        $user->update(['status' => 'disabled']);
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return redirect()->route('admin.users.index')
            ->with('status', "Grant Review access revoked for {$user->full_name}. Historical records were preserved.");
    }

    public function restore(
        Request $request,
        User $user,
        HubClient $hub,
        HubIdentityService $identities,
    ): RedirectResponse {
        abort_unless(config('hub.enabled') && is_string($user->sso_sub), 404);
        abort_if($user->status === 'active', 409, 'User is already active.');

        $identity = $hub->manageUser(
            (string) $request->session()->get(config('hub.actor_token_session_key', 'hub_actor_token')),
            [
                'name' => $user->full_name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        );
        $identities->restore($user, $identity);

        return redirect()->route('admin.users.index')
            ->with('status', "Grant Review access restored for {$user->full_name}.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        $name = $user->full_name;
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('status', "User {$name} deleted.");
    }

    private function syncRoundInvitations(User $user, array $roundIds): void
    {
        $currentIds = $user->roundsInvitedTo()->pluck('rounds.id')->toArray();
        RoundInvitation::where('user_id', $user->id)
            ->whereIn('round_id', array_diff($currentIds, $roundIds))
            ->delete();

        foreach (array_diff($roundIds, $currentIds) as $roundId) {
            RoundInvitation::firstOrCreate([
                'round_id' => $roundId,
                'user_id' => $user->id,
            ]);
        }
    }

    private function sendInviteEmail(User $user, string $token): void
    {
        $url = url("/set-password?token={$token}&email=".urlencode($user->email));

        Mail::to($user->email)
            ->send(new InviteUser($url, $user->full_name));
    }
}

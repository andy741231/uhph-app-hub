<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeEmptyArchivedUsers extends Command
{
    protected $signature = 'users:purge-archived
                            {--dry-run : List candidates without deleting}
                            {--force : Skip confirmation prompt}
                            {--include-invited : Also purge users whose only records are round invitations}';

    protected $description = 'Permanently delete archived (disabled) users with no submissions, reviews, decisions, or COI declarations';

    public function handle(): int
    {
        $candidates = User::where('status', 'disabled')
            ->where(function ($query): void {
                $this->addDoesntHave($query, 'submissions', 'submitter_id');
                $this->addDoesntHave($query, 'review_assignments', 'reviewer_id');
                $this->addDoesntHave($query, 'decisions', 'decided_by');
                $this->addDoesntHave($query, 'conflict_of_interest_declarations', 'reviewer_id');
                if (! $this->option('include-invited')) {
                    $this->addDoesntHave($query, 'round_invitations', 'user_id');
                }
            })
            ->orderBy('email')
            ->get(['id', 'email', 'first_name', 'last_name', 'sso_sub']);

        if ($candidates->isEmpty()) {
            $this->info('No empty archived users found.');

            return self::SUCCESS;
        }

        $this->info("Found {$candidates->count()} empty archived user(s):");
        $this->table(
            ['ID', 'Email', 'Name', 'SSO Sub'],
            $candidates->map(fn (User $user): array => [
                $user->id,
                $user->email,
                $user->full_name,
                $user->sso_sub ?: '(none)',
            ]),
        );

        if ($this->option('dry-run')) {
            $this->info('Dry run — no records deleted.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Permanently delete these archived users?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $ids = $candidates->pluck('id');
        DB::table('sessions')->whereIn('user_id', $ids)->delete();
        if ($this->option('include-invited') && Schema::hasTable('round_invitations')) {
            DB::table('round_invitations')->whereIn('user_id', $ids)->delete();
        }
        $deleted = User::whereKey($ids)->delete();

        $this->info("Deleted {$deleted} archived user(s).");

        return self::SUCCESS;
    }

    private function addDoesntHave($query, string $table, string $foreignKey): void
    {
        if (Schema::hasTable($table)) {
            $query->whereNotExists(function ($sub) use ($table, $foreignKey): void {
                $sub->select(DB::raw(1))
                    ->from($table)
                    ->whereColumn("{$table}.{$foreignKey}", 'users.id');
            });
        }
    }
}

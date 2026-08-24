<?php

namespace Database\Seeders;

use App\Models\Decision;
use App\Models\Review;
use App\Models\ReviewAssignment;
use App\Models\Round;
use App\Models\RoundInvitation;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // First admin (seed manually — login with this account to provision everyone else)
        User::create([
            'email' => 'admin@uh.edu',
            'password_hash' => Hash::make('changeme123'),
            'first_name' => 'Grants',
            'last_name' => 'Admin',
            'department' => 'Office of Research',
            'role' => 'admin',
            'status' => 'active',
        ]);

        // Test submitters
        $submitters = User::factory()->count(5)->create([
            'role' => 'submitter',
            'status' => 'active',
            'password_hash' => Hash::make('password'),
        ]);

        // Test reviewers
        $reviewers = User::factory()->count(3)->create([
            'role' => 'reviewer',
            'status' => 'active',
            'password_hash' => Hash::make('password'),
        ]);

        // Test round
        $round = Round::create([
            'name' => 'Spring 2027',
            'opens_at' => now()->subDays(30),
            'deadline_at' => now()->addDays(60),
            'status' => 'open',
        ]);

        // Invite all submitters to the round
        foreach ($submitters as $submitter) {
            RoundInvitation::create([
                'round_id' => $round->id,
                'user_id' => $submitter->id,
            ]);
        }

        // Test submissions from first 3 submitters
        $submissions = collect();
        foreach ($submitters->take(3) as $submitter) {
            $submissions->push(Submission::create([
                'round_id' => $round->id,
                'submitter_id' => $submitter->id,
                'title' => 'Research Proposal: '.$submitter->full_name,
                'abstract' => 'This is a test abstract for the proposal.',
                'amount_requested' => 50000.00,
                'pdf_path' => 'submissions/test_proposal.pdf',
                'status' => 'submitted',
                'submitted_at' => now()->subDays(5),
            ]));
        }

        // Assign reviewers to first submission
        $firstSubmission = $submissions->first();
        foreach ($reviewers->take(2) as $reviewer) {
            $assignment = ReviewAssignment::create([
                'submission_id' => $firstSubmission->id,
                'reviewer_id' => $reviewer->id,
            ]);

            Review::create([
                'review_assignment_id' => $assignment->id,
                'score' => 8.50,
                'comments' => 'Strong proposal with clear methodology.',
                'submitted_at' => now()->subDay(),
            ]);
        }

        // Decision on first submission
        Decision::create([
            'submission_id' => $firstSubmission->id,
            'outcome' => 'funded',
            'amount_awarded' => 45000.00,
            'decided_by' => 1, // admin
        ]);
    }
}

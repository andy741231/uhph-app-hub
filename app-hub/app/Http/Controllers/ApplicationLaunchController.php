<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationLaunchAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApplicationLaunchController extends Controller
{
    public function __invoke(Request $request, Application $application): RedirectResponse|Response
    {
        $assignment = $request->user()
            ->applications()
            ->where('applications.id', $application->id)
            ->first();
        $reason = match (true) {
            ! $application->enabled => 'disabled',
            ! $assignment => 'not_assigned',
            ! $application->hasSafePath() => 'invalid_path',
            default => null,
        };
        $this->audit($request, $application, $reason === null, $reason);

        if ($reason !== null) {
            return response()->view('errors.access-denied', [
                'application' => $application,
            ], 403);
        }

        return redirect()->away($application->path);
    }

    private function audit(Request $request, Application $application, bool $succeeded, ?string $reason): void
    {
        ApplicationLaunchAudit::create([
            'user_id' => $request->user()->id,
            'application_id' => $application->id,
            'succeeded' => $succeeded,
            'failure_reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportUsersRequest;
use App\Models\Application;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserImportController extends Controller
{
    private const HEADERS = ['name', 'email', 'application', 'role'];

    private const ALLOWED_EMAIL_DOMAINS = ['uh.edu', 'central.uh.edu', 'cougarnet.uh.edu'];

    public function create(): View
    {
        return view('admin.users.import', [
            'applications' => Application::query()->where('enabled', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function template(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, self::HEADERS, ',', '"', '');
            fputcsv($output, ['Jane Submitter', 'jsubmitter@uh.edu', 'grant-review', 'submitter'], ',', '"', '');
            fputcsv($output, ['Robert Reviewer', 'rreviewer@cougarnet.uh.edu', 'grant-review', 'reviewer'], ',', '"', '');
            fputcsv($output, ['Faith Editor', 'feditor@central.uh.edu', 'flipbook', 'admin'], ',', '"', '');
            fclose($output);
        }, 'app-hub-user-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public function store(ImportUsersRequest $request): RedirectResponse
    {
        $rows = $this->parse($request->file('csv')->path());
        $applications = Application::query()->get()->keyBy('key');
        $validated = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $name = trim($row['name']);
            $email = Str::lower(trim($row['email']));
            $applicationKey = Str::lower(trim($row['application']));
            $role = Str::lower(trim($row['role']));
            $application = $applications->get($applicationKey);
            $domain = Str::afterLast($email, '@');
            $rowKey = $email.'|'.$applicationKey;

            if ($name === '' || mb_strlen($name) > 255) {
                $this->fail($line, 'name', 'Provide a name of no more than 255 characters.');
            }
            if (! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
                $this->fail($line, 'email', 'Provide a valid email address.');
            }
            if (! in_array($domain, self::ALLOWED_EMAIL_DOMAINS, true)) {
                $this->fail($line, 'email', 'Use an @uh.edu, @central.uh.edu, or @cougarnet.uh.edu address.');
            }
            if (! $application || ! $application->enabled) {
                $this->fail($line, 'application', 'Select an enabled application key.');
            }
            if (! in_array($role, $application->roles ?? [], true)) {
                $this->fail($line, 'role', "Select a role supported by {$application->name}.");
            }
            if (isset($seen[$rowKey])) {
                $this->fail($line, 'email', 'The same user and application appear more than once.');
            }

            $seen[$rowKey] = true;
            $validated[] = compact('name', 'email', 'application', 'role');
        }

        $newUsers = [];
        $existing = 0;
        DB::transaction(function () use ($validated, $request, &$newUsers, &$existing): void {
            foreach ($validated as $row) {
                $user = User::where('email', $row['email'])->lockForUpdate()->first();

                if (! $user) {
                    $user = User::create([
                        'name' => $row['name'],
                        'email' => $row['email'],
                        'password' => Str::random(64),
                        'email_verified_at' => now(),
                        'status' => User::STATUS_ACTIVE,
                        'is_admin' => false,
                    ]);
                    $newUsers[$user->id] = $user;
                } else {
                    $existing++;
                }

                $user->applications()->syncWithoutDetaching([
                    $row['application']->id => [
                        'role' => $row['role'],
                        'granted_by' => $request->user()->id,
                        'granted_at' => now(),
                    ],
                ]);
            }
        });

        $inviteFailures = 0;
        foreach ($newUsers as $user) {
            try {
                if (Password::sendResetLink(['email' => $user->email]) !== Password::RESET_LINK_SENT) {
                    $inviteFailures++;
                }
            } catch (\Throwable) {
                $inviteFailures++;
            }
        }
        $created = count($newUsers);
        $assigned = count($validated);
        $message = "Imported {$assigned} application assignment(s): {$created} new user(s), {$existing} existing user row(s).";

        if ($inviteFailures > 0) {
            $message .= " {$inviteFailures} set-password invitation(s) could not be sent.";
        } elseif ($created > 0) {
            $message .= ' Set-password invitations were sent to all new users.';
        }

        return redirect()->route('admin.users.import.create')->with('status', $message);
    }

    private function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        $headers = fgetcsv($handle, null, ',', '"', '');

        if (is_array($headers) && isset($headers[0])) {
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
            $headers = array_map(fn (string $header): string => Str::lower(trim($header)), $headers);
        }
        if ($headers !== self::HEADERS) {
            fclose($handle);
            throw ValidationException::withMessages([
                'csv' => 'The header must be exactly: '.implode(',', self::HEADERS),
            ]);
        }

        $rows = [];
        while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
            if (count($values) === 1 && trim((string) $values[0]) === '') {
                continue;
            }
            if (count($values) !== count(self::HEADERS)) {
                fclose($handle);
                $this->fail(count($rows) + 2, 'csv', 'Each row must contain exactly four columns.');
            }
            $rows[] = array_combine(self::HEADERS, $values);
            if (count($rows) > 1000) {
                fclose($handle);
                throw ValidationException::withMessages(['csv' => 'Import no more than 1,000 rows at a time.']);
            }
        }
        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages(['csv' => 'The CSV does not contain any user rows.']);
        }

        return $rows;
    }

    private function fail(int $line, string $field, string $message): never
    {
        throw ValidationException::withMessages([
            'csv' => "Row {$line}, {$field}: {$message}",
        ]);
    }
}

<?php

use App\Models\User;
use App\Services\InvitationSender;
use Illuminate\Contracts\Console\Kernel;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__.'/../vendor/autoload.php';

function requireInvitationCheck(bool $condition, string $reason): void
{
    if (! $condition) {
        throw new RuntimeException($reason);
    }
}

try {
    $options = getopt('', ['provisioned:', 'receipts:', 'send']);
    requireInvitationCheck(isset($options['provisioned'], $options['receipts']), 'Provisioned scope and receipt directory required.');
    $scope = json_decode(file_get_contents($options['provisioned']), true, 512, JSON_THROW_ON_ERROR);
    requireInvitationCheck($scope['committed'] === true && count($scope['created']) === 3, 'Expected three committed invitees.');
    $receiptDirectory = $options['receipts'];
    requireInvitationCheck(is_dir($receiptDirectory), 'Restricted receipt directory must already exist.');
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    requireInvitationCheck(app()->isProduction() && config('app.debug') === false
        && config('app.url') === 'https://uhph.uh.edu/apps' && config('hub.login_mode') === 'local',
        'Unexpected production invitation environment.');
    requireInvitationCheck(config('mail.default') === 'smtp', 'Real SMTP mailer is required; log mail is not delivery.');
    $grantRoot = dirname(__DIR__, 2).'/grant-review';
    if (is_file($grantRoot.'/bootstrap/cache/config.php')) {
        $grantConfig = require $grantRoot.'/bootstrap/cache/config.php';
        $enabled = $grantConfig['hub']['enabled'];
    } else {
        $env = Dotenv\Dotenv::createArrayBacked($grantRoot)->load();
        $enabled = filter_var($env['HUB_SSO_ENABLED'] ?? false, FILTER_VALIDATE_BOOL);
    }
    requireInvitationCheck($enabled === true, 'Enable and verify Grant Review integration before sending invitations.');
    $targets = [];
    $seen = [];
    foreach ($scope['created'] as $item) {
        requireInvitationCheck(! isset($seen[$item['hub_id']]), 'Duplicate invitation scope.');
        $seen[$item['hub_id']] = true;
        $user = User::query()->where('id', $item['hub_id'])->where('email', $item['email'])->first();
        requireInvitationCheck($user !== null && $user->isActive() && ! $user->is_admin,
            'Approved invitee identity is missing or changed.');
        $applications = $user->applications()->get();
        requireInvitationCheck($applications->count() === 1
            && $applications->first()->key === 'grant-review'
            && $applications->first()->enabled
            && $applications->first()->pivot->role === 'submitter',
            'Approved invitee assignment changed.');
        $receipt = $receiptDirectory.'/invitation-'.$user->id.'.json';
        if (is_file($receipt)) {
            $previous = json_decode(file_get_contents($receipt), true, 512, JSON_THROW_ON_ERROR);
            requireInvitationCheck($previous['hub_id'] === $user->id
                && $previous['email'] === $user->email && $previous['status'] === 'smtp_accepted',
                'Prior delivery is ambiguous; do not automatically resend.');
            $targets[] = ['user' => $user, 'applications' => $applications, 'receipt' => $receipt, 'already_sent' => true];
        } else {
            requireInvitationCheck($user->password === null, 'Invitee already has a password; review instead of resetting.');
            $targets[] = ['user' => $user, 'applications' => $applications, 'receipt' => $receipt, 'already_sent' => false];
        }
    }
    if (! isset($options['send'])) {
        echo "DRY RUN: three approved invitation recipients verified. No tokens created or emails sent.\n";
        exit(0);
    }
    $results = [];
    foreach ($targets as $target) {
        $user = $target['user'];
        if ($target['already_sent']) {
            $results[] = ['email' => $user->email, 'status' => 'already_smtp_accepted'];

            continue;
        }
        // Journal before sending: an ambiguous failure must never trigger an automatic duplicate.
        $receipt = ['hub_id' => $user->id, 'email' => $user->email, 'status' => 'sending', 'started_at_utc' => gmdate('c')];
        $handle = fopen($target['receipt'], 'x');
        requireInvitationCheck($handle !== false, 'Cannot exclusively create delivery receipt.');
        try {
            $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            requireInvitationCheck(fwrite($handle, $json) === strlen($json) && fflush($handle), 'Delivery journal write failed.');
        } finally {
            fclose($handle);
        }
        $accepted = app(InvitationSender::class)->send($user, $target['applications']);
        $receipt['status'] = $accepted ? 'smtp_accepted' : 'delivery_result_ambiguous';
        $receipt['completed_at_utc'] = gmdate('c');
        $json = json_encode($receipt, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        requireInvitationCheck(file_put_contents($target['receipt'], $json, LOCK_EX) === strlen($json),
            'Delivery receipt update failed; do not resend without review.');
        $results[] = ['email' => $user->email, 'status' => $receipt['status']];
        requireInvitationCheck($accepted, 'Invitation service reported failure; prior receipts require review before retry.');
    }
    echo json_encode([
        'completed_at_utc' => gmdate('c'),
        'results' => $results,
        'note' => 'SMTP acceptance is not proof of inbox delivery. No passwords or setup tokens are printed.',
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $exception) {
    $reason = get_class($exception) === RuntimeException::class ? $exception->getMessage() : get_class($exception);
    fwrite(STDERR, 'Invitation batch stopped: '.$reason.' Check private delivery receipts before any retry.'.PHP_EOL);
    exit(1);
}

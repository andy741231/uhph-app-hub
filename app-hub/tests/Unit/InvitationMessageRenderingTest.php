<?php

namespace Tests\Unit;

use App\Models\User;
use App\Notifications\HubAccessInvitation;
use Tests\TestCase;

class InvitationMessageRenderingTest extends TestCase
{
    public function test_invitation_message_markdown_is_rendered_in_the_email(): void
    {
        $html = $this->renderInvitation('**Important:** complete your [profile](https://uhph.uh.edu/apps/grant-review) today.');

        $this->assertMatchesRegularExpression('#<strong[^>]*>Important:</strong>#', $html);
        $this->assertStringContainsString('href="https://uhph.uh.edu/apps/grant-review"', $html);
    }

    public function test_invitation_message_html_is_escaped_in_the_email(): void
    {
        $html = $this->renderInvitation('<script>alert("x")</script>');

        $this->assertStringNotContainsString('<script>alert("x")</script>', $html);
    }

    public function test_invitation_message_unsafe_links_are_not_rendered(): void
    {
        $html = $this->renderInvitation('[click](javascript:alert(1))');

        $this->assertStringNotContainsString('href="javascript:alert(1)"', $html);
    }

    private function renderInvitation(string $message): string
    {
        $user = new User(['name' => 'Jane Doe', 'email' => 'jane@uh.edu']);
        $notification = new HubAccessInvitation([[
            'key' => 'grant-review',
            'name' => 'Grant Review',
            'invitation_message' => $message,
        ]]);

        return $notification->toMail($user)->render();
    }
}

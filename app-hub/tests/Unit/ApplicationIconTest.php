<?php

namespace Tests\Unit;

use App\Models\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationIconTest extends TestCase
{
    use RefreshDatabase;

    private function application(array $attributes = []): Application
    {
        return new Application($attributes);
    }

    public function test_icon_url_points_to_the_application_favicon(): void
    {
        $this->assertSame('/apps/grant-review/favicon.ico', $this->application([
            'key' => 'grant-review',
            'path' => '/apps/grant-review',
        ])->iconUrl());
        $this->assertSame('/apps/flipbook/favicon.ico', $this->application([
            'path' => '/apps/flipbook/',
        ])->iconUrl());
    }

    public function test_icon_initial_is_derived_from_the_application_name(): void
    {
        $this->assertSame('GR', $this->application(['name' => 'Grant Review'])->iconInitial());
        $this->assertSame('F', $this->application(['name' => 'Flipbook'])->iconInitial());
        $this->assertSame('CP', $this->application(['name' => 'Community- Partners'])->iconInitial());
    }

    public function test_icon_color_class_is_deterministic_and_stable(): void
    {
        $application = $this->application(['key' => 'grant-review']);
        $expected = 'icon-'.(crc32('grant-review') % 8);

        $this->assertSame($expected, $application->iconColorClass());
        $this->assertSame($expected, $application->iconColorClass());
    }
}

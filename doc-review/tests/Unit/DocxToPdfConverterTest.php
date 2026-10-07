<?php

namespace Tests\Unit;

use App\Services\DocxToPdfConverter;
use PHPUnit\Framework\TestCase;

class DocxToPdfConverterTest extends TestCase
{
    /**
     * The Windows timeout kill must be scoped to the owned process tree
     * (/PID <pid> /T /F) — never taskkill /IM, which would kill every
     * soffice.exe on the host including parallel conversions.
     */
    public function test_owned_process_kill_command_is_pid_scoped(): void
    {
        $converter = new DocxToPdfConverter;
        $method = new \ReflectionMethod($converter, 'ownedProcessKillCommand');
        $command = $method->invoke($converter, 4321);

        $this->assertSame('taskkill /PID 4321 /T /F 2>nul', $command);
        $this->assertStringNotContainsString('/IM', $command);
        $this->assertStringNotContainsString('soffice', $command);
    }
}

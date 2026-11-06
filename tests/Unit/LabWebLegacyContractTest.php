<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LabWebLegacyContractTest extends TestCase
{
    public function testWebRuntimeIsOwnedByUnifiedFrankenPhp(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertFileDoesNotExist($root.'/scripts/start.cmd');
        self::assertFileExists($root.'/public/router.php');

        // The LAB entry point remains; Orchestrator owns the lifecycle.
        // LAB web must not depend on the retired standalone bootstrap script.
        self::assertStringNotContainsString(
            'scripts/start.ps1',
            (string) file_get_contents($root.'/src/Service/GlobalQueueProfileBridge.php')
        );
        self::assertFileExists($root.'/public/index.php');

        $ignore = (string) file_get_contents($root.'/.gitignore');
        self::assertStringContainsString('.phpunit.result.cache', $ignore);
    }
}

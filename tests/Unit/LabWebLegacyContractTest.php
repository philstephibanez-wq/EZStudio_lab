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

        // The legacy PHP router and science environment remain together until audited.
        self::assertFileExists($root.'/scripts/start.ps1');
        self::assertFileExists($root.'/public/index.php');

        $ignore = (string) file_get_contents($root.'/.gitignore');
        self::assertStringContainsString('.phpunit.result.cache', $ignore);
    }
}

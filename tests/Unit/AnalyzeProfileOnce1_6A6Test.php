<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AnalyzeProfileOnce1_6A6Test extends TestCase
{
    public function testOneShotUsesExistingScientificServicesAndRequiresExplicitApply(): void
    {
        $src = file_get_contents(__DIR__.'/../../src/Command/AnalyzeProfileOnceCommand.php');
        self::assertIsString($src);
        self::assertStringContainsString('ProfileExecutionService $execution', $src);
        self::assertStringContainsString('GlobalQueueProfileBridge $bridge', $src);
        self::assertStringContainsString("'ezstudio-profile-modules'", $src);
        self::assertStringContainsString("'ezstudio.profile.v1'", $src);
        self::assertStringContainsString('if (!$input->getOption(\'apply\'))', $src);
        self::assertSame(1, substr_count($src, '$this->bridge->runOne('));
        self::assertSame(1, substr_count($src, '$this->execution->queue('));
        self::assertStringNotContainsString('claimNextAnalysisJob', $src);
    }
}

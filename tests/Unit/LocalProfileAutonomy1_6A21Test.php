<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LocalProfileAutonomy1_6A21Test extends TestCase
{
    public function testLocalWorkerIsFencedToLocalOwnedProfileJobs(): void
    {
        $path = dirname(__DIR__, 2).'/src/Command/LocalProfileWorkerCommand.php';
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString("dispatch_owner='lab'", $source);
        self::assertStringContainsString('LOCAL_PROFILE_OWNER_OR_STATE_REFUSED', $source);
        self::assertStringContainsString('LOCAL_PROFILE_GLOBAL_LEASE_STILL_RUNNING', $source);
        self::assertStringContainsString('LOCAL_PROFILE_GLOBAL_QUEUE_NOT_OBSERVABLE', $source);
        self::assertStringContainsString('LOCAL_PROFILE_ELIGIBLE_READ_ONLY', $source);
        self::assertStringContainsString('LOCAL_PROFILE_SCIENCE_START', $source);
        self::assertStringNotContainsString('UPDATE analysis_jobs SET dispatch_owner', $source);
        self::assertStringNotContainsString('analysis_job:204', $source);
        self::assertStringNotContainsString('analysis_job:205', $source);
    }
}

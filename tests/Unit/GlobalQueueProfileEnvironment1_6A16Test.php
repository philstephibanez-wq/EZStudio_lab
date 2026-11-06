<?php

declare(strict_types=1);
namespace App\Tests\Unit;
use App\Service\GlobalQueueProfileEnvironment;
use PHPUnit\Framework\TestCase;
final class GlobalQueueProfileEnvironment1_6A16Test extends TestCase {
    public function testDefaultsNoSecretsAndPreservedOverrides(): void {
        $env = GlobalQueueProfileEnvironment::build('H:/EZStudio_lab', [
            'EZSTUDIO_PROFILE_MODELS' => 'X:/test-models',
            'EZS_QUEUE_EXECUTOR_TOKEN' => 'sensitive',
            'EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY' => 'sensitive',
            'EZS_QUEUE_LAB_TOKEN' => 'sensitive',
        ]);
        self::assertSame('X:/test-models', $env['EZSTUDIO_PROFILE_MODELS']);
        self::assertSame('H:/EZStudio_lab/var/runtime/deps/profile-r3b12', $env['EZSTUDIO_PROFILE_DEP_ROOT']);
        self::assertSame('H:/EZStudio_lab/var/logs', $env['EZSTUDIO_LOG_ROOT']);
        self::assertSame('lab', $env['EZS_TARGET']);
        self::assertArrayNotHasKey('EZS_QUEUE_EXECUTOR_TOKEN', $env);
        self::assertArrayNotHasKey('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY', $env);
        self::assertArrayNotHasKey('EZS_QUEUE_LAB_TOKEN', $env);
    }
}

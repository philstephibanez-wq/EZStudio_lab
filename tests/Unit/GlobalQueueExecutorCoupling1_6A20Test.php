<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\GlobalQueueAdmission;
use PHPUnit\Framework\TestCase;

final class GlobalQueueExecutorCoupling1_6A20Test extends TestCase
{
    public function testExecutorTokenIsExplicitlyAllowlisted(): void
    {
        $method = new \ReflectionMethod(GlobalQueueAdmission::class, 'localSetting');
        self::assertTrue($method->isPublic());
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Service/GlobalQueueAdmission.php');
        self::assertIsString($source);
        self::assertStringContainsString("'EZS_QUEUE_EXECUTOR_TOKEN'", $source);
    }

    public function testNoEngineOrWorkerIsAdded(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Command/ExecuteProfileGlobalQueueCommand.php');
        self::assertIsString($source);
        self::assertStringContainsString('GlobalQueueProfileBridge', $source);
        self::assertStringContainsString('runOne(', $source);
        self::assertStringNotContainsString('proc_open(', $source);
    }
}

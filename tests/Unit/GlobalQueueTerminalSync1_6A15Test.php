<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GlobalQueueTerminalSync1_6A15Test extends TestCase
{
    public function testGlobalOwnerFenceRetainedWithReceiptGate(): void
    {
        $db = file_get_contents(__DIR__.'/../../src/Service/Database.php');
        self::assertIsString($db);
        self::assertStringContainsString('global_queue_terminal_receipts', $db);
        self::assertStringContainsString('global_queue_dispatch_fenced', $db);
        self::assertStringContainsString('reconcileGlobalTerminal(', $db);
        $service = file_get_contents(__DIR__.'/../../src/Service/GlobalQueueTerminalSync.php');
        self::assertIsString($service);
        self::assertStringContainsString('active_executor_stop_attestation_required', $service);
        self::assertStringContainsString('global_job_identity_mismatch', $service);
    }
}

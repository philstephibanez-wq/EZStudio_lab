<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ProfileOnceNoQueue1_6A26Test extends TestCase
{
    public function testNoProfileQueueExecutorOrGlobalIntegrationRemains(): void
    {
        $root = dirname(__DIR__, 2);
        self::assertFileExists($root.'/src/Command/ProfileOnceCommand.php');
        self::assertFileDoesNotExist($root.'/src/Service/GlobalQueueAdmission.php');
        self::assertFileDoesNotExist($root.'/src/Service/GlobalQueueProfileBridge.php');
        self::assertFileDoesNotExist($root.'/src/Messenger/ProfileOutboxTransport.php');
        self::assertFileDoesNotExist($root.'/src/MessageHandler/ProfileQueueJobHandler.php');
        self::assertFileDoesNotExist($root.'/config/packages/messenger.yaml');
    }
    public function testDirectProfileStartsRunningAndPersistsThroughHistoricService(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root.'/src/Service/ProfileExecutionService.php');
        $cli = file_get_contents($root.'/src/Command/ProfileOnceCommand.php');
        self::assertIsString($service);
        self::assertIsString($cli);
        self::assertStringContainsString('function beginOneShot(', $service);
        self::assertStringNotContainsString('function queue(', $service);
        self::assertStringContainsString("'running', 1", $service);
        self::assertStringContainsString("'lab'", $service);
        self::assertStringContainsString('$this->profile->completeFromJob($jobId)', $cli);
        self::assertStringContainsString('$this->database->completeAnalysisJob($jobId)', $cli);
        self::assertStringContainsString("\$context['target'] = 'lab'", $cli);
        self::assertStringNotContainsString('GlobalQueue', $cli);
        self::assertStringNotContainsString('messenger:', $cli);
    }
}

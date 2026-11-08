<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Service\LocalProfileQueue;

final class LocalProfileQueue1_6A32Test extends TestCase
{
    public function testSinglePersistentQueueOrdersBothJobKinds(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE analysis_jobs(id INTEGER PRIMARY KEY,kind TEXT NOT NULL,scientific_run_id INTEGER,status TEXT NOT NULL,dispatch_owner TEXT NOT NULL,created_at TEXT NOT NULL)');
        $add = $pdo->prepare('INSERT INTO analysis_jobs(id,kind,scientific_run_id,status,dispatch_owner,created_at) VALUES(?,?,?,?,?,?)');
        $add->execute([1,'profile',11,'queued','lab','2026-11-08T10:00:00Z']);
        $add->execute([2,'assistant.profile.review',12,'queued','lab','2026-11-08T10:00:01Z']);
        $this->assertSame(1,(int)$pdo->query(LocalProfileQueue::headSql())->fetch(PDO::FETCH_ASSOC)['id']);
        $pdo->exec("UPDATE analysis_jobs SET status='completed' WHERE id=1");
        $this->assertSame(2,(int)$pdo->query(LocalProfileQueue::headSql())->fetch(PDO::FETCH_ASSOC)['id']);
    }

    public function testRunningAssistantBlocksScientificClaimAndConversely(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE analysis_jobs(id INTEGER PRIMARY KEY,kind TEXT,status TEXT,dispatch_owner TEXT,created_at TEXT,scientific_run_id INTEGER)');
        $pdo->exec("INSERT INTO analysis_jobs VALUES(1,'assistant.profile.review','running','lab','2026-11-08',99)");
        $this->assertSame(1,(int)$pdo->query(LocalProfileQueue::activeSql())->fetchColumn());
        $pdo->exec("UPDATE analysis_jobs SET kind='profile'");
        $this->assertSame(1,(int)$pdo->query(LocalProfileQueue::activeSql())->fetchColumn());
    }

    public function testNoDirectScientificCreationOrLegacyGlobalOrchestrator(): void
    {
        $root = dirname(__DIR__,2);
        $cmd = file_get_contents($root.'/src/Command/ProfileOnceCommand.php');
        $controller = file_get_contents($root.'/src/Controller/ProfileController.php');
        $batch = file_get_contents($root.'/src/Controller/WorkbenchController.php');
        $assistant = file_get_contents($root.'/src/Service/ProfileAiRequestService.php');
        self::assertStringContainsString('PROFILE_QUEUE_REQUIRED',$cmd);
        self::assertStringContainsString('LocalProfileQueue::headSql()',$cmd);
        self::assertStringContainsString('lab:profile:drain',file_get_contents($root.'/src/Command/LocalProfileDrainCommand.php'));
        self::assertStringContainsString('$queue->enqueue',$controller);
        self::assertStringContainsString('$queue->enqueue',$batch);
        self::assertStringContainsString('LocalProfileQueue::headSql()',$assistant);
        self::assertStringNotContainsString('GlobalQueueAdmission', file_get_contents($root.'/src/Service/LocalProfileQueue.php'));
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Service\LocalProfileQueue;

/** Isolate the new local queue from archived Orchestrator-owned admissions. */
final class LocalProfileQueueOwnerIsolation1_6A33Test extends TestCase
{
    public function testArchivedGlobalJobsCannotBecomeLocalHeadOrBlockExecution(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE analysis_jobs (id INTEGER PRIMARY KEY, kind TEXT, status TEXT, dispatch_owner TEXT, created_at TEXT, scientific_run_id INTEGER)');
        $pdo->exec("INSERT INTO analysis_jobs VALUES (193, 'profile', 'queued', 'orchestrator', '2026-10-28', 168)");
        $pdo->exec("INSERT INTO analysis_jobs VALUES (194, 'profile', 'running', 'orchestrator', '2026-10-29', 169)");
        $pdo->exec("INSERT INTO analysis_jobs VALUES (160, 'assistant.profile.review', 'queued', 'lab', '2026-10-19', 153)");
        $pdo->exec("INSERT INTO analysis_jobs VALUES (214, 'profile', 'queued', 'lab', '2026-11-08', 189)");
        self::assertSame(160, (int)$pdo->query(LocalProfileQueue::headSql())->fetch(PDO::FETCH_ASSOC)['id']);
        self::assertSame(0, (int)$pdo->query(LocalProfileQueue::activeSql())->fetchColumn());
        $pdo->exec("UPDATE analysis_jobs SET status='cancelled' WHERE id=160");
        self::assertSame(214, (int)$pdo->query(LocalProfileQueue::headSql())->fetch(PDO::FETCH_ASSOC)['id']);
        $pdo->exec("UPDATE analysis_jobs SET status='running' WHERE id=214");
        self::assertSame(1, (int)$pdo->query(LocalProfileQueue::activeSql())->fetchColumn());
    }

    public function testProfileAdmissionDuplicateGuardIsScopedToLocalOwner(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__,2).'/src/Service/LocalProfileQueue.php');
        self::assertStringContainsString("AND kind='profile' AND dispatch_owner='lab'", $source);
        self::assertStringContainsString("AND dispatch_owner='lab'", LocalProfileQueue::activeSql());
        self::assertStringContainsString("AND kind IN ('profile','assistant.profile.review')", LocalProfileQueue::headSql());
    }
}

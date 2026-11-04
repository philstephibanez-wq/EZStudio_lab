<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Database;
use App\Service\GlobalQueueAdmission;
use PHPUnit\Framework\TestCase;

final class GlobalQueueAdmission1_6A0Test extends TestCase
{
    public function testTechnicalEnvelopeContainsNoScientificOrPrivateData(): void
    {
        self::assertSame([
            'external_id' => 'analysis_job:200',
            'idempotency_key' => 'lab.analysis_job.200',
            'kind' => 'profile',
            'resource_class' => 'gpu',
            'priority' => 50,
        ], GlobalQueueAdmission::envelope(200, 'profile'));
        foreach (['stems', 'chords_scientific'] as $kind) {
            self::assertSame($kind, GlobalQueueAdmission::envelope(201, $kind)['kind']);
        }
        $this->expectException(\InvalidArgumentException::class);
        GlobalQueueAdmission::envelope(0, 'profile');
    }

    public function testNewGlobalJobsAreExcludedAndFencedFromLegacyClaims(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite required');
        $file = tempnam(sys_get_temp_dir(), 'ezs-admission-test-');
        if (!is_string($file)) self::fail('cannot allocate SQLite fixture');
        try {
            $db = new Database($file);
            $songId = $db->createSong('Fixture', 'Test', 'fixture.wav', str_repeat('a', 64));
            $pdo = $db->pdo();
            $stmt = $pdo->prepare(<<<'SQL'
INSERT INTO analysis_jobs(
run_id,scientific_run_id,song_id,source_path,kind,status,progress,
request_json,error,created_at,updated_at,dispatch_owner
) VALUES(NULL,NULL,?,'fixture.wav','profile','queued',0,'{}',NULL,?,?,?)
SQL);
            $now = gmdate('c');
            $stmt->execute([$songId, $now, $now, 'orchestrator']);
            $globalId = (int) $pdo->lastInsertId();
            $stmt->execute([$songId, $now, $now, 'lab']);
            $localId = (int) $pdo->lastInsertId();
            self::assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM global_queue_admission_outbox')->fetchColumn());
            self::assertSame('pending', (string) $pdo->query('SELECT state FROM global_queue_admission_outbox')->fetchColumn());
            self::assertSame([$localId], array_column($db->analysisQueue(), 'job_id'));
            $claimed = $db->claimNextAnalysisJob();
            self::assertSame($localId, (int) $claimed['id']);
            self::assertSame('queued', (string) $pdo->query('SELECT status FROM analysis_jobs WHERE id='.$globalId)->fetchColumn());
            try {
                $pdo->exec("UPDATE analysis_jobs SET status='running' WHERE id=".$globalId);
                self::fail('global job should be fenced against legacy status update');
            } catch (\PDOException $e) {
                self::assertStringContainsString('global_queue_dispatch_fenced', $e->getMessage());
            }
            try {
                $pdo->exec('DELETE FROM analysis_jobs WHERE id='.$globalId);
                self::fail('global job should not be deleted without cancellation protocol');
            } catch (\PDOException $e) {
                self::assertStringContainsString('global_queue_cancel_protocol_required', $e->getMessage());
            }
            self::assertSame($globalId, (int) (new GlobalQueueAdmission($db))->pending()[0]['job_id']);
        } finally {
            foreach ([$file, $file.'-wal', $file.'-shm'] as $path) {
                if (is_file($path)) @unlink($path);
            }
        }
    }
}

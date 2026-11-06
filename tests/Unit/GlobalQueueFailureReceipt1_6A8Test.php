<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\AnalysisRunRegistry;
use App\Service\Database;
use App\Service\GlobalQueuePersistedReceipt;
use PHPUnit\Framework\TestCase;

final class GlobalQueueFailureReceipt1_6A8Test extends TestCase
{
    private string $dir;
    private Database $db;
    private AnalysisRunRegistry $runs;
    private int $jobId;
    private int $runId;
    private string|false $priorKey;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite_required');
        $temp = PHP_OS_FAMILY === 'Windows' ? 'H:/temp' : sys_get_temp_dir();
        self::assertDirectoryExists($temp);
        $this->dir = $temp.'/ezs-profile-failure-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->dir, 0700));
        $this->priorKey = getenv('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY');
        putenv('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY='.str_repeat('k', 48));
        $this->db = new Database($this->dir.'/fixture.sqlite');
        $this->runs = new AnalysisRunRegistry($this->db);
        $song = $this->db->createSong('Fixture', 'Test', 'source.wav', str_repeat('a', 64));
        $run = $this->runs->createRun($song, 'profile', ['name' => 'fixture'], []);
        $this->runId = (int)$run['id'];
        $now = gmdate('c');
        $stmt = $this->db->pdo()->prepare(<<<'SQL'
INSERT INTO analysis_jobs(run_id,scientific_run_id,song_id,source_path,kind,status,progress,request_json,error,created_at,updated_at,dispatch_owner)
VALUES(NULL,?,?,'source.wav','profile','queued',0,'{}',NULL,?,?,'orchestrator')
SQL);
        $stmt->execute([$this->runId, $song, $now, $now]);
        $this->jobId = (int)$this->db->pdo()->lastInsertId();
        $this->db->pdo()->prepare("UPDATE global_queue_admission_outbox SET state='admitted',global_job_id=907 WHERE job_id=?")
            ->execute([$this->jobId]);
    }

    protected function tearDown(): void
    {
        if (isset($this->priorKey)) {
            $this->priorKey === false ? putenv('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY')
                : putenv('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY='.$this->priorKey);
        }
        unset($this->db, $this->runs);
        if (isset($this->dir) && is_dir($this->dir)) {
            foreach (glob($this->dir.'/*') ?: [] as $p) if (is_file($p)) @unlink($p);
            @rmdir($this->dir);
        }
    }

    public function testFailureMustBePersistedBeforeSigning(): void
    {
        $signer = new GlobalQueuePersistedReceipt($this->db);
        $this->expectExceptionMessage('profile_failure_not_attested');
        $signer->failedProfile($this->jobId, 907, 1);
    }

    public function testFailureReceiptBindsToExistingGlobalJobAndGeneration(): void
    {
        $this->runs->setState($this->runId, 'error', [], ['error' => 'profile_worker_exit_1'], [], gmdate('c'));
        $signer = new GlobalQueuePersistedReceipt($this->db);
        $receipt = $signer->failedProfile($this->jobId, 907, 3);
        self::assertSame('failed', $receipt['result']);
        self::assertNull($receipt['artifact_sha256']);
        self::assertSame('analysis_job:'.$this->jobId, $receipt['external_id']);
        self::assertSame(907, $receipt['job_id']);
        self::assertSame(3, $receipt['claim_generation']);
        $canonical = implode("\n", ['ezs.lab.persisted-result.v1', 'lab',
            'analysis_job:'.$this->jobId, '907', '3', 'failed', '-']);
        self::assertSame(hash_hmac('sha256', $canonical, str_repeat('k', 48)), $receipt['signature']);
        self::assertSame('queued', $this->db->analysisJob($this->jobId)['status']);
        $this->expectExceptionMessage('profile_failure_not_attested');
        $signer->failedProfile($this->jobId, 908, 3);
    }

    public function testPendingAdmissionCannotSignFailure(): void
    {
        $this->runs->setState($this->runId, 'error', [], ['error' => 'profile_worker_exit_1'], [], gmdate('c'));
        $this->db->pdo()->prepare("UPDATE global_queue_admission_outbox SET state='pending' WHERE job_id=?")
            ->execute([$this->jobId]);
        $this->expectExceptionMessage('profile_failure_not_attested');
        (new GlobalQueuePersistedReceipt($this->db))->failedProfile($this->jobId, 907, 1);
    }
}

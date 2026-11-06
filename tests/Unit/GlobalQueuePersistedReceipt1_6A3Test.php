<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\AnalysisRunRegistry;
use App\Service\Database;
use App\Service\GlobalQueuePersistedReceipt;
use PHPUnit\Framework\TestCase;

final class GlobalQueuePersistedReceipt1_6A3Test extends TestCase
{
    private string $root;
    private string $dbFile;
    private string $output;
    private Database $db;
    private AnalysisRunRegistry $runs;
    private int $labJob;
    private int $globalJob = 907;
    private int $runId;
    private string|false $priorKey;
    private mixed $priorServerKey;
    private mixed $priorEnvKey;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite_required');
        $temp = PHP_OS_FAMILY === 'Windows' ? 'H:/temp' : sys_get_temp_dir();
        self::assertDirectoryExists($temp);
        $this->root = $temp.'/ezs-receipt-fixture-'.bin2hex(random_bytes(8));
        self::assertTrue(mkdir($this->root, 0700));
        $this->dbFile = $this->root.'/fixture.sqlite';
        $this->output = $this->root.'/profile.json';
        $this->priorKey = getenv('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY');
        $this->priorServerKey = $_SERVER['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'] ?? null;
        $this->priorEnvKey = $_ENV['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'] ?? null;
        putenv('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY='.str_repeat('k', 48));
        $this->db = new Database($this->dbFile);
        $this->runs = new AnalysisRunRegistry($this->db);
        $songId = $this->db->createSong('Fixture', 'Test', 'source.wav', str_repeat('a', 64));
        $run = $this->runs->createRun($songId, 'profile', ['name'=>'fixture'], []);
        $this->runId = (int) $run['id'];
        $now = gmdate('c');
        $request = json_encode([
            'scientific_run_id' => $this->runId,
            'output_path' => $this->output,
            'output_contract' => 'ezstudio.profile.v1',
            'audio_hash' => str_repeat('a', 64),
        ], JSON_THROW_ON_ERROR);
        $stmt = $this->db->pdo()->prepare(<<<'SQL'
INSERT INTO analysis_jobs(run_id,scientific_run_id,song_id,source_path,kind,status,progress,request_json,error,created_at,updated_at,dispatch_owner)
VALUES(NULL,?,?,'source.wav','profile','queued',0,?,NULL,?,?,'orchestrator')
SQL);
        $stmt->execute([$this->runId, $songId, $request, $now, $now]);
        $this->labJob = (int) $this->db->pdo()->lastInsertId();
        $this->db->pdo()->prepare("UPDATE global_queue_admission_outbox SET state='admitted',global_job_id=? WHERE job_id=?")
            ->execute([$this->globalJob, $this->labJob]);
    }

    protected function tearDown(): void
    {
        if (isset($this->priorKey)) {
            if ($this->priorKey === false) putenv('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY');
            else putenv('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY='.$this->priorKey);
        }
        if (isset($this->priorServerKey)) $_SERVER['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'] = $this->priorServerKey;
        else unset($_SERVER['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY']);
        if (isset($this->priorEnvKey)) $_ENV['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'] = $this->priorEnvKey;
        else unset($_ENV['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY']);
        unset($this->db, $this->runs);
        if (isset($this->root) && is_dir($this->root)) {
            foreach (glob($this->root.'/*') ?: [] as $file) if (is_file($file)) @unlink($file);
            @rmdir($this->root);
        }
    }

    private function persist(): string
    {
        file_put_contents($this->output, '{"schema":"ezstudio.profile.v1","characteristics":{}}');
        $digest = hash_file('sha256', $this->output);
        self::assertIsString($digest);
        $this->runs->registerArtifact($this->runId, 'profile_json', $this->output, $digest);
        $this->runs->setState($this->runId, 'done', [], [], [], gmdate('c'));
        return $digest;
    }

    public function testReceiptRequiresScientificRunDoneAndRegisteredArtifact(): void
    {
        $signer = new GlobalQueuePersistedReceipt($this->db);
        try {
            $signer->completedProfile($this->labJob, $this->globalJob, 1);
            self::fail('Unsigned scientific run should fail');
        } catch (\DomainException $e) {
            self::assertSame('profile_persistence_not_attested', $e->getMessage());
        }
        $digest = $this->persist();
        $receipt = $signer->completedProfile($this->labJob, $this->globalJob, 3);
        self::assertSame($digest, $receipt['artifact_sha256']);
        self::assertSame('analysis_job:'.$this->labJob, $receipt['external_id']);
        self::assertSame(907, $receipt['job_id']);
        self::assertSame(3, $receipt['claim_generation']);
        self::assertSame('completed', $receipt['result']);
        $canonical = implode("\n", [
            'ezs.lab.persisted-result.v1','lab', 'analysis_job:'.$this->labJob,
            '907','3','completed',$digest,
        ]);
        self::assertSame(hash_hmac('sha256', $canonical, str_repeat('k', 48)), $receipt['signature']);
        self::assertSame('queued', $this->db->analysisJob($this->labJob)['status']);
    }

    public function testArtifactTamperAndWrongGlobalIdentityAreRejected(): void
    {
        $this->persist();
        $signer = new GlobalQueuePersistedReceipt($this->db);
        try {
            $signer->completedProfile($this->labJob, $this->globalJob + 1, 1);
            self::fail('Foreign global job accepted');
        } catch (\DomainException $e) {
            self::assertSame('profile_persistence_not_attested', $e->getMessage());
        }
        file_put_contents($this->output, '{"schema":"ezstudio.profile.v1","characteristics":{"changed":true}}');
        $this->expectExceptionMessage('profile_artifact_digest_mismatch');
        $signer->completedProfile($this->labJob, $this->globalJob, 1);
    }

    public function testPendingAdmissionAndInvalidKeyCannotIssueReceipt(): void
    {
        $this->persist();
        $signer = new GlobalQueuePersistedReceipt($this->db);
        $this->db->pdo()->prepare("UPDATE global_queue_admission_outbox SET state='pending' WHERE job_id=?")
            ->execute([$this->labJob]);
        try {
            $signer->completedProfile($this->labJob, $this->globalJob, 1);
            self::fail('Pending admission accepted');
        } catch (\DomainException $e) {
            self::assertSame('profile_persistence_not_attested', $e->getMessage());
        }
        putenv('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY=invalid');
        unset($_SERVER['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'], $_ENV['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY']);
        $this->expectExceptionMessage('persistence_attestation_not_configured');
        $signer->completedProfile($this->labJob, $this->globalJob, 1);
    }
}

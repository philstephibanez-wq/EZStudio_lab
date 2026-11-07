<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Database;
use PHPUnit\Framework\TestCase;

final class GlobalQueueTerminalAlreadyFinished1_6A18Test extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    private function fixture(string $scientificState, string $jobState = 'queued'): array
    {
        $root = PHP_OS_FAMILY === 'Windows' ? 'H:/temp' : sys_get_temp_dir();
        self::assertDirectoryExists($root);
        $path = rtrim($root, '/\\').'/ezs-terminal-sync-fixture-'.bin2hex(random_bytes(12)).'.sqlite';
        $this->paths[] = $path;
        $database = new Database($path);
        $pdo = $database->pdo();
        $pdo->exec('CREATE TABLE IF NOT EXISTS scientific_runs (id INTEGER PRIMARY KEY, state TEXT NOT NULL, finished_at TEXT)');
        $pdo->prepare('INSERT INTO scientific_runs(id,state,finished_at) VALUES(11,?,?)')->execute([
            $scientificState,
            $scientificState === 'queued' ? null : '2026-11-06T14:50:47+00:00',
        ]);
        $pdo->exec("INSERT INTO songs(id,title,artist,source_filename,audio_sha256,created_at) VALUES(22,'fixture','','fixture.mp3','hash','2026-11-06T00:00:00Z')");
        $statement = $pdo->prepare("INSERT INTO analysis_jobs(id,scientific_run_id,song_id,source_path,kind,status,progress,request_json,created_at,updated_at,dispatch_owner) VALUES(33,11,22,'H:/temp/fixture.mp3','profile',?,0,'{}','2026-11-06T00:00:00Z','2026-11-06T00:00:00Z','orchestrator')");
        $statement->execute([$jobState]);
        $pdo->exec("UPDATE global_queue_admission_outbox SET state='admitted',global_job_id=44 WHERE job_id=33");
        return [$database, $pdo];
    }

    public function testFailedGlobalCanMirrorAlreadyFailedScientificRun(): void
    {
        [$database, $pdo] = $this->fixture('error');
        $before = (string)$pdo->query('SELECT finished_at FROM scientific_runs WHERE id=11')->fetchColumn();
        $database->reconcileGlobalTerminal(33, 44, 'failed');
        self::assertSame('failed', (string)$pdo->query('SELECT status FROM analysis_jobs WHERE id=33')->fetchColumn());
        self::assertSame('error', (string)$pdo->query('SELECT state FROM scientific_runs WHERE id=11')->fetchColumn());
        self::assertSame($before, (string)$pdo->query('SELECT finished_at FROM scientific_runs WHERE id=11')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM global_queue_terminal_receipts')->fetchColumn());
        $database->reconcileGlobalTerminal(33, 44, 'failed');
    }

    public function testCancelledGlobalCanMirrorAlreadyCancelledScientificRun(): void
    {
        [$database, $pdo] = $this->fixture('cancelled');
        $database->reconcileGlobalTerminal(33, 44, 'cancelled');
        self::assertSame('cancelled', (string)$pdo->query('SELECT status FROM analysis_jobs WHERE id=33')->fetchColumn());
        self::assertSame('cancelled', (string)$pdo->query('SELECT state FROM scientific_runs WHERE id=11')->fetchColumn());
    }

    public function testQueuedScientificRunStillTransitionsAsBefore(): void
    {
        [$database, $pdo] = $this->fixture('queued');
        $database->reconcileGlobalTerminal(33, 44, 'failed');
        self::assertSame('failed', (string)$pdo->query('SELECT status FROM analysis_jobs WHERE id=33')->fetchColumn());
        self::assertSame('error', (string)$pdo->query('SELECT state FROM scientific_runs WHERE id=11')->fetchColumn());
    }

    public function testContradictoryTerminalStateIsRefusedWithoutMutation(): void
    {
        [$database, $pdo] = $this->fixture('error');
        try {
            $database->reconcileGlobalTerminal(33, 44, 'cancelled');
            self::fail('Expected a terminal conflict');
        } catch (\DomainException $error) {
            self::assertSame('global_terminal_local_state_conflict', $error->getMessage());
        }
        self::assertSame('queued', (string)$pdo->query('SELECT status FROM analysis_jobs WHERE id=33')->fetchColumn());
        self::assertSame('error', (string)$pdo->query('SELECT state FROM scientific_runs WHERE id=11')->fetchColumn());
        self::assertSame(0, (int)$pdo->query('SELECT COUNT(*) FROM global_queue_terminal_receipts')->fetchColumn());
    }

    public function testWrongGlobalIdentityIsRefused(): void
    {
        [$database, $pdo] = $this->fixture('error');
        try {
            $database->reconcileGlobalTerminal(33, 999, 'failed');
            self::fail('Expected identity mismatch');
        } catch (\DomainException $error) {
            self::assertSame('global_terminal_identity_conflict', $error->getMessage());
        }
        self::assertSame('queued', (string)$pdo->query('SELECT status FROM analysis_jobs WHERE id=33')->fetchColumn());
    }
}

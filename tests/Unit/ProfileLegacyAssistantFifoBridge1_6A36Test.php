<?php

declare(strict_types=1);

use App\Service\Database;
use App\Service\ProfileAiRequestService;
use App\Service\LegacyProfileFinalProjection;
use App\Service\ProfileExternalReviewService;
use PHPUnit\Framework\TestCase;

final class ProfileLegacyAssistantFifoBridge1_6A36Test extends TestCase
{
    private function connection(): Database
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite unavailable');
        $db = new Database(':memory:');
        $db->pdo()->exec('PRAGMA foreign_keys=OFF');
        return $db;
    }

    private function insertJob(Database $db, int $id, string $kind, string $status, int $run, string $owner, string $when): void
    {
        $stmt = $db->pdo()->prepare(
            'INSERT INTO analysis_jobs(id,run_id,scientific_run_id,song_id,source_path,kind,status,progress,request_json,error,created_at,updated_at,dispatch_owner)
             VALUES(?,NULL,?,1,?, ?,?,0,?,NULL,?,?,?)'
        );
        $stmt->execute([$id, $run, '', $kind, $status, '{}', $when, $when, $owner]);
    }

    private function head(Database $db): ?array
    {
        $service = new ProfileExternalReviewService($db);
        $method = new ReflectionMethod(ProfileExternalReviewService::class, 'legacyAssistantAutoReviewHead');
        return $method->invoke($service);
    }

    public function testExpertiseHeadKeepsFifoOrderAndIgnoresOrchestrator(): void
    {
        $db = $this->connection();
        $this->insertJob($db, 193, 'profile', 'queued', 170, 'orchestrator', '2026-10-28T12:36:23Z');
        $this->insertJob($db, 217, 'assistant.profile.review', 'queued', 191, 'lab', '2026-11-08T13:32:13Z');
        $this->insertJob($db, 218, 'profile', 'queued', 192, 'lab', '2026-11-08T22:50:59Z');
        $this->insertJob($db, 219, 'assistant.profile.review', 'queued', 186, 'lab', '2026-11-09T08:09:07Z');
        $head = $this->head($db);
        self::assertNotNull($head);
        self::assertSame(217, (int)$head['id']);
        self::assertSame(191, (int)$head['scientific_run_id']);
    }

    public function testEarlierProfileBlocksExpertise(): void
    {
        $db = $this->connection();
        $this->insertJob($db, 216, 'profile', 'queued', 191, 'lab', '2026-11-08T12:32:13Z');
        $this->insertJob($db, 217, 'assistant.profile.review', 'queued', 191, 'lab', '2026-11-08T13:32:13Z');
        self::assertNull($this->head($db));
    }

    public function testRunningProfileBlocksExpertise(): void
    {
        $db = $this->connection();
        $this->insertJob($db, 216, 'profile', 'running', 190, 'lab', '2026-11-08T12:32:13Z');
        $this->insertJob($db, 217, 'assistant.profile.review', 'queued', 191, 'lab', '2026-11-08T13:32:13Z');
        self::assertNull($this->head($db));
    }

    public function testMaintenancePauseBlocksExpertise(): void
    {
        $db = $this->connection();
        $this->insertJob($db, 217, 'assistant.profile.review', 'queued', 191, 'lab', '2026-11-08T13:32:13Z');
        $db->pdo()->exec('CREATE TABLE IF NOT EXISTS profile_ai_maintenance(id INTEGER PRIMARY KEY, paused INTEGER NOT NULL)');
        $db->pdo()->exec('INSERT OR REPLACE INTO profile_ai_maintenance(id,paused) VALUES(1,1)');
        self::assertNull($this->head($db));
    }

    public function testLegacyProjectionOnlyPublishesFinalMusicalAssertions(): void
    {
        $output = LegacyProfileFinalProjection::fromFinal(191, [
            'title'=>'Chanson','artist'=>'Artiste',
            'secret_engine_settings'=>'NEVER_EXPOSE_TEST_SECRET',
            'metrics'=>['secret'=>'NEVER_EXPOSE_TEST_SECRET'],
            'final_assertions'=>[
                ['id'=>'genre','dimension'=>'genre','value'=>'rock'],
                ['id'=>'instruments:0','dimension'=>'instruments','value'=>'piano'],
                ['id'=>'tempo','dimension'=>'tempo','value'=>120.0],
                ['id'=>'future_item_1','dimension'=>'future_item','value'=>'valeur arbitraire'],
                ['id'=>'tempo','dimension'=>'tempo','value'=>999],
            ],
        ]);
        self::assertSame(4, $output['final_assertions_count']);
        self::assertSame([], $output['config']);
        self::assertSame([], $output['metrics']);
        self::assertSame(['label'=>'piano','decision'=>'accepted'],
            $output['diagnostics']['profile_view']['instrumentation']['candidates'][0]);
        self::assertSame('rock', $output['diagnostics']['profile_view']['genre_hierarchy']['primary']['genre']);
        self::assertSame('future_item', $output['final_assertions'][3]['dimension']);
        self::assertStringNotContainsString('NEVER_EXPOSE_TEST_SECRET', json_encode($output, JSON_THROW_ON_ERROR));
    }

    public function testNoRecordingIdentityNeverExposesUnverifiedQueueRow(): void
    {
        $this->expectException(DomainException::class);
        LegacyProfileFinalProjection::fromFinal(191, [
            'title'=>'Chanson','artist'=>null,'final_assertions'=>[],
        ]);
    }

    public function testCompletionIsAtomicAndPreservesNextFifoHead(): void
    {
        $db = $this->connection();
        $this->insertJob($db, 193, 'profile', 'queued', 170, 'orchestrator', '2026-10-28T12:36:23Z');
        $this->insertJob($db, 217, 'assistant.profile.review', 'queued', 191, 'lab', '2026-11-08T13:32:13Z');
        $this->insertJob($db, 218, 'profile', 'queued', 192, 'lab', '2026-11-08T22:50:59Z');
        $this->insertJob($db, 219, 'assistant.profile.review', 'queued', 186, 'lab', '2026-11-09T08:09:07Z');
        $db->pdo()->exec('CREATE TABLE proof(run_id INTEGER PRIMARY KEY)');
        $result = (new ProfileAiRequestService($db))->completeLegacyReview(191,
            function () use ($db): array {
                $db->pdo()->exec('INSERT INTO proof(run_id) VALUES(191)');
                return ['status'=>'completed','run_id'=>191,'id'=>5];
            }
        );
        self::assertSame('completed', $result['status']);
        self::assertSame(191, (int)$db->pdo()->query('SELECT run_id FROM proof')->fetchColumn());
        $state = $db->pdo()->query('SELECT id,status,progress FROM analysis_jobs WHERE id IN (217,218,219) ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        self::assertSame(['completed','queued','queued'], array_column($state, 'status'));
        self::assertSame([100,0,0], array_map('intval', array_column($state,'progress')));
        self::assertSame(218, (int)$db->pdo()->query("SELECT id FROM analysis_jobs WHERE status='queued' AND dispatch_owner='lab' ORDER BY created_at,id LIMIT 1")->fetchColumn());
    }

    public function testPersistenceFailureRollsBackClaimAndRevision(): void
    {
        $db = $this->connection();
        $this->insertJob($db, 217, 'assistant.profile.review', 'queued', 191, 'lab', '2026-11-08T13:32:13Z');
        $db->pdo()->exec('CREATE TABLE proof(run_id INTEGER PRIMARY KEY)');
        try {
            (new ProfileAiRequestService($db))->completeLegacyReview(191,
                function () use ($db): array {
                    $db->pdo()->exec('INSERT INTO proof(run_id) VALUES(191)');
                    throw new RuntimeException('persist_fail_test');
                }
            );
            self::fail('Expected persistence error');
        } catch (RuntimeException $e) {
            self::assertSame('persist_fail_test', $e->getMessage());
        }
        self::assertSame(0, (int)$db->pdo()->query('SELECT COUNT(*) FROM proof')->fetchColumn());
        self::assertSame('queued', $db->pdo()->query('SELECT status FROM analysis_jobs WHERE id=217')->fetchColumn());
    }

    public function testWrongHeadCannotPersistOrClaim(): void
    {
        $db = $this->connection();
        $this->insertJob($db, 216, 'profile', 'queued', 190, 'lab', '2026-11-08T12:30:00Z');
        $this->insertJob($db, 217, 'assistant.profile.review', 'queued', 191, 'lab', '2026-11-08T13:32:13Z');
        $written = false;
        try {
            (new ProfileAiRequestService($db))->completeLegacyReview(191,
                function () use (&$written): array { $written = true; return ['status'=>'completed','run_id'=>191]; }
            );
            self::fail('Expected wrong-head rejection');
        } catch (RuntimeException $e) {
            self::assertSame('legacy_assistant_fifo_claim_rejected', $e->getMessage());
        }
        self::assertFalse($written);
        self::assertSame('queued', $db->pdo()->query('SELECT status FROM analysis_jobs WHERE id=217')->fetchColumn());
    }

    public function testNoFalseCompletionWhenPersistedReviewIsDraft(): void
    {
        $db = $this->connection();
        $this->insertJob($db, 217, 'assistant.profile.review', 'queued', 191, 'lab', '2026-11-08T13:32:13Z');
        try {
            (new ProfileAiRequestService($db))->completeLegacyReview(191,
                static fn(): array => ['status'=>'draft','run_id'=>191]
            );
            self::fail('Expected unverified draft rejection');
        } catch (RuntimeException $e) {
            self::assertSame('legacy_assistant_review_not_persisted', $e->getMessage());
        }
        self::assertSame('queued', $db->pdo()->query('SELECT status FROM analysis_jobs WHERE id=217')->fetchColumn());
    }

    public function testLegacySaveUsesAtomicQueueBridge(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2).'/src/Service/ProfileExternalReviewService.php');
        self::assertStringContainsString('LEGACY_FIFO_GATE_R1', $service);
        self::assertStringContainsString('LegacyProfileFinalProjection::fromFinal', $service);
        self::assertStringContainsString('completeLegacyReview((int)$runId, $persist)', $service);
        self::assertStringContainsString("\$legacyAssistant = \$reviewer === 'assistant-auto'", $service);
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use App\Service\Database;
use App\Service\ProfileAiRequestService;

final class ExpertiseCancellation1_6A37Test extends TestCase
{
    private function newEnvironment(): array
    {
        $db = new Database(':memory:');
        $pdo = $db->pdo();
        $pdo->exec("INSERT INTO songs(title,artist,source_filename,audio_sha256,created_at) VALUES('Chanson témoin','Artiste','test.wav','sha','2026-11-10T00:00:00Z')");
        return [$db, $pdo, new ProfileAiRequestService($db)];
    }

    private function insertJob(PDO $pdo, int $runId, string $status): int
    {
        $query = $pdo->prepare("INSERT INTO analysis_jobs(scientific_run_id,song_id,source_path,kind,status,progress,request_json,error,created_at,updated_at,dispatch_owner) VALUES(?,1,'','assistant.profile.review',?,0,'{}',NULL,'2026-11-10T00:00:00Z','2026-11-10T00:00:00Z','lab')");
        $query->execute([$runId,$status]);
        return (int)$pdo->lastInsertId();
    }

    public function testCatalogueReusesExistingHtmlDialogAndCancelRoute(): void
    {
        $root = dirname(__DIR__,2);
        $twig = file_get_contents($root.'/templates/workbench/home.html.twig');
        $js = file_get_contents($root.'/public/js/ezstudio-lab.js');
                self::assertStringContainsString('{% for job in expertise.active %}', $twig);
        self::assertStringContainsString('EXPERTISE IA', $twig);
        self::assertStringContainsString("job.status in ['queued', 'running']", $twig);
        self::assertStringContainsString('data-job-id="{{ job.job_id }}"', $twig);
        self::assertStringContainsString('catalog-job-cancel', $twig);
        self::assertStringContainsString("path('analysis_job_cancel'", $twig);
        self::assertStringContainsString('confirmUi(', $js);
        self::assertStringNotContainsString('window.confirm(', $js);
        self::assertStringNotContainsString('window.alert(', $js);
        self::assertStringNotContainsString('window.prompt(', $js);
    }

    public function testQueuedExpertiseIsCancelledImmediately(): void
    {
        [$db,$pdo,$service] = $this->newEnvironment();
        $id = $this->insertJob($pdo,101,'queued');
        $updated = $db->requestAnalysisJobCancellation($id);
        self::assertSame('cancelled',$updated['status']);
        self::assertSame('cancelled',$service->executionState(101,$id)['status']);
    }

    public function testRunningExpertiseRequiresWorkerAcknowledgement(): void
    {
        [$db,$pdo,$service] = $this->newEnvironment();
        $id = $this->insertJob($pdo,102,'running');
        self::assertSame('cancelling',$db->requestAnalysisJobCancellation($id)['status']);
        self::assertSame('cancelling',$service->executionState(102,$id)['status']);
        self::assertSame('cancelled',$service->acknowledgeCancellation(102,$id)['status']);
        self::assertSame('cancelled',$service->acknowledgeCancellation(102,$id)['status']);
        $this->expectException(RuntimeException::class);
        $service->finish(102,'completed','late result',$id);
    }

    public function testWorkerCannotAcknowledgeAnotherAttempt(): void
    {
        [$db,$pdo,$service] = $this->newEnvironment();
        $id = $this->insertJob($pdo,103,'running');
        $this->expectException(RuntimeException::class);
        $service->acknowledgeCancellation(103,$id+1);
    }

    public function testSuccessfulNonCancelledAttemptStillCompletes(): void
    {
        [$db,$pdo,$service] = $this->newEnvironment();
        $id = $this->insertJob($pdo,104,'running');
        $result = $service->finish(104,'completed','ok',$id);
        self::assertSame('completed',$result['status']);
    }
}

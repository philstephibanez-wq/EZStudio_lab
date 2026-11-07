<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\AnalysisRunRegistry;
use App\Service\CatalogService;
use App\Service\Database;
use PHPUnit\Framework\TestCase;

/** Real SQLite in an isolated temporary file; never touches the MAIN database. */
final class CatalogueProfileHistoricalAvailability1_6A19Test extends TestCase
{
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            foreach ([$path, $path.'-wal', $path.'-shm'] as $file) {
                if (is_file($file)) @unlink($file);
            }
        }
    }

    private function setupCatalogue(): array
    {
        $dir = PHP_OS_FAMILY === 'Windows' ? 'H:/temp' : sys_get_temp_dir();
        self::assertDirectoryExists($dir);
        $path = rtrim($dir, '/\\').'/ezs-catalog-r19-'.bin2hex(random_bytes(10)).'.sqlite';
        $this->paths[] = $path;
        $db = new Database($path);
        $pdo = $db->pdo();
        (new AnalysisRunRegistry($db))->ensureSchema();
        $pdo->exec("INSERT INTO songs(id,title,artist,source_filename,audio_sha256,created_at)
                    VALUES(12,'Fixture','Test','fixture.mp3','fixture-hash','2026-11-06T00:00:00Z')");
        return [$db, $pdo];
    }

    private function addRun(\PDO $pdo, int $id, string $state, ?string $jobStatus): void
    {
        $stmt = $pdo->prepare("INSERT INTO scientific_runs(public_id,song_id,item,state,engine_name,
            engine_version,created_at) VALUES(?,12,'profile',?,'test','PROFILE.r11','2026-11-06T00:00:00Z')");
        $stmt->execute(['profile-test-'.$id, $state]);
        $runId = (int) $pdo->lastInsertId();
        if ($jobStatus !== null) {
            $pdo->prepare("INSERT INTO analysis_jobs(scientific_run_id,song_id,source_path,kind,status,
                request_json,created_at,updated_at) VALUES(?,12,'H:/temp/mock.mp3','profile',?,
                '{}','2026-11-06T00:00:00Z','2026-11-06T00:00:00Z')")
                ->execute([$runId, $jobStatus]);
        }
    }

    public function testCancelledAttemptDoesNotHideCompletedProfile(): void
    {
        [$db, $pdo] = $this->setupCatalogue();
        $this->addRun($pdo, 1, 'done', 'completed');
        $this->addRun($pdo, 2, 'cancelled', 'cancelled');
        $profile = (new CatalogService($db))->songs()[0]['profile'];
        self::assertSame('done', $profile['state']);
        self::assertSame('cancelled', $profile['latest_attempt']['state']);
        self::assertNotSame($profile['run_id'], $profile['latest_attempt']['run_id']);
    }

    public function testFailedAttemptDoesNotHideCompletedProfile(): void
    {
        [$db, $pdo] = $this->setupCatalogue();
        $this->addRun($pdo, 1, 'done', 'completed');
        $this->addRun($pdo, 2, 'error', 'failed');
        $profile = (new CatalogService($db))->songs()[0]['profile'];
        self::assertSame('done', $profile['state']);
        self::assertSame('error', $profile['latest_attempt']['state']);
    }

    public function testQueuedAttemptDoesNotHideCompletedProfile(): void
    {
        [$db, $pdo] = $this->setupCatalogue();
        $this->addRun($pdo, 1, 'done', 'completed');
        $this->addRun($pdo, 2, 'queued', 'queued');
        $profile = (new CatalogService($db))->songs()[0]['profile'];
        self::assertSame('done', $profile['state']);
        self::assertSame('queued', $profile['latest_attempt']['state']);
        self::assertGreaterThan(0, $profile['latest_attempt']['job_id']);
    }

    public function testNoCompletedProfileDoesNotInventAvailability(): void
    {
        [$db, $pdo] = $this->setupCatalogue();
        $this->addRun($pdo, 1, 'error', 'failed');
        $profile = (new CatalogService($db))->songs()[0]['profile'];
        self::assertSame('error', $profile['state']);
        self::assertArrayNotHasKey('latest_attempt', $profile);
    }

    public function testLatestCompletedProfileWinsOverOlderCompletedOne(): void
    {
        [$db, $pdo] = $this->setupCatalogue();
        $this->addRun($pdo, 1, 'done', 'completed');
        $this->addRun($pdo, 2, 'done', 'completed');
        $this->addRun($pdo, 3, 'error', 'failed');
        $profile = (new CatalogService($db))->songs()[0]['profile'];
        self::assertSame('done', $profile['state']);
        self::assertSame('error', $profile['latest_attempt']['state']);
        self::assertSame('PROFILE.r11', $profile['version']);
    }

    public function testTemplateKeepsReadyProfileAndShowsTerminalAttemptSeparately(): void
    {
        $root = dirname(__DIR__, 2);
        $template = (string) file_get_contents($root.'/templates/workbench/home.html.twig');
        $base = (string) file_get_contents($root.'/templates/base.html.twig');
        self::assertStringContainsString("key == 'profile' and s.latest_attempt", $template);
        self::assertStringContainsString('Dernier essai : erreur', $template);
        self::assertStringContainsString('Dernier essai : annulé', $template);
        self::assertStringContainsString('class="badge error">Erreur', $template);
        self::assertStringContainsString('.badge.error{', $base);
        self::assertStringContainsString('/js/ezstudio-lab.js?v={{ lab_version|url_encode }}', $base);
    }
}

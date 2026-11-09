<?php

declare(strict_types=1);

use App\Service\LocalQueueVisibility;
use PHPUnit\Framework\TestCase;

final class LocalQueueVisibilityTest extends TestCase
{
    public function testLocalFifoKeepsExpertiseBeforeProfileAndExcludesOrchestrator(): void
    {
        if (!extension_loaded('pdo_sqlite')) self::markTestSkipped('pdo_sqlite unavailable');
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('CREATE TABLE songs(id INTEGER PRIMARY KEY,title TEXT,artist TEXT)');
        $db->exec('CREATE TABLE analysis_jobs(id INTEGER PRIMARY KEY,kind TEXT,status TEXT,progress INTEGER,scientific_run_id INTEGER,song_id INTEGER,dispatch_owner TEXT,created_at TEXT,updated_at TEXT)');
        $db->exec("INSERT INTO songs VALUES(1,'Premier morceau','Auteur'),(2,'Second morceau','Auteur')");
        $db->exec("INSERT INTO analysis_jobs VALUES
            (216,'profile','completed',100,191,1,'lab','2026-11-08T13:30:00Z','2026-11-08T13:40:00Z'),
            (217,'assistant.profile.review','queued',0,191,1,'lab','2026-11-08T13:32:13Z','2026-11-08T13:32:13Z'),
            (218,'profile','queued',0,192,2,'lab','2026-11-08T22:50:59Z','2026-11-08T22:50:59Z'),
            (204,'profile','queued',0,100,1,'orchestrator','2026-10-10T00:00:00Z','2026-10-10T00:00:00Z')");
        $rows = LocalQueueVisibility::activeRows($db);
        self::assertSame([217,218], array_map(static fn($r): int => (int)$r['id'], $rows));
        self::assertSame(['assistant.profile.review','profile'], array_column($rows,'kind'));
        self::assertSame(['queued','queued'], array_column($rows,'status'));
    }

    public function testCatalogueContainsQueueAndIndividualExpertiseStates(): void
    {
        $root=dirname(__DIR__,2);
        $controller=(string)file_get_contents($root.'/src/Controller/WorkbenchController.php');
        $twig=(string)file_get_contents($root.'/templates/workbench/home.html.twig');
        self::assertStringContainsString('LocalQueueVisibility $visibility', $controller);
        self::assertStringContainsString("'queue_active'", $controller);
        self::assertStringContainsString("'expertise_by_song'", $controller);
        self::assertStringContainsString('EXPERTISE IA', $twig);
        self::assertStringContainsString('Job #{{ job.job_id }}', $twig);
        self::assertStringContainsString('Position {{ job.position }}', $twig);
        self::assertStringContainsString('expertise_by_song[song.id]', $twig);
        self::assertStringContainsString('Aucune expertise demandée', $twig);
    }
}

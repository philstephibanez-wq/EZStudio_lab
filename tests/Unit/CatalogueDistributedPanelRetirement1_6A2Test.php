<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class CatalogueDistributedPanelRetirement1_6A2Test extends TestCase
{
    public function testCatalogueContainsNoLegacyDistributedPanelOrLegacyReads(): void
    {
        $root = dirname(__DIR__, 2);
        $template = (string) file_get_contents($root.'/templates/workbench/home.html.twig');
        $controller = (string) file_get_contents($root.'/src/Controller/WorkbenchController.php');

        self::assertStringNotContainsString('id="distributed-execution"', $template);
        self::assertStringNotContainsString('Exécution distribuée', $template);
        self::assertStringNotContainsString('distributed_workers', $template);
        self::assertStringNotContainsString('distributed_jobs', $template);
        self::assertStringNotContainsString('.distributed-', $template);
        self::assertStringNotContainsString('DistributedAnalysisLeaseService', $controller);
        self::assertStringNotContainsString('workersSnapshot()', $controller);
        self::assertStringNotContainsString('jobsSnapshot()', $controller);
        self::assertStringContainsString("#[Route('/home', name: 'workbench_home'", $controller);
        self::assertStringContainsString("'songs' => \$catalog->songs()", $controller);
        self::assertStringContainsString('id="catalog-live-phase"', $template);
        self::assertStringContainsString('catalog-batch-toolbar', $template);
        self::assertStringContainsString("path('profile_analyze_all')", $template);
        self::assertStringContainsString("path('workbench_import')", $template);
    }

    public function testCatalogueTwigRemainsParsable(): void
    {
        $root = dirname(__DIR__, 2);
        $loader = new FilesystemLoader($root.'/templates');
        $twig = new Environment($loader, ['strict_variables' => true]);
        // Standalone Twig lacks Symfony RoutingExtension; supply its syntax-level function only.
        $twig->addFunction(new \Twig\TwigFunction('path', static fn (string $route, array $params = []): string => '/synthetic'));
        $source = (string) file_get_contents($root.'/templates/workbench/home.html.twig');
        $twig->parse($twig->tokenize(new \Twig\Source($source, 'workbench/home.html.twig')));
        self::assertTrue(true);
    }

    public function testHistoricalDistributedInfrastructureIsNotDestroyed(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root.'/src/Service/DistributedAnalysisLeaseService.php');
        self::assertStringContainsString('public function workersSnapshot(): array', $service);
        self::assertStringContainsString('public function jobsSnapshot(?int $songId = null): array', $service);
        self::assertFileDoesNotExist($root.'/src/Command/PublishGlobalQueueCommand.php');
        self::assertFileDoesNotExist($root.'/src/Service/GlobalQueueAdmission.php');
        self::assertFileExists($root.'/src/Command/ProfileOnceCommand.php');
    }
}

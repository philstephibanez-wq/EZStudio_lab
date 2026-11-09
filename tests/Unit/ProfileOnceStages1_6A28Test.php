<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Durable regression contract: observed one-shot stages; historical FIFO not shown as live. */
final class ProfileOnceStages1_6A28Test extends TestCase
{
    public function testReadOnlyStatusIsLimitedToLocalProfileRuns(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string) file_get_contents($root.'/src/Controller/ProfileController.php');
        self::assertStringContainsString("name: 'profile_oneshot_status'", $controller);
        self::assertStringContainsString("j.dispatch_owner='lab'", $controller);
        self::assertStringContainsString("j.kind='profile'", $controller);
        self::assertStringContainsString("j.scientific_run_id AS run_id", $controller);
        self::assertStringContainsString("'/var/tmp/jobs/'.".'$jobId'.".'/progress.json'", $controller);
        self::assertStringContainsString('is_int($raw[\'percent\'] ?? null)', $controller);
        self::assertStringContainsString('$previousJobId', $controller);
        self::assertStringContainsString("'after_job'=>".'$previousJobId', $controller);
        self::assertStringNotContainsString('messenger:consume', $controller);
        self::assertStringNotContainsString('lab:queue:execute-profile', $controller);
    }

    public function testCataloguePreservesCompletedProfileButNeverTreatsGlobalAsLocalActive(): void
    {
        $root = dirname(__DIR__, 2);
        $service = (string) file_get_contents($root.'/src/Service/CatalogService.php');
        $twig = (string) file_get_contents($root.'/templates/workbench/home.html.twig');
        self::assertStringContainsString('$group[$item][\'latest_attempt\']', $service);
        self::assertStringContainsString("'legacy_fifo'", $service);
        self::assertStringContainsString("'orchestrator'", $service);
        self::assertStringContainsString('Ancienne tentative FIFO (historique)', $twig);
        self::assertStringNotContainsString('one-shot', strtolower($twig));
        self::assertStringContainsString('Résultat scientifique #', $twig);
        self::assertStringContainsString("s.state == 'done'", $twig);
    }

    public function testLiveStepsAndPersistedCompletedModulesAreBothDisplayed(): void
    {
        $root = dirname(__DIR__, 2);
        $twig = (string) file_get_contents($root.'/templates/workbench/profile.html.twig');
        self::assertStringContainsString("path('profile_oneshot_status'", $twig);
        self::assertStringContainsString('profile-step-progress', $twig);
        self::assertStringContainsString('profile-step-current', $twig);
        self::assertStringContainsString('profile-step-history', $twig);
        self::assertStringContainsString('data.stage', $twig);
        self::assertMatchesRegularExpression('/Number\(data\.job_id\)\s*>\s*minimumJob/', $twig);
        self::assertStringContainsString('selected_profile_run.diagnostics.module_registry.modules', $twig);
        self::assertStringContainsString('Étapes scientifiques terminées', $twig);
        self::assertStringNotContainsString('en attente dans la queue orchestrator', $twig);
    }
}

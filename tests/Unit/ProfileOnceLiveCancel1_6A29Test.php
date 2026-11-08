<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileOnceLiveCancel1_6A29Test extends TestCase
{
    public function testCooperativeCancellationIsScopedToLabProfileOneShot(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = (string)file_get_contents($root.'/src/Controller/ProfileController.php');
        $command = (string)file_get_contents($root.'/src/Command/ProfileOnceCommand.php');
        $worker = (string)file_get_contents($root.'/analysis/worker_entrypoint.py');

        $reflection = new ReflectionMethod(\App\Controller\ProfileController::class, 'cancelOneShot');
        $routes = $reflection->getAttributes(Route::class);
        self::assertCount(1, $routes);
        self::assertSame('/profile/oneshot/{id<\d+>}/cancel', $routes[0]->getArguments()[0]);
        self::assertSame(['POST'], $routes[0]->getArguments()['methods']);
        self::assertStringContainsString("(string)$".'job[\'kind\'] !== \'profile\'', $controller);
        self::assertStringContainsString("(string)($".'job[\'dispatch_owner\'] ?? \'\') !== \'lab\'', $controller);
        self::assertStringContainsString('requestAnalysisJobCancellation($id)', $controller);
        self::assertStringNotContainsString('taskkill', $controller);

        self::assertStringContainsString('PROFILE_ONCE_CANCEL_SIGNAL job=', $command);
        self::assertStringContainsString('markAnalysisJobCancelled($jobId)', $command);
        self::assertStringContainsString("$".'finalJob = $this->database->analysisJob($jobId)', $command);
        self::assertStringContainsString('EZSTUDIO_PROFILE_ONESHOT_CANCEL_FILE', $command);
        self::assertStringContainsString('proc.terminate()', $worker);
        self::assertStringContainsString('proc.kill()', $worker);
        self::assertStringContainsString('proc.poll() is None', $worker);
        self::assertStringNotContainsString('taskkill', $worker);
    }

    public function testLiveObserverIsPersistentAndOldReloadRemoved(): void
    {
        $root = dirname(__DIR__, 2);
        $twig = (string)file_get_contents($root.'/templates/workbench/profile.html.twig');
        self::assertStringContainsString('id="profile-step-observer"', $twig);
        self::assertStringContainsString('profile_oneshot_status', $twig);
        self::assertStringContainsString('profile_once_cancel', $twig);
        self::assertStringContainsString('profile-live-cancel', $twig);
        self::assertStringContainsString('profile-cancel-modal', $twig);
        self::assertStringContainsString('modal.showModal()', $twig);
        self::assertStringNotContainsString('window.confirm(', $twig);
        self::assertStringContainsString('const active=', $twig);
        self::assertStringContainsString('panel.hidden=false', $twig);
        self::assertStringContainsString('window.setTimeout(poll,2000)', $twig);
        self::assertStringNotContainsString('window.setTimeout(pollProfile', $twig);
        self::assertStringNotContainsString("{% if app.request.query.get('oneshot') == 'started' or is_local_running %}", $twig);
        self::assertStringContainsString('selected_profile_run.diagnostics.module_registry.modules', $twig);
    }
}

<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/** Regression: a background response must never replace the complete PROFILE view. */
final class ProfileNoGlobalRefresh1_6A31Test extends TestCase
{
    public function testOneShotProgressObserverIsTheOnlyProfileLiveReloader(): void
    {
        $root = dirname(__DIR__, 2);
        $twig = (string) file_get_contents($root.'/templates/workbench/profile.html.twig');
        self::assertStringContainsString('id="profile-step-observer"', $twig);
        self::assertStringContainsString("path('profile_oneshot_status'", $twig);
        self::assertStringContainsString('window.setTimeout(poll,2000)', $twig);
        self::assertStringContainsString('profile-step-history', $twig);
        self::assertStringContainsString('profile-cancel-modal', $twig);
        self::assertStringNotContainsString('data-profile-live-refresh', $twig);
        self::assertStringNotContainsString('currentWrap.innerHTML = nextWrap.innerHTML;', $twig);
        self::assertStringNotContainsString('refreshProfileContent', $twig);
        self::assertStringNotContainsString('__ezstudioProfileLiveRefresh', $twig);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProfileTerminalNavigation1_6A34Test extends TestCase
{
    private static function template(string $file): string
    {
        $content = file_get_contents(dirname(__DIR__, 2).'/templates/workbench/'.$file);
        self::assertIsString($content);
        return $content;
    }

    public function testCompletedRunNavigatesExactlyOnceWithoutChangingTheQueue(): void
    {
        $profile = self::template('profile.html.twig');
        self::assertSame(1, substr_count($profile, 'window.location.assign(destination)'));
        self::assertStringContainsString("data.job_status==='completed' && data.state==='done'", $profile);
        self::assertStringContainsString('bar.value===100', $profile);
        self::assertStringContainsString('(hadActive || (requested && newer))', $profile);
        self::assertStringContainsString("'run='+encodeURIComponent(String(runId))", $profile);
        self::assertStringContainsString('window.setTimeout(poll,2000)', $profile);
        self::assertStringContainsString("path('profile_oneshot_status'", $profile);
        self::assertStringContainsString("path('profile_once_cancel'", $profile);
    }

    public function testNoUserFacingOneShotTerminologyRemainsInProfileTemplates(): void
    {
        foreach (['profile.html.twig', 'home.html.twig'] as $file) {
            self::assertStringNotContainsString('one-shot', strtolower(self::template($file)), $file);
        }
        self::assertStringContainsString('Analyser le profil', self::template('profile.html.twig'));
        self::assertStringContainsString('Le traitement est inscrit dans la file locale.', self::template('profile.html.twig'));
    }
}

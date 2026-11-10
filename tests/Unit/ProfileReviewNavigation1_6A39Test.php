<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class ProfileReviewNavigation1_6A39Test extends TestCase
{
    public function testCatalogueLinksToExactPersistedRevision(): void
    {
        $root = dirname(__DIR__, 2);
        $home = (string) file_get_contents($root.'/templates/workbench/home.html.twig');
        self::assertStringContainsString('run: song.profile.run_id', $home);
        self::assertStringContainsString('#profile-ai-revision-{{ song.profile_ai_revision_id }}', $home);
        self::assertStringContainsString('Voir la révision #{{ song.profile_ai_revision_id }}', $home);
    }

    public function testReviewPageExposesLatestAndHistoricalTargets(): void
    {
        $root = dirname(__DIR__, 2);
        $profile = (string) file_get_contents($root.'/templates/workbench/profile.html.twig');
        self::assertStringContainsString('id="profile-ai-revisions"', $profile);
        self::assertStringContainsString('id="profile-ai-revision-{{ revision.id }}"', $profile);
        self::assertStringContainsString('profile_external_revisions', $profile);
    }

    public function testReviewButtonReloadsFromLabWithVisibleAnchor(): void
    {
        $root = dirname(__DIR__, 2);
        $button = (string) file_get_contents($root.'/templates/workbench/profile_ai_button.html.twig');
        self::assertStringContainsString("window.location.hash='profile-ai-revisions'", $button);
        self::assertStringContainsString('window.location.reload();', $button);
        self::assertStringNotContainsString('onclick="window.location.reload()"', $button);
        self::assertStringContainsString("refreshLink.hidden=s!=='completed'", $button);
    }

    public function testTwigSyntaxWithoutApplicationSideEffects(): void
    {
        $root = dirname(__DIR__, 2);
        $twig = new Environment(new FilesystemLoader($root.'/templates'), ['strict_variables'=>true]);
        $twig->addFunction(new \Twig\TwigFunction('path', static fn (string $route, array $params=[]): string => '/synthetic'));
        foreach (['workbench/home.html.twig','workbench/profile.html.twig','workbench/profile_ai_button.html.twig'] as $name) {
            $source = (string) file_get_contents($root.'/templates/'.$name);
            $twig->parse($twig->tokenize(new \Twig\Source($source,$name)));
        }
        self::assertTrue(true);
    }
}

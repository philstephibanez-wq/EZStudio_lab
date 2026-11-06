<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Historical test class retained; expectations follow the installed release. */
final class CatalogueDialogAssetVersion1_6A13Test extends TestCase
{
    public function testCatalogLoadsCurrentJavascriptAndNeverFallsBackToNativeDialogs(): void
    {
        $root = dirname(__DIR__, 2);
        $base = file_get_contents($root.'/templates/base.html.twig');
        $js = file_get_contents($root.'/public/js/ezstudio-lab.js');
        $version = trim((string) file_get_contents($root.'/VERSION'));
        self::assertIsString($base);
        self::assertIsString($js);
        self::assertSame('1.6a17', $version, 'Test valid only for the installed LAB release');
        self::assertStringContainsString('/js/ezstudio-lab.js?v='.$version, $base);
        self::assertStringNotContainsString('/js/ezstudio-lab.js?v=r3b29a', $base);
        self::assertStringContainsString('catalogQueueError(', $js);
        self::assertStringContainsString('window.HTMLDialogElement', $js);
        self::assertStringContainsString('dialog.showModal()', $js);
        self::assertStringContainsString('d.resolve(false)', $js);
        foreach (['window.alert(', 'window.confirm(', 'window.prompt(', 'MsgBox(', '$.ui.dialog'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $js);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CatalogueDialogAssetVersion1_6A13Test extends TestCase
{
    public function testCatalogLoadsCurrentJavascriptAndNeverFallsBackToNativeDialogs(): void
    {
        $root = dirname(__DIR__, 2);
        $base = file_get_contents($root.'/templates/base.html.twig');
        $js = file_get_contents($root.'/public/js/ezstudio-lab.js');
        self::assertIsString($base);
        self::assertIsString($js);
        self::assertStringContainsString('/js/ezstudio-lab.js?v=1.6a13', $base);
        self::assertStringNotContainsString('/js/ezstudio-lab.js?v=r3b29a', $base);
        self::assertStringContainsString('catalogQueueError(', $js);
        self::assertStringContainsString('$.ui.dialog', $js);
        foreach (['window.alert(', 'window.confirm(', 'window.prompt(', 'MsgBox('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $js);
        }
    }
}

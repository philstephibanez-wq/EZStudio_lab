<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

/** Preserve fail-closed confirmation and inline errors after jQuery UI dialog retirement. */
final class QueueNoNativeDialogs1_6A12Test extends TestCase
{
    public function testCatalogUsesHtmlDialogAndInlineErrorsOnly(): void
    {
        $path = dirname(__DIR__, 2).'/public/js/ezstudio-lab.js';
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString("document.createElement('dialog')", $source);
        self::assertStringContainsString('dialog.showModal()', $source);
        self::assertStringContainsString('dialog.querySelector', $source);
        self::assertStringContainsString('window.HTMLDialogElement', $source);
        self::assertStringContainsString('d.resolve(false)', $source);
        self::assertStringContainsString("$(dialog).on('cancel'", $source);
        self::assertStringContainsString('catalogQueueError', $source);
        foreach (['window.confirm(', 'window.alert(', 'window.prompt(', 'MsgBox(', '$.ui.dialog'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class QueueNoNativeDialogs1_6A12Test extends TestCase
{
    public function testCatalogUsesHtmlDialogAndInlineErrorsOnly(): void
    {
        $path = dirname(__DIR__, 2) . '/public/js/ezstudio-lab.js';
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString('$.ui.dialog', $source);
        self::assertStringContainsString('catalogQueueError', $source);
        foreach (['window.confirm(', 'window.alert(', 'window.prompt(', 'MsgBox('] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $source);
        }
    }
}

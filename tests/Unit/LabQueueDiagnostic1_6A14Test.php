<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class LabQueueDiagnostic1_6A14Test extends TestCase
{
    public function testPassiveDiagnosticCannotMutateJobsOrRevealSecrets(): void
    {
        $root = dirname(__DIR__, 2);
        $php = file_get_contents($root.'/src/Controller/QueueDiagnosticController.php');
        $script = file_get_contents($root.'/public/js/ezstudio-queue-debug.js');
        $template = file_get_contents($root.'/templates/base.html.twig');
        self::assertIsString($php);
        self::assertIsString($script);
        self::assertIsString($template);
        self::assertStringContainsString('dev_loopback_only', $php);
        self::assertStringContainsString("methods: ['GET']", $php);
        self::assertStringContainsString('global_state', $php);
        self::assertStringNotContainsString('source_path', $php);
        self::assertStringNotContainsString('request_json', $php);
        self::assertStringNotContainsString('last_error', $php);
        self::assertStringNotContainsString('window.alert(', $script);
        self::assertStringNotContainsString('window.confirm(', $script);
        self::assertStringContainsString('ezstudio-queue-diagnostic', $template);
    }
}

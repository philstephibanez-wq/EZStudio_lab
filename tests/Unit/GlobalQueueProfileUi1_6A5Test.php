<?php
declare(strict_types=1);
namespace App\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class GlobalQueueProfileUi1_6A5Test extends TestCase
{
    public function testTerminalScientificStateOverridesFencedQueueState(): void
    {
        $template=(string)file_get_contents(dirname(__DIR__,2).'/templates/workbench/profile.html.twig');
        self::assertStringContainsString("selected_profile_run.state in ['done','error','running'] ? selected_profile_run.state",$template);
        self::assertStringContainsString("selected_profile_run.state not in ['done','error']",$template);
    }
    public function testBridgePersistsProgressWithoutChangingScientificEngine(): void
    {
        $source=(string)file_get_contents(dirname(__DIR__,2).'/src/Service/GlobalQueueProfileBridge.php');
        self::assertStringContainsString('->progressFromJob($labJobId, $lastProgress)', $source);
        self::assertStringContainsString('->completeFromJob($labJobId)', $source);
        self::assertStringContainsString('/analysis/worker_entrypoint.py', $source);
    }
}

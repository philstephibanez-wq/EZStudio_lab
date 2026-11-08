<?php
declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\AnalysisDesktopController;
use App\Service\DistributedAnalysisLeaseService;
use App\Service\ProfileExecutionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Attribute\Route;

final class LabQueueCompatibilityTest extends TestCase
{
    public function testExistingWorkerEndpointsRemainDeclared(): void
    {
        $reflection = new \ReflectionClass(AnalysisDesktopController::class);
        $root = $reflection->getAttributes(Route::class);
        self::assertCount(1, $root);
        self::assertSame('/internal/analysis/desktop', $root[0]->getArguments()[0]);
        foreach (['queue'=>'/jobs/queue', 'claim'=>'/jobs/claim',
            'progress'=>'/jobs/{id<\d+>}/progress',
            'complete'=>'/jobs/{id<\d+>}/complete',
            'fail'=>'/jobs/{id<\d+>}/fail',
            'cancelled'=>'/jobs/{id<\d+>}/cancelled'] as $method => $path) {
            self::assertTrue($reflection->hasMethod($method), $method);
            $routes = $reflection->getMethod($method)->getAttributes(Route::class);
            self::assertCount(1, $routes, $method);
            self::assertSame($path, $routes[0]->getArguments()[0], $method);
        }
    }

    public function testDistributedLeaseOperationsRemainPublic(): void
    {
        $reflection = new \ReflectionClass(DistributedAnalysisLeaseService::class);
        foreach (['eligibleJobs','claim','renewLease','assertLease','releaseLease','reconcileExpiredLeases'] as $method) {
            self::assertTrue($reflection->hasMethod($method), $method);
            self::assertTrue($reflection->getMethod($method)->isPublic(), $method);
        }
    }

    public function testScientificProfileServiceContractRemainsAvailable(): void
    {
        $reflection = new \ReflectionClass(ProfileExecutionService::class);
        foreach (['beginOneShot','jobForRun','progressFromJob','completeFromJob','failFromJob'] as $method) {
            self::assertTrue($reflection->hasMethod($method), $method);
            self::assertTrue($reflection->getMethod($method)->isPublic(), $method);
        }
    }
}

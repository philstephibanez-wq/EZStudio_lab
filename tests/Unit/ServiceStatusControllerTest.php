<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Controller\ServiceStatusController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ServiceStatusControllerTest extends TestCase
{
    public function testRouteAndLocalAccessContract(): void
    {
        $attributes = (new \ReflectionMethod(ServiceStatusController::class, 'index'))->getAttributes(Route::class);
        self::assertCount(1, $attributes);
        $arguments = $attributes[0]->getArguments();
        self::assertSame('/api/v1/service-status', $arguments[0] ?? null);
        self::assertSame(['GET'], $arguments['methods'] ?? null);
        self::assertTrue(ServiceStatusController::allowed('127.0.0.1', '127.0.0.1'));
        self::assertTrue(ServiceStatusController::allowed('localhost', '::1'));
        self::assertFalse(ServiceStatusController::allowed('example.com', '127.0.0.1'));
        self::assertFalse(ServiceStatusController::allowed('127.0.0.1', '192.0.2.1'));
    }

    public function testMinimalSchemaNeverInventsWorkerHealth(): void
    {
        $result = ServiceStatusController::payload('1.5a5', '2026-11-04T10:00:00Z');
        self::assertSame('ezs.service-status.v1', $result['schema']);
        self::assertSame('ezstudio_lab', $result['application']);
        self::assertSame('lab', $result['instance']);
        self::assertSame('1.5a5', $result['version']);
        self::assertSame('OK', $result['state']);
        self::assertSame('UNKNOWN', $result['activity']);
        self::assertSame('2026-11-04T10:00:00Z', $result['captured_utc']);
        self::assertIsArray($result['components']);
        self::assertSame('UNKNOWN', $result['components'][2]['health']);
        self::assertSame('UNKNOWN', $result['components'][3]['health']);
        self::assertSame('UNKNOWN', $result['components'][4]['health']);
        self::assertArrayNotHasKey('token', $result);
        self::assertArrayNotHasKey('secret', $result);
    }

    public function testInvalidVersionIsAnExplicitFailure(): void
    {
        foreach (['', 'v1.5a5', '1.5a(5)', 'garbage'] as $version) {
            $result = ServiceStatusController::payload($version, '2026-11-04T10:00:00Z');
            self::assertSame('KO', $result['state']);
            self::assertSame('ERROR', $result['health']);
            self::assertNull($result['version']);
        }
    }

    public function testEndpointIsReadOnlyAndForbiddenOutsideLoopback(): void
    {
        $request = Request::create('http://example.com/api/v1/service-status', 'GET', [], [], [], ['REMOTE_ADDR' => '192.0.2.1']);
        $response = (new ServiceStatusController())->index($request);
        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame(['error'=>'forbidden'], json_decode((string) $response->getContent(), true));
        $src = (string) file_get_contents(dirname(__DIR__, 2).'/src/Controller/ServiceStatusController.php');
        foreach (['proc_open', 'Start-Process', 'Stop-Process', 'dispatch(', 'file_put_contents', 'shell_exec'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $src);
        }
    }
}

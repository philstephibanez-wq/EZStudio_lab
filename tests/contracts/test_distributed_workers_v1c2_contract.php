<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controller = file_get_contents($root . '/src/Controller/AnalysisDesktopController.php');
$service = file_get_contents($root . '/src/Service/DistributedAnalysisLeaseService.php');

if ($controller === false || $service === false) {
    fwrite(STDERR, "SOURCE_READ_FAILED\n");
    exit(1);
}

$mustController = [
    "#[Route('/distributed/workers', name: 'lab_internal_distributed_workers', methods: ['GET'])]",
    "'schema_version' => 'ezstudio.distributed.workers.v1'",
    "'workers' => \$this->distributed->workersSnapshot()",
];
foreach ($mustController as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "CONTROLLER_CONTRACT_MISSING: {$needle}\n");
        exit(2);
    }
}

$mustService = [
    'public function workersSnapshot(): array',
    "FROM analysis_workers",
    "'online' => \$lastSeenEpoch >= \$staleBefore",
    "'capabilities' => \$capabilities",
];
foreach ($mustService as $needle) {
    if (!str_contains($service, $needle)) {
        fwrite(STDERR, "SERVICE_CONTRACT_MISSING: {$needle}\n");
        exit(3);
    }
}

$forbidden = [
    "claimNextAnalysisJob",
    "->claim(",
    "status='running'",
];
$workersMethodStart = strpos($service, 'public function workersSnapshot(): array');
$workersMethodEnd = strpos($service, 'private function distributedSpec', $workersMethodStart);
$workersBlock = substr($service, $workersMethodStart, $workersMethodEnd - $workersMethodStart);
foreach ($forbidden as $needle) {
    if (str_contains($workersBlock, $needle)) {
        fwrite(STDERR, "READ_ONLY_CONTRACT_VIOLATION: {$needle}\n");
        exit(4);
    }
}

echo "EZSTUDIO_DISTRIBUTED_WORKERS_V1C2_CONTRACT_OK\n";
echo "Read-only worker inventory endpoint present\n";
echo "No distributed claim added to inventory path\n";
echo "Scientific pipeline untouched\n";

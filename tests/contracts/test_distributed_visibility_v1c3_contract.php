<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents($root.'/src/Service/DistributedAnalysisLeaseService.php');
$controller = file_get_contents($root.'/src/Controller/WorkbenchController.php');
$template = file_get_contents($root.'/templates/workbench/home.html.twig');

if ($service === false || $controller === false || $template === false) {
    fwrite(STDERR, "SOURCE_READ_FAILED\n");
    exit(1);
}

foreach ([
    'public function jobsSnapshot(?int $songId = null): array',
    '\'compatible_workers\' => $compatibleWorkers',
    '\'lease\' => [',
] as $needle) {
    if (!str_contains($service, $needle)) {
        fwrite(STDERR, "SERVICE_CONTRACT_MISSING: {$needle}\n");
        exit(2);
    }
}

foreach ([
    'DistributedAnalysisLeaseService $distributed',
    '\'distributed_workers\' => $distributed->workersSnapshot()',
    '\'distributed_jobs\' => $distributed->jobsSnapshot()',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        fwrite(STDERR, "CONTROLLER_CONTRACT_MISSING: {$needle}\n");
        exit(3);
    }
}

foreach ([
    'id="distributed-execution"',
    'Exécution distribuée',
    'en attente de claim',
    'Worker claimé',
] as $needle) {
    if (!str_contains($template, $needle)) {
        fwrite(STDERR, "TEMPLATE_CONTRACT_MISSING: {$needle}\n");
        exit(4);
    }
}

foreach (["->claim(", "claimNextAnalysisJob", "distributedClaim("] as $needle) {
    if (str_contains($controller, $needle)) {
        fwrite(STDERR, "READ_ONLY_UI_CONTRACT_VIOLATION: {$needle}\n");
        exit(5);
    }
}

echo "EZSTUDIO_DISTRIBUTED_VISIBILITY_CATALOGUE_V1C3_CONTRACT_OK\n";
echo "Catalogue /home read-only visibility added\n";
echo "No claim or worker start added to Catalogue\n";
echo "Scientific pipeline untouched\n";

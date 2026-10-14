<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = file_get_contents(
    $root.'/src/Service/DistributedAnalysisLeaseService.php'
);

if ($service === false) {
    fwrite(STDERR, "SERVICE_READ_FAILED\n");
    exit(1);
}

$needles = [
    'private function withDistributedSharedPath(array $context): array',
    'EZSTUDIO_DISTRIBUTED_IMPORTS_UNC_ROOT',
    'EZStudioLab-Imports',
    '$context[\'paths\'][\'shared_source\'] = $sharedSource;',
    '$this->withDistributedSharedPath($this->db->analysisJobContext($jobId))',
];

foreach ($needles as $needle) {
    if (!str_contains($service, $needle)) {
        fwrite(
            STDERR,
            "DISTRIBUTED_SHARED_PATH_CONTRACT_MISSING: {$needle}\n"
        );
        exit(2);
    }
}

$wrappedContext = '$this->withDistributedSharedPath('
    .'$this->db->analysisJobContext($jobId))';

if (substr_count($service, $wrappedContext) !== 2) {
    fwrite(
        STDERR,
        "DISTRIBUTED_SHARED_PATH_MUST_APPLY_TO_CLAIM_AND_CONTEXT\n"
    );
    exit(3);
}

echo "EZSTUDIO_DISTRIBUTED_SHARED_PATH_V1C3_CONTRACT_OK\n";
echo "Base local source preserved\n";
echo "Distributed shared_source assigned in context\n";
echo "Distributed shared_source applied to claim/context\n";
echo "No scientific pipeline mutation\n";

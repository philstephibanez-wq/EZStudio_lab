<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$service = file_get_contents($root.'/src/Service/ProfileExternalReviewService.php');
$controller = file_get_contents($root.'/src/Controller/AnalysisDesktopController.php');

foreach ([
    'profile_external_revisions',
    'ezstudio.profile-external-review.v1',
    'public function queue(int $limit = 100): array',
    'public function save(array $payload): array',
    'profile_external_review_completed_requires_sources',
    "sr2.item='profile'",
    "sr2.state='done'",
    "er.status='completed'",
] as $needle) {
    if (!str_contains($service, $needle)) {
        throw new RuntimeException('profile_external_review_service_contract_missing:'.$needle);
    }
}

foreach ([
    'ProfileExternalReviewService',
    '/profile/reviews/queue',
    '/profile/reviews',
    'profileReviewQueue',
    'profileReviewSubmit',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        throw new RuntimeException('profile_external_review_controller_contract_missing:'.$needle);
    }
}

echo "EZSTUDIO_PROFILE_EXTERNAL_REVIEW_V1_1A_CONTRACT_OK\n";

<?php
declare(strict_types=1);
$service = file_get_contents(dirname(__DIR__).'/src/Service/ProfileExternalReviewService.php');
if ($service === false) throw new RuntimeException('service_unreadable');
if (!preg_match('/public function assertionsForRun\\(int \\$runId\\): array(.*?)\\n    }/s', $service, $matches)) throw new RuntimeException('method_missing');
$method = $matches[1];
foreach (["'final_assertions'=>".'$assertions', "'final_assertions_count'=>count(".'$assertions'.")", "'final_assertions_schema'=>'ezstudio.profile-final-assertions.v1'"] as $needle) {
    if (!str_contains($method,$needle)) throw new RuntimeException('missing_return:'.$needle);
}
echo "PROFILE_FINAL_ASSERTIONS_RETURN_OK\n";

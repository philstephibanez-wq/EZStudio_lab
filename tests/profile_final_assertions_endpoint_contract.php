<?php
declare(strict_types=1);
$src = file_get_contents(dirname(__DIR__).'/src/Controller/AnalysisDesktopController.php');
if ($src === false) throw new RuntimeException('missing_controller');
foreach (['profileFinalAssertions', 'lab_internal_profile_final_assertions', 'assertionsForRun', "'final_assertions_count'"] as $needle) {
    if (!str_contains($src, $needle)) throw new RuntimeException('missing:'.$needle);
}
echo "PROFILE_FINAL_ASSERTIONS_ENDPOINT_OK\n";

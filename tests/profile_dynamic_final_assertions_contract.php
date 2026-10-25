<?php
declare(strict_types=1);
$src=file_get_contents(dirname(__DIR__).'/src/Service/ProfileExternalReviewService.php');
foreach (["'final_assertions_schema'","'final_assertions_count'", "'final_assertions'=>".chr(36)."assertions", "'final_musical_assertions'", chr(36)."emit(".chr(36)."dimension.':'. ".chr(36)."index"] as $needle) {
    if (!str_contains($src,$needle)) throw new RuntimeException('contract_missing:'.$needle);
}
if (!str_contains($src,"No diagnostics, engine parameters, checkpoints, paths or stems cross this boundary")) {
    throw new RuntimeException('privacy_boundary_missing');
}
echo "PROFILE_DYNAMIC_FINAL_ASSERTIONS_1_4A19_OK\n";
."assertions","'final_musical_assertions'","$"."emit($"."dimension.':'.$"."index"] as $needle) {
    if (!str_contains($src,$needle)) throw new RuntimeException('contract_missing:'.$needle);
}
if (!str_contains($src,"No diagnostics, engine parameters, checkpoints, paths or stems cross this boundary")) {
    throw new RuntimeException('privacy_boundary_missing');
}
echo "PROFILE_DYNAMIC_FINAL_ASSERTIONS_1_4A19_OK\n";

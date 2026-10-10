<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$path = $root.'/templates/workbench/profile.html.twig';
if (!is_file($path)) {
    throw new RuntimeException('profile_template_missing');
}
$text = file_get_contents($path);
if ($text === false) {
    throw new RuntimeException('profile_template_unreadable');
}

$legacyExpr = "attribute(e, 'ge' ~ 'ne')";
if (!str_contains($text, "e.module is defined")) {
    throw new RuntimeException('embedding_module_compat_missing');
}
if (!str_contains($text, $legacyExpr)) {
    throw new RuntimeException('embedding_legacy_source_compat_missing');
}
if (str_contains($text, '{{ e.module }} : rang')) {
    throw new RuntimeException('unsafe_embedding_module_access_remains');
}

if (!str_contains($text, "s.module is defined")) {
    throw new RuntimeException('source_module_compat_missing');
}

echo "TERMINOLOGY_PROFILE_HISTORY_COMPAT_HOTFIX_CONTRACT_OK\n";
echo "Historical PROFILE source rows remain readable without rewriting persisted runs\n";

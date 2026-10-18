<?php
declare(strict_types=1);

$root=dirname(__DIR__);
$registry=file_get_contents($root.'/src/Service/AnalysisRunRegistry.php');
$controller=file_get_contents($root.'/src/Controller/ScientificRunController.php');
$runTwig=file_get_contents($root.'/templates/scientific_run/run.html.twig');

foreach ([
    'CREATE TABLE IF NOT EXISTS scientific_runs',
    'CREATE TABLE IF NOT EXISTS scientific_run_parents',
    'CREATE TABLE IF NOT EXISTS scientific_artifacts',
    'CREATE TABLE IF NOT EXISTS scientific_run_inputs',
    'artifact_id',
    'sha256',
    'lineage',
    'compareAcrossSongs',
] as $needle) {
    if (!str_contains($registry,$needle)) {
        throw new RuntimeException('registry_missing:'.$needle);
    }
}

foreach ([
    "Route('/scientific-runs/song/",
    "Route('/scientific-runs/run/",
    "Route('/scientific-runs/compare/",
] as $needle) {
    if (!str_contains($controller,$needle)) {
        throw new RuntimeException('controller_missing:'.$needle);
    }
}

foreach ([
    'Ascendance',
    'Entrées sélectionnées',
    'Artefacts',
    'Descendance',
] as $needle) {
    if (!str_contains($runTwig,$needle)) {
        throw new RuntimeException('twig_missing:'.$needle);
    }
}

echo "EZSTUDIO_SCIENTIFIC_RUN_DAG_R3B3_CONTRACT_OK\n";

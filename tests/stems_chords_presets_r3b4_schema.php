<?php
declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use App\Service\Database;
use App\Service\AnalysisRunRegistry;

$root=dirname(__DIR__);
$db=new Database($root.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'benchmark.sqlite');
$runRegistry=new AnalysisRunRegistry($db);
$runRegistry->ensureSchema();

$required=[
 'scientific_runs','scientific_run_parents','scientific_artifacts',
 'scientific_run_inputs','scientific_run_aliases','scientific_run_labels'
];

$stmt=$db->pdo()->prepare(
 "SELECT name FROM sqlite_master WHERE type='table' AND name=?"
);
foreach($required as $table){
 $stmt->execute([$table]);
 if($stmt->fetchColumn()===false) throw new RuntimeException('missing_table:'.$table);
}

echo "EZSTUDIO_STEMS_CHORDS_PRESETS_R3B4_SCHEMA_OK\n";

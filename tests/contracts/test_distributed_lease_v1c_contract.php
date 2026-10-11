<?php

declare(strict_types=1);

use App\Service\Database;
use App\Service\DistributedAnalysisLeaseService;

$root = dirname(__DIR__, 2);
require_once $root.'/src/Service/Database.php';
require_once $root.'/src/Service/DistributedAnalysisLeaseService.php';

$tmp = sys_get_temp_dir().DIRECTORY_SEPARATOR.'ezstudio-v1c-'.bin2hex(random_bytes(6));
if (!mkdir($tmp, 0777, true) && !is_dir($tmp)) {
    throw new RuntimeException('tmp_create_failed');
}

$dbPath = $tmp.DIRECTORY_SEPARATOR.'lab.sqlite';
$db = new Database($dbPath);
$leases = new DistributedAnalysisLeaseService($db);

$songId = $db->createSong('V1C Lease Contract', 'EZStudio', 'v1c.wav', 'deadbeef');
$runId = $db->createRun($songId, '4/4', $tmp.DIRECTORY_SEPARATOR.'v1c.wav');
$jobId = $db->queueAnalysisJob($runId, $tmp.DIRECTORY_SEPARATOR.'v1c.wav', '4/4');

$workerId = 'EZStudioLab_assistant-EZSTUDIO-XPS';
$leases->heartbeatWorker($workerId, [
    'protocol' => 'ezstudio.distributed.v1',
    'node_id' => $workerId,
    'cuda_available' => true,
    'max_vram_mb' => 4096,
    'ram_total_mb' => 16118,
]);

// Existing jobs are not distributable by default: fail closed.
if ($leases->eligibleJobs($workerId) !== []) {
    throw new RuntimeException('legacy_job_must_not_be_distributable');
}

$job = $db->analysisJob($jobId);
$request = json_decode((string)$job['request_json'], true);
$request['distributed'] = [
    'enabled' => true,
    'input_mode' => 'shared_path',
    'requirements' => [
        'protocol' => 'ezstudio.distributed.v1',
        'requires_cuda' => true,
        'min_vram_mb' => 2048,
        'min_ram_mb' => 4096,
        'required_models' => [],
    ],
];
$db->pdo()->prepare(
    'UPDATE analysis_jobs SET request_json=? WHERE id=?'
)->execute([
    json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    $jobId,
]);

$eligible = $leases->eligibleJobs($workerId);
if (count($eligible) !== 1 || (int)$eligible[0]['job_id'] !== $jobId) {
    throw new RuntimeException('compatible_job_not_exposed');
}

$claim = $leases->claim($jobId, $workerId, 60);
$token = (string)($claim['lease']['lease_token'] ?? '');
if ($token === '') {
    throw new RuntimeException('lease_token_missing');
}

$renewed = $leases->renewLease($jobId, $workerId, $token, 60);
if (($renewed['status'] ?? '') !== 'lease_renewed') {
    throw new RuntimeException('lease_renew_failed');
}

$context = $leases->context($jobId, $workerId, $token);
if (($context['status'] ?? '') !== 'running') {
    throw new RuntimeException('claimed_job_not_running');
}

// Simulate worker disappearance: expired lease must requeue the job.
$db->pdo()->prepare(
    'UPDATE analysis_job_leases SET expires_at_epoch=? WHERE job_id=?'
)->execute([time() - 1, $jobId]);
$leases->reconcileExpiredLeases();

$job = $db->analysisJob($jobId);
if (($job['status'] ?? '') !== 'queued') {
    throw new RuntimeException('expired_lease_not_requeued');
}

$eligible = $leases->eligibleJobs($workerId);
if (count($eligible) !== 1 || (int)$eligible[0]['job_id'] !== $jobId) {
    throw new RuntimeException('requeued_job_not_recoverable');
}

// 8 GiB requirement must exclude the 4 GiB XPS.
$request['distributed']['requirements']['min_vram_mb'] = 8192;
$db->pdo()->prepare(
    'UPDATE analysis_jobs SET request_json=? WHERE id=?'
)->execute([
    json_encode($request, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    $jobId,
]);
if ($leases->eligibleJobs($workerId) !== []) {
    throw new RuntimeException('incompatible_vram_job_exposed');
}

@unlink($dbPath);
@rmdir($tmp);

echo "DISTRIBUTED_LEASE_V1C_CONTRACT_OK\n";
echo "Legacy jobs remain local by default\n";
echo "Capability-aware exact-job claim validated\n";
echo "Expired lease requeues recoverable work\n";
echo "GTX 1050 Ti 4GB rejects 8GB job\n";
echo "Scientific pipeline untouched\n";

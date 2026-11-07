<?php

declare(strict_types=1);

namespace App\Service;

/** One-shot, explicit, fail-closed bridge. Never changes the scientific engine. */
final class GlobalQueueProfileBridge
{
    private const ENDPOINT = 'http://127.0.0.1:8790/api/v2/queue/executor/';

    public function __construct(
        private readonly Database $db,
        private readonly ProfileExecutionService $profile,
        private readonly GlobalQueuePersistedReceipt $receipt,
    ) {}

    /** @return array{global_job_id:int, lab_job_id:int, scientific_run_id:int} */
    public function eligible(int $labJobId): array
    {
        if ($labJobId < 1) {
            throw new \InvalidArgumentException('invalid_lab_job_id');
        }
        $statement = $this->db->pdo()->prepare(<<<'SQL'
SELECT j.id,j.kind,j.status,j.dispatch_owner,j.scientific_run_id,j.song_id,
       j.source_path,j.request_json,
       o.state AS admission_state,o.global_job_id,
       r.item AS run_item,r.state AS run_state,r.song_id AS run_song_id,
       s.audio_sha256 AS audio_hash
FROM analysis_jobs j
JOIN global_queue_admission_outbox o ON o.job_id=j.id
JOIN scientific_runs r ON r.id=j.scientific_run_id
JOIN songs s ON s.id=j.song_id
WHERE j.id=?
SQL);
        $statement->execute([$labJobId]);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)
            || $row['kind'] !== 'profile'
            || $row['status'] !== 'queued'
            || $row['dispatch_owner'] !== 'orchestrator'
            || $row['admission_state'] !== 'admitted'
            || (int) $row['global_job_id'] < 1
            || (int) $row['scientific_run_id'] < 1
            || $row['run_item'] !== 'profile'
            || $row['run_state'] !== 'queued'
            || (int) $row['run_song_id'] !== (int) $row['song_id']
            || !is_file((string) $row['source_path'])) {
            throw new \DomainException('profile_global_job_not_eligible');
        }
        $request = json_decode((string) $row['request_json'], true);
        if (!is_array($request)
            || ($request['output_contract'] ?? null) !== 'ezstudio.profile.v1'
            || ($request['audio_hash'] ?? null) !== $row['audio_hash']
            || (int) ($request['scientific_run_id'] ?? 0) !== (int) $row['scientific_run_id']
            || !is_string($request['output_path'] ?? null)
            || $request['output_path'] === '') {
            throw new \DomainException('profile_global_request_not_eligible');
        }
        return [
            'lab_job_id' => $labJobId,
            'global_job_id' => (int) $row['global_job_id'],
            'scientific_run_id' => (int) $row['scientific_run_id'],
        ];
    }

    /**
     * Execute a single explicitly selected NEW Orchestrator-owned PROFILE job.
     * On ambiguity, deliberately retain its lease; an operator must inspect before recovery.
     * No automatic retry, no cancellation of foreign processes, no legacy claim.
     */
    public function runOne(int $labJobId, string $python): array
    {
        if (self::setting('EZSTUDIO_GLOBAL_QUEUE_EXECUTOR') !== '1') {
            throw new \RuntimeException('global_profile_bridge_disabled');
        }
        $token = self::setting('EZS_QUEUE_EXECUTOR_TOKEN');
        if (!preg_match('#^[A-Za-z0-9._~+/=\-]{32,256}$#D', $token)) {
            throw new \RuntimeException('executor_token_invalid');
        }
        $pythonReal = realpath($python);
        if (!is_string($pythonReal) || !is_file($pythonReal)
            || !in_array(strtolower(pathinfo($pythonReal, PATHINFO_EXTENSION)), ['exe', ''], true)) {
            throw new \RuntimeException('python_executable_missing');
        }
        $job = $this->eligible($labJobId);
        $project = dirname(__DIR__, 2);
        $worker = $project . '/analysis/worker_entrypoint.py';
        $launcher = $project . '/analysis/queue_profile_jobobject_launcher.py';
        if (!is_file($launcher)) {
            throw new \RuntimeException('profile_jobobject_launcher_missing');
        }
        if (!is_file($worker)) {
            throw new \RuntimeException('canonical_worker_missing');
        }
        $context = $this->db->analysisJobContext($labJobId);
        if (($context['protocol'] ?? null) !== 'ezscore.analysis-job.v2'
            || ($context['kind'] ?? null) !== 'profile'
            || (int) ($context['job_id'] ?? 0) !== $labJobId) {
            throw new \DomainException('canonical_worker_context_invalid');
        }
        if (PHP_OS_FAMILY !== 'Windows' || !class_exists('COM')) {
            throw new \RuntimeException('windows_process_attestation_unavailable');
        }
        try {
            $locator = new \COM('WbemScripting.SWbemLocator');
            $locator->ConnectServer('.', 'root\\cimv2');
        } catch (\Throwable $error) {
            throw new \RuntimeException('windows_process_inventory_unavailable', 0, $error);
        }
        $claimBody = $this->request('claim', [
            'resource_class' => 'gpu', 'lease_seconds' => 120,
            'job_id' => $job['global_job_id'], 'kind' => 'profile',
        ], $token);
        $claim = $claimBody['job'] ?? null;
        if ($claim === null) {
            // Orchestrator has not granted the GPU; leave LAB queued, never spawn.
            return ['lab_job_id' => $labJobId, 'global_job_id' => $job['global_job_id'], 'state' => 'waiting_gpu'];
        }
        if (!is_array($claim) || (int) ($claim['id'] ?? 0) !== $job['global_job_id']
            || ($claim['producer'] ?? null) !== 'lab'
            || ($claim['external_id'] ?? null) !== 'analysis_job:'.$labJobId
            || ($claim['kind'] ?? null) !== 'profile'
            || ($claim['resource_class'] ?? null) !== 'gpu'
            || (int) ($claim['claim_generation'] ?? 0) < 1
            || preg_match('/^[a-f0-9]{64}$/D', (string) ($claim['lease_token'] ?? '')) !== 1) {
            // A claim mismatch is an incident; its lease must not be guessed or completed.
            throw new \DomainException('global_profile_claim_mismatch');
        }
        $generation = (int) $claim['claim_generation'];
        $lease = (string) $claim['lease_token'];
        $temporary = 'H:/temp';
        $jobDir = rtrim($temporary, '\\/') . '/global-bridge/' . $labJobId . '/' . $generation;
        if (!is_dir($jobDir) && !mkdir($jobDir, 0700, true) && !is_dir($jobDir)) {
            throw new \RuntimeException('job_envelope_directory_missing');
        }
        $jobFile = $jobDir.'/job.json';
        if (file_put_contents($jobFile, json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) === false) {
            throw new \RuntimeException('job_envelope_write_failed');
        }
        $environment = getenv();
        if (!is_array($environment)) $environment = [];
        foreach ($_ENV as $key => $value) {
            if (is_string($key) && is_string($value)) $environment[$key] = $value;
        }
        $environment = GlobalQueueProfileEnvironment::build($project, $environment);
        $pipes = [];
        $child = proc_open(
            [$pythonReal, $launcher, '--worker', $worker, '--job-file', $jobFile],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes,
            $project, $environment, ['bypass_shell' => true]
        );
        if (!is_resource($child)) {
            throw new \RuntimeException('profile_spawn_failed_lease_retained');
        }
        fclose($pipes[0]);
        $childStatus = proc_get_status($child);
        $pid = (int) ($childStatus['pid'] ?? 0);
        if ($pid < 1 || !$childStatus['running']) {
            throw new \RuntimeException('profile_child_identity_unavailable_lease_retained');
        }
        $identity = $this->observeSpawn($pid, $pythonReal, $launcher);
        $ownerAck = $this->request('ownership', [
            'job_id' => $job['global_job_id'],
            'claim_generation' => $generation,
            'lease_token' => $lease,
            'node' => $identity['node'],
            'pid' => $identity['pid'],
            'created_at' => $identity['created_at'],
            'instance_id' => $identity['instance_id'],
        ], $token);
        if (($ownerAck['registered'] ?? null) !== true
            || (int) ($ownerAck['job_id'] ?? 0) !== $job['global_job_id']) {
            throw new \RuntimeException('global_ownership_ack_invalid_lease_retained');
        }
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $nextHeartbeat = 0;
        $lastProgress = null;
        $lastPersistedProgress = -1;
        $log = '';
        $exit = -1;
        try {
            // No reconciling foreign PIDs: after an executor crash the global lease
            // remains RUNNING and requires separately attested manual recovery.
            while (true) {
                $status = proc_get_status($child);
                $log .= (string) stream_get_contents($pipes[1]);
                $log .= (string) stream_get_contents($pipes[2]);
                if (strlen($log) > 65536) $log = substr($log, -65536);
                $now = time();
                if ($now >= $nextHeartbeat && $status['running']) {
                    $progressFile = $context['paths']['progress_file'] ?? null;
                    if (is_string($progressFile) && is_file($progressFile)) {
                        $raw = file_get_contents($progressFile);
                        $progress = is_string($raw) ? json_decode($raw, true) : null;
                        if (is_array($progress) && is_int($progress['percent'] ?? null)) {
                            $lastProgress = max($lastProgress ?? 0, min(99, max(0, $progress['percent'])));
                        }
                    }
                    if ($lastProgress !== null && $lastProgress > $lastPersistedProgress) {
                        $this->profile->progressFromJob($labJobId, $lastProgress);
                        $lastPersistedProgress = $lastProgress;
                    }
                    $this->request('heartbeat', [
                        'job_id' => $job['global_job_id'], 'claim_generation' => $generation,
                        'lease_token' => $lease, 'lease_seconds' => 120,
                        'progress_percent' => $lastProgress,
                    ], $token);
                    $nextHeartbeat = $now + 15;
                }
                if (!$status['running']) {
                    $exit = (int) $status['exitcode'];
                    break;
                }
                usleep(250000);
            }
        } finally {
            foreach ([1, 2] as $stream) {
                if (isset($pipes[$stream]) && is_resource($pipes[$stream])) fclose($pipes[$stream]);
            }
            $closeCode = proc_close($child);
            if ($exit < 0) $exit = $closeCode;
        }
        if ($exit !== 0) {
            // Keep the actual child exception on LAB only. Never log credentials or expose it via HTTP.
            $diagnosticLog = $project.'/var/logs/jobs/job-'.sprintf('%08d', $labJobId).'-bridge.log';
            $diagnosticDirectory = dirname($diagnosticLog);
            if (is_dir($diagnosticDirectory) || @mkdir($diagnosticDirectory, 0700, true)) {
                $diagnosticText = substr($log, -65536);
                foreach ([$token, self::setting('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'),
                    self::setting('EZS_QUEUE_LAB_TOKEN')] as $secret) {
                    if (is_string($secret) && strlen($secret) >= 8) {
                        $diagnosticText = str_replace($secret, '[REDACTED]', $diagnosticText);
                    }
                }
                @file_put_contents($diagnosticLog,
                    gmdate('c').' lab_job_id='.$labJobId.' exit_code='.$exit."\n".$diagnosticText."\n",
                    FILE_APPEND | LOCK_EX);
            }
            // The Job Object launcher has exited: no surviving scientific child
            // may be inferred from the process return code alone on other paths.
            // Persist LAB failure first, then attest it to the canonical queue.
            $this->profile->failFromJob($labJobId, 'profile_worker_exit_'.$exit);
            $receipt = $this->receipt->failedProfile(
                $labJobId, $job['global_job_id'], $generation
            );
            $ack = $this->request('finish', [
                'job_id' => $job['global_job_id'], 'claim_generation' => $generation,
                'lease_token' => $lease, 'receipt' => $receipt,
            ], $token);
            if (($ack['state'] ?? null) !== 'failed'
                || (int) ($ack['job_id'] ?? 0) !== $job['global_job_id']) {
                throw new \RuntimeException('global_failure_finish_ack_missing_lease_retained');
            }
            return [
                'lab_job_id' => $labJobId,
                'global_job_id' => $job['global_job_id'],
                'state' => 'failed',
                'analysis_returncode' => $exit,
            ];
        }
        // Persist through the unchanged LAB scientific service, not a new code path.
        $this->profile->completeFromJob($labJobId);
        $receipt = $this->receipt->completedProfile(
            $labJobId, $job['global_job_id'], $generation
        );
        $ack = $this->request('finish', [
            'job_id' => $job['global_job_id'], 'claim_generation' => $generation,
            'lease_token' => $lease, 'receipt' => $receipt,
        ], $token);
        if (($ack['state'] ?? null) !== 'completed' || (int) ($ack['job_id'] ?? 0) !== $job['global_job_id']) {
            throw new \RuntimeException('global_finish_ack_missing');
        }
        return ['lab_job_id' => $labJobId, 'global_job_id' => $job['global_job_id'], 'state' => 'completed'];
    }

    private static function setting(string $name): string
    {
        foreach ([getenv($name), $_SERVER[$name] ?? null, $_ENV[$name] ?? null] as $value) {
            if (is_string($value) && $value !== '') return $value;
        }
        return $name === 'EZS_QUEUE_EXECUTOR_TOKEN'
            ? GlobalQueueAdmission::localSetting($name) : '';
    }

    /** Observe the child independently of proc_open's PID declaration. */
    private function observeSpawn(int $pid, string $python, string $worker): array
    {
        try {
            $locator = new \COM('WbemScripting.SWbemLocator');
            $service = $locator->ConnectServer('.', 'root\\cimv2');
            $rows = $service->ExecQuery(
                'SELECT ProcessId,CreationDate,ExecutablePath,CommandLine FROM Win32_Process WHERE ProcessId='.$pid
            );
            foreach ($rows as $row) {
                $observedPid = (int) $row->ProcessId;
                $created = trim((string) $row->CreationDate);
                $executable = realpath((string) $row->ExecutablePath);
                $command = (string) $row->CommandLine;
                if ($observedPid !== $pid || $created === ''
                    || !is_string($executable)
                    || strcasecmp($executable, $python) !== 0
                    || stripos($command, basename($worker)) === false) {
                    break;
                }
                return [
                    'node' => gethostname() ?: 'unknown-local-node',
                    'pid' => $pid,
                    'created_at' => $created,
                    'instance_id' => bin2hex(random_bytes(16)),
                ];
            }
        } catch (\Throwable $error) {
            throw new \RuntimeException('windows_child_attestation_failed_lease_retained', 0, $error);
        }
        throw new \RuntimeException('windows_child_identity_mismatch_lease_retained');
    }

    private function request(string $path, array $body, string $token): array
    {
        if (!in_array($path, ['claim', 'heartbeat', 'finish', 'ownership'], true)) {
            throw new \InvalidArgumentException('unsupported_global_bridge_operation');
        }
        $response = @file_get_contents(self::ENDPOINT.$path, false, stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer ".$token."\r\nConnection: close\r\n",
            'content' => json_encode($body, JSON_THROW_ON_ERROR),
            'timeout' => 5, 'ignore_errors' => true,
            'follow_location' => 0, 'max_redirects' => 0,
        ]]));
        $headers = $http_response_header ?? [];
        $code = isset($headers[0]) && preg_match('~^HTTP/\S+\s+(\d{3})~', $headers[0], $m) ? (int) $m[1] : 0;
        if ($code !== 200 || !is_string($response) || strlen($response) > 8192) {
            throw new \RuntimeException('global_bridge_'.$path.'_http_'.$code);
        }
        $result = json_decode($response, true);
        if (!is_array($result)) throw new \RuntimeException('global_bridge_'.$path.'_bad_response');
        return $result;
    }
}

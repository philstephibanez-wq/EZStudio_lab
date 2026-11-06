<?php

declare(strict_types=1);

namespace App\Service;

/** Reconciles an already-attested canonical terminal state, never guesses a worker exit. */
final class GlobalQueueTerminalSync
{
    private const BASE = 'http://127.0.0.1:8790/api/v2/queue/jobs/';

    public function __construct(private readonly Database $db) {}

    public function cancelOrReconcile(int $labJobId): array
    {
        $job = $this->db->analysisJob($labJobId);
        if (!is_array($job) || $job['dispatch_owner'] !== 'orchestrator') {
            throw new \DomainException('global_job_not_owned');
        }
        $sql = 'SELECT state,global_job_id FROM global_queue_admission_outbox WHERE job_id=?';
        $statement = $this->db->pdo()->prepare($sql);
        $statement->execute([$labJobId]);
        $outbox = $statement->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($outbox) || $outbox['state'] !== 'admitted' || (int)$outbox['global_job_id'] < 1) {
            throw new \DomainException('global_job_not_admitted');
        }
        $globalId = (int) $outbox['global_job_id'];
        $token = GlobalQueueAdmission::localSetting('EZS_QUEUE_LAB_TOKEN');
        if (!preg_match('#^[A-Za-z0-9._~+/=\-]{32,256}$#D', $token)) {
            throw new \DomainException('global_queue_token_unavailable');
        }
        $global = $this->request($globalId, 'GET', $token);
        $this->assertIdentity($global, $globalId, $labJobId, (string)$job['kind']);
        $state = (string)$global['state'];
        // A running process must be stopped AND independently attested before
        // global cancellation. Never signal or adopt an unidentified PID.
        if ($state === 'running') throw new \DomainException('active_executor_stop_attestation_required');
        if ($state === 'queued') {
            if ((string)$job['status'] !== 'queued') throw new \DomainException('local_global_state_conflict');
            $global = $this->request($globalId, 'POST', $token);
            $this->assertIdentity($global, $globalId, $labJobId, (string)$job['kind']);
            $state = (string)$global['state'];
        }
        if (in_array($state, ['failed', 'cancelled'], true)) {
            $this->db->reconcileGlobalTerminal($labJobId, $globalId, $state);
            return $this->db->analysisJob($labJobId) ?? $job;
        }
        if ($state === 'completed') throw new \DomainException('completed_global_job_requires_scientific_result_check');
        throw new \DomainException('unknown_global_state');
    }

    private function assertIdentity(array $row, int $id, int $lab, string $kind): void
    {
        if ((int)($row['id'] ?? 0) !== $id || ($row['producer'] ?? '') !== 'lab'
            || ($row['external_id'] ?? '') !== 'analysis_job:'.$lab
            || ($row['kind'] ?? '') !== $kind
            || !in_array(($row['state'] ?? ''), ['queued','running','completed','failed','cancelled'], true)) {
            throw new \DomainException('global_job_identity_mismatch');
        }
    }

    private function request(int $id, string $method, string $token): array
    {
        $url = self::BASE.$id.($method === 'POST' ? '/cancel' : '');
        $headers = "Authorization: Bearer ".$token."\r\nConnection: close\r\n";
        $context = stream_context_create(['http' => [
            'method' => $method, 'header' => $headers, 'content' => '',
            'timeout' => 3, 'ignore_errors' => true, 'follow_location' => 0,
            'max_redirects' => 0,
        ]]);
        $response = @file_get_contents($url, false, $context);
        $headersReceived = $http_response_header ?? [];
        $httpStatus = 0;
        if (isset($headersReceived[0]) && preg_match('~^HTTP/\\S+\\s+(\\d{3})~', $headersReceived[0], $m)) {
            $httpStatus = (int)$m[1];
        }
        if (!is_string($response) || strlen($response)>8192) throw new \DomainException('global_queue_unreachable');
        $data = json_decode($response, true);
        if ($httpStatus !== 200 || !is_array($data)) {
            throw new \DomainException(is_array($data) && isset($data['error'])
                && preg_match('/^[a-z0-9_]+$/D',(string)$data['error']) ? (string)$data['error'] : 'global_queue_http_error');
        }
        $expected = $method === 'POST' ? 'ezs.queue.cancel.v1' : 'ezs.queue.read.v1';
        if (($data['schema'] ?? '') !== $expected || !is_array($data['job'] ?? null)) {
            throw new \DomainException('invalid_global_queue_response');
        }
        return $data['job'];
    }
}

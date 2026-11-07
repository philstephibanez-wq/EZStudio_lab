<?php

declare(strict_types=1);

namespace App\Service;

/** Technical admission only. The LAB SQLite job remains the sole scientific owner. */
final class GlobalQueueAdmission
{
    private const URL = 'http://127.0.0.1:8790/api/v2/queue/jobs';
    private const KINDS = ['profile', 'stems', 'chords_scientific'];

    public function __construct(private readonly Database $db) {}

    /** Explicit activation; a missing secret must never silently select a legacy worker. */
    public static function owner(string $kind = 'profile'): string
    {
        // Unrouted STEMS and CHORDS retain their proven LAB worker until separately integrated.
        if (!in_array($kind, ['profile', 'stems', 'chords_scientific'], true)) {
            throw new \InvalidArgumentException('unsupported_queue_owner_kind');
        }
        if ($kind !== 'profile') return 'lab';
        if (self::setting('EZSTUDIO_GLOBAL_QUEUE_ADMISSION') !== '1') {
            return 'lab';
        }
        $token = self::token();
        if (preg_match('#^[A-Za-z0-9._~+/=\-]{32,256}$#D', $token) !== 1) {
            throw new \RuntimeException('global_queue_lab_token_missing_or_invalid');
        }
        return 'orchestrator';
    }

    /** Symfony Dotenv may populate superglobals without calling putenv(). */
    private static function token(): string
    {
        $native = getenv('EZS_QUEUE_LAB_TOKEN');
        if (is_string($native) && $native !== '') {
            return $native;
        }
        $server = $_SERVER['EZS_QUEUE_LAB_TOKEN'] ?? null;
        if (is_string($server) && $server !== '') {
            return $server;
        }
        $env = $_ENV['EZS_QUEUE_LAB_TOKEN'] ?? null;
        return is_string($env) && $env !== '' ? $env : self::localSetting('EZS_QUEUE_LAB_TOKEN');
    }

    private static function setting(string $key): string
    {
        foreach ([getenv($key), $_SERVER[$key] ?? null, $_ENV[$key] ?? null] as $value) {
            if (is_string($value) && $value !== '') return $value;
        }
        return self::localSetting($key);
    }

    /** Read only explicitly allowlisted LAB settings; no generic dotenv import. */
    public static function localSetting(string $key): string
    {
        if (!in_array($key, ['EZSTUDIO_GLOBAL_QUEUE_ADMISSION', 'EZS_QUEUE_LAB_TOKEN', 'EZS_QUEUE_EXECUTOR_TOKEN', 'EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'], true)) return '';
        $path = dirname(__DIR__, 2).'/.env.local';
        if (!is_file($path) || is_link($path)) return '';
        $lines = @file($path, FILE_IGNORE_NEW_LINES);
        if (!is_array($lines)) return '';
        $result = null;
        foreach ($lines as $line) {
            if (!is_string($line) || preg_match('/^\s*'.preg_quote($key, '/').'\s*=\s*(.*?)\s*$/D', $line, $m) !== 1) continue;
            if ($result !== null) return '';
            $value = trim($m[1]);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                if ($value[strlen($value) - 1] !== $value[0]) return '';
                $value = substr($value, 1, -1);
            }
            if ($key === 'EZSTUDIO_GLOBAL_QUEUE_ADMISSION'
                ? !in_array($value, ['0', '1'], true)
                : preg_match('#^[A-Za-z0-9._~+/=\-]{32,256}$#D', $value) !== 1) return '';
            $result = $value;
        }
        return $result ?? '';
    }
    /** This envelope intentionally contains no audio, song metadata, paths or presets. */
    public static function envelope(int $jobId, string $kind): array
    {
        if ($jobId < 1 || !in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('unsupported_global_admission');
        }
        return [
            'external_id' => 'analysis_job:'.$jobId,
            'idempotency_key' => 'lab.analysis_job.'.$jobId,
            'kind' => $kind,
            'resource_class' => 'gpu',
            'priority' => 50,
        ];
    }

    /**
     * Does not throw for network errors: the SQLite outbox persists the retry.
     * Never mutates the scientific job status or makes a local claim.
     */
    public function publish(int $jobId): bool
    {
        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare(<<<'SQL'
SELECT j.id,j.kind,j.status,j.dispatch_owner,o.state
FROM analysis_jobs j
JOIN global_queue_admission_outbox o ON o.job_id=j.id
WHERE j.id=?
SQL);
        $stmt->execute([$jobId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row || $row['dispatch_owner'] !== 'orchestrator' || $row['status'] !== 'queued') {
            return false;
        }
        if ($row['state'] === 'admitted') {
            return true;
        }
        $envelope = self::envelope($jobId, (string) $row['kind']);
        $token = self::token();
        if (preg_match('#^[A-Za-z0-9._~+/=\-]{32,256}$#D', $token) !== 1) {
            $this->recordFailure($jobId, 'token_unavailable');
            return false;
        }
        $json = json_encode($envelope, JSON_THROW_ON_ERROR);
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer ".$token."\r\nConnection: close\r\n",
            'content' => $json,
            'timeout' => 3,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
        ]]);
        $response = @file_get_contents(self::URL, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = 0;
        if (isset($responseHeaders[0]) && preg_match('~^HTTP/\S+\s+(\d{3})~', $responseHeaders[0], $matches)) {
            $status = (int) $matches[1];
        }
        if ($status !== 200 || !is_string($response) || strlen($response) > 8192) {
            $this->recordFailure($jobId, $status === 0 ? 'connection_failure' : 'http_'.$status);
            return false;
        }
        $body = json_decode($response, true);
        $global = is_array($body) ? ($body['job'] ?? null) : null;
        if (!is_array($global)
            || ($body['schema'] ?? '') !== 'ezs.queue.admission.v1'
            || ($global['producer'] ?? '') !== 'lab'
            || ($global['external_id'] ?? '') !== $envelope['external_id']
            || ($global['kind'] ?? '') !== $envelope['kind']
            || ($global['resource_class'] ?? '') !== $envelope['resource_class']
            || (int) ($global['priority'] ?? -1) !== $envelope['priority']
            || (int) ($global['id'] ?? 0) < 1
            || !in_array($global['state'] ?? '', ['queued', 'running', 'completed', 'failed'], true)) {
            $this->recordFailure($jobId, 'invalid_admission_response');
            return false;
        }
        $stmt = $pdo->prepare(<<<'SQL'
UPDATE global_queue_admission_outbox
SET state='admitted', global_job_id=?,attempts=attempts+1,last_error=NULL,updated_at=?
WHERE job_id=? AND state='pending'
SQL);
        $stmt->execute([(int) $global['id'], gmdate('c'), $jobId]);
        return true;
    }

    private function recordFailure(int $jobId, string $code): void
    {
        $stmt = $this->db->pdo()->prepare(<<<'SQL'
UPDATE global_queue_admission_outbox
SET attempts=attempts+1,last_error=?,updated_at=?
WHERE job_id=? AND state='pending'
SQL);
        $stmt->execute([$code, gmdate('c'), $jobId]);
    }

    /** Newly routed pending requests only. No historical LAB jobs are selected. */
    public function pending(int $limit = 50): array
    {
        $stmt = $this->db->pdo()->prepare(<<<'SQL'
SELECT o.job_id,o.attempts,o.last_error
FROM global_queue_admission_outbox o
JOIN analysis_jobs j ON j.id=o.job_id
WHERE o.state='pending' AND j.dispatch_owner='orchestrator' AND j.status='queued'
ORDER BY o.job_id ASC LIMIT ?
SQL);
        $stmt->bindValue(1, max(1, min(100, $limit)), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}

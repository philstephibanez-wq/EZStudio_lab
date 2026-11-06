<?php

declare(strict_types=1);

namespace App\Service;

/** Read-only scientific proof for an already admitted PROFILE job. No HTTP or queue transition. */
final class GlobalQueuePersistedReceipt
{
    public function __construct(private readonly Database $db) {}

    /**
     * Generate a completed-result receipt only after the scientific run and artifact
     * have been persisted. The caller must be a trusted LAB execution component;
     * never expose this method directly to an untrusted HTTP client.
     */
    public function completedProfile(int $labJobId, int $globalJobId, int $claimGeneration): array
    {
        if ($labJobId < 1 || $globalJobId < 1 || $claimGeneration < 1) {
            throw new \InvalidArgumentException('invalid_receipt_identity');
        }
        $key = self::attestationKey();
        if (strlen($key) < 32 || strlen($key) > 256) {
            throw new \RuntimeException('persistence_attestation_not_configured');
        }

        $stmt = $this->db->pdo()->prepare(<<<'SQL'
SELECT j.id,j.kind,j.status,j.dispatch_owner,j.scientific_run_id,j.song_id,j.request_json,
       o.state AS admission_state,o.global_job_id,
       r.id AS run_id,r.item AS run_item,r.state AS run_state,r.song_id AS run_song_id,r.finished_at,
       s.audio_sha256 AS song_audio_hash
FROM analysis_jobs j
JOIN global_queue_admission_outbox o ON o.job_id=j.id
JOIN scientific_runs r ON r.id=j.scientific_run_id
JOIN songs s ON s.id=j.song_id
WHERE j.id=?
SQL);
        $stmt->execute([$labJobId]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($job)
            || $job['dispatch_owner'] !== 'orchestrator'
            || $job['kind'] !== 'profile'
            || $job['status'] !== 'queued'
            || $job['admission_state'] !== 'admitted'
            || (int) $job['global_job_id'] !== $globalJobId
            || $job['run_item'] !== 'profile'
            || $job['run_state'] !== 'done'
            || !is_string($job['finished_at']) || $job['finished_at'] === ''
            || (int) $job['run_song_id'] !== (int) $job['song_id']) {
            throw new \DomainException('profile_persistence_not_attested');
        }
        $request = json_decode((string) $job['request_json'], true);
        if (!is_array($request)
            || (int) ($request['scientific_run_id'] ?? 0) !== (int) $job['run_id']
            || ($request['output_contract'] ?? null) !== 'ezstudio.profile.v1'
            || ($request['audio_hash'] ?? null) !== $job['song_audio_hash']) {
            throw new \DomainException('profile_request_not_attested');
        }
        $stmt = $this->db->pdo()->prepare(<<<'SQL'
SELECT path,sha256 FROM scientific_artifacts WHERE run_id=? AND role='profile_json'
SQL);
        $stmt->execute([(int) $job['run_id']]);
        $artifacts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (count($artifacts) !== 1) {
            throw new \DomainException('profile_artifact_not_unique');
        }
        $artifact = $artifacts[0];
        $path = (string) $artifact['path'];
        $digest = (string) $artifact['sha256'];
        if ($path === '' || $path !== (string) ($request['output_path'] ?? '')
            || preg_match('/^[a-f0-9]{64}$/D', $digest) !== 1
            || !is_file($path) || !is_readable($path)) {
            throw new \DomainException('profile_artifact_not_attested');
        }
        $raw = file_get_contents($path);
        if (!is_string($raw) || !is_array($document = json_decode($raw, true))
            || ($document['schema'] ?? null) !== 'ezstudio.profile.v1'
            || (isset($document['audio_sha256'])
                && $document['audio_sha256'] !== $job['song_audio_hash'])) {
            throw new \DomainException('profile_artifact_invalid');
        }
        $actual = hash('sha256', $raw);
        if (!hash_equals($digest, $actual)) {
            throw new \DomainException('profile_artifact_digest_mismatch');
        }

        $receipt = [
            'schema' => 'ezs.lab.persisted-result.v1',
            'producer' => 'lab',
            'external_id' => 'analysis_job:'.$labJobId,
            'job_id' => $globalJobId,
            'claim_generation' => $claimGeneration,
            'result' => 'completed',
            'artifact_sha256' => $digest,
        ];
        $canonical = implode("\n", [
            $receipt['schema'], $receipt['producer'], $receipt['external_id'],
            (string) $globalJobId, (string) $claimGeneration,
            $receipt['result'], $digest,
        ]);
        $receipt['signature'] = hash_hmac('sha256', $canonical, $key);
        return $receipt;
    }

    /**
     * Failure receipt is only issued after LAB has persistently recorded the
     * scientific error. It cannot attest an arbitrary process exit by itself.
     */
    public function failedProfile(int $labJobId, int $globalJobId, int $claimGeneration): array
    {
        if ($labJobId < 1 || $globalJobId < 1 || $claimGeneration < 1) {
            throw new \InvalidArgumentException('invalid_receipt_identity');
        }
        $key = self::attestationKey();
        if (strlen($key) < 32 || strlen($key) > 256) {
            throw new \RuntimeException('persistence_attestation_not_configured');
        }
        $stmt = $this->db->pdo()->prepare(<<<'SQL'
SELECT j.id,j.kind,j.status,j.dispatch_owner,j.scientific_run_id,
       o.state AS admission_state,o.global_job_id,
       r.item AS run_item,r.state AS run_state
FROM analysis_jobs j
JOIN global_queue_admission_outbox o ON o.job_id=j.id
JOIN scientific_runs r ON r.id=j.scientific_run_id
WHERE j.id=?
SQL);
        $stmt->execute([$labJobId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!is_array($row)
            || $row['dispatch_owner'] !== 'orchestrator'
            || $row['kind'] !== 'profile'
            || $row['status'] !== 'queued'
            || $row['admission_state'] !== 'admitted'
            || (int) $row['global_job_id'] !== $globalJobId
            || (int) $row['scientific_run_id'] < 1
            || $row['run_item'] !== 'profile'
            || $row['run_state'] !== 'error') {
            throw new \DomainException('profile_failure_not_attested');
        }
        $receipt = [
            'schema' => 'ezs.lab.persisted-result.v1',
            'producer' => 'lab',
            'external_id' => 'analysis_job:'.$labJobId,
            'job_id' => $globalJobId,
            'claim_generation' => $claimGeneration,
            'result' => 'failed',
            'artifact_sha256' => null,
        ];
        $canonical = implode("\n", [
            $receipt['schema'], $receipt['producer'], $receipt['external_id'],
            (string) $globalJobId, (string) $claimGeneration,
            'failed', '-',
        ]);
        $receipt['signature'] = hash_hmac('sha256', $canonical, $key);
        return $receipt;
    }

    private static function attestationKey(): string
    {
        foreach ([getenv('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'),
            $_SERVER['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'] ?? null,
            $_ENV['EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY'] ?? null] as $value) {
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }
        return GlobalQueueAdmission::localSetting('EZS_QUEUE_LAB_RESULT_ATTESTATION_KEY');
    }
}

<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Message\ProfileQueueJob;
use App\Service\Database;
use App\Service\GlobalQueueAdmission;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/** Receiver-only adapter over the canonical LAB jobs/outbox. Never persists duplicate messages. */
final class ProfileOutboxTransport implements TransportInterface
{
    /** @var array<int, float> */
    private array $notBefore = [];

    public function __construct(
        private readonly Database $db,
        private readonly GlobalQueueAdmission $admission,
        private readonly int $minimumJobId,
    ) {}

    public function get(): iterable
    {
        if (self::setting('EZSTUDIO_GLOBAL_QUEUE_EXECUTOR') !== '1') {
            throw new \RuntimeException('profile_executor_disabled');
        }
        $stmt = $this->db->pdo()->prepare(<<<'SQL'
SELECT j.id,o.state
FROM analysis_jobs j
JOIN global_queue_admission_outbox o ON o.job_id=j.id
JOIN scientific_runs r ON r.id=j.scientific_run_id
WHERE j.id > :floor AND j.kind='profile' AND j.status='queued'
 AND j.dispatch_owner='orchestrator' AND r.item='profile' AND r.state='queued'
 AND o.state IN ('pending','admitted')
ORDER BY j.id ASC LIMIT 50
SQL);
        $stmt->bindValue(':floor', $this->minimumJobId, \PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $id = (int) $row['id'];
            if (($this->notBefore[$id] ?? 0.0) > microtime(true)) continue;
            // Backoff affects this worker only; the durable job is never acknowledged or lost.
            $this->notBefore[$id] = microtime(true) + 8.0;
            if ($row['state'] === 'pending' && !$this->admission->publish($id)) continue;
            return [new Envelope(new ProfileQueueJob($id), [new TransportMessageIdStamp((string) $id)])];
        }
        return [];
    }

    public function ack(Envelope $envelope): void
    {
        // The canonical scientific run state and the Orchestrator lease are authoritative.
    }

    public function reject(Envelope $envelope): void
    {
        $message = $envelope->getMessage();
        if ($message instanceof ProfileQueueJob) {
            // A failure after claim retains the lease: do NOT automatically retry this job.
            $this->notBefore[$message->labJobId] = INF;
        }
    }

    public function send(Envelope $envelope): Envelope
    {
        throw new \LogicException('profile_outbox_is_receiver_only');
    }

    private static function setting(string $key): string
    {
        foreach ([getenv($key), $_SERVER[$key] ?? null, $_ENV[$key] ?? null] as $value) {
            if (is_string($value) && $value !== '') return $value;
        }
        return '';
    }
}

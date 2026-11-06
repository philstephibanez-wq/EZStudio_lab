<?php

declare(strict_types=1);

namespace App\Messenger;

use App\Service\Database;
use App\Service\GlobalQueueAdmission;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/** Adapts the existing LAB outbox to Symfony Messenger. Does not create another queue. */
final class ProfileOutboxTransportFactory implements TransportFactoryInterface
{
    public function __construct(
        private readonly Database $db,
        private readonly GlobalQueueAdmission $admission,
    ) {}

    public function supports(string $dsn, array $options): bool
    {
        return $dsn === 'ezs-profile://local';
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        if (!$this->supports($dsn, $options)) {
            throw new \InvalidArgumentException('unsupported_profile_transport');
        }
        $min = self::setting('EZSTUDIO_PROFILE_WORKER_MIN_JOB_ID');
        if (!preg_match('/^(0|[1-9][0-9]*)$/D', $min)) {
            throw new \RuntimeException('profile_worker_baseline_required');
        }
        return new ProfileOutboxTransport($this->db, $this->admission, (int) $min);
    }

    private static function setting(string $key): string
    {
        foreach ([getenv($key), $_SERVER[$key] ?? null, $_ENV[$key] ?? null] as $value) {
            if (is_string($value) && $value !== '') return $value;
        }
        return '';
    }
}

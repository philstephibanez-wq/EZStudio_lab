<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProfileQueueJob;
use App\Service\GlobalQueueProfileBridge;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProfileQueueJobHandler
{
    public function __construct(private readonly GlobalQueueProfileBridge $bridge) {}

    public function __invoke(ProfileQueueJob $job): void
    {
        $python = self::setting('EZSTUDIO_PROFILE_PYTHON');
        if ($python === '' || !is_file($python)) {
            throw new \RuntimeException('profile_python_not_configured');
        }
        // Exactly the existing canonically fenced Orchestrator claim and PROFILE worker.
        // When the global GPU slot is occupied, runOne returns 'waiting_gpu', no spawn.
        $result = $this->bridge->runOne($job->labJobId, $python);
        if (!in_array($result['state'] ?? '', ['completed','failed','waiting_gpu'], true)) {
            throw new \RuntimeException('profile_bridge_unexpected_result');
        }
    }

    private static function setting(string $key): string
    {
        foreach ([getenv($key), $_SERVER[$key] ?? null, $_ENV[$key] ?? null] as $value) {
            if (is_string($value) && $value !== '') return $value;
        }
        return '';
    }
}

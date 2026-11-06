<?php

declare(strict_types=1);

namespace App\Message;

/** Reference only: no audio, model, path or scientific configuration. */
final readonly class ProfileQueueJob
{
    public function __construct(public int $labJobId) {}
}

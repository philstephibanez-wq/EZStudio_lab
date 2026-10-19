<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Expose the canonical product version, independently from the Git revision.
 */
final class LabVersionExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'VERSION';
        $raw = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('lab_version_unreadable');
        }
        $version = trim($raw);
        if (!preg_match('/^\d+\.\d+(?:[a-z]+)?$/D', $version)) {
            throw new \RuntimeException('lab_version_invalid');
        }

        return ['lab_version' => $version];
    }
}

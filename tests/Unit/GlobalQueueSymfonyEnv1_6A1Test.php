<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\GlobalQueueAdmission;
use PHPUnit\Framework\TestCase;

final class GlobalQueueSymfonyEnv1_6A1Test extends TestCase
{
    public function testSymfonyTokenIsAcceptedWithoutPutenvAndFlagRemainsOff(): void
    {
        $key = 'EZS_QUEUE_LAB_TOKEN';
        $flag = 'EZSTUDIO_GLOBAL_QUEUE_ADMISSION';
        $native = getenv($key);
        $nativeFlag = getenv($flag);
        $server = $_SERVER[$key] ?? null;
        $env = $_ENV[$key] ?? null;
        try {
            putenv($key);
            putenv($flag);
            unset($_SERVER[$key], $_ENV[$key]);
            self::assertSame('lab', GlobalQueueAdmission::owner());
            putenv($flag.'=1');
            $this->expectException(\RuntimeException::class);
            GlobalQueueAdmission::owner();
        } finally {
            $native === false ? putenv($key) : putenv($key.'='.$native);
            $nativeFlag === false ? putenv($flag) : putenv($flag.'='.$nativeFlag);
            if ($server === null) { unset($_SERVER[$key]); } else { $_SERVER[$key] = $server; }
            if ($env === null) { unset($_ENV[$key]); } else { $_ENV[$key] = $env; }
        }
    }

    public function testSymfonyServerTokenCanBeReadWhenEnabledExplicitly(): void
    {
        $key = 'EZS_QUEUE_LAB_TOKEN';
        $flag = 'EZSTUDIO_GLOBAL_QUEUE_ADMISSION';
        $native = getenv($key);
        $nativeFlag = getenv($flag);
        $server = $_SERVER[$key] ?? null;
        $env = $_ENV[$key] ?? null;
        try {
            putenv($key);
            putenv($flag);
            unset($_ENV[$key]);
            $_SERVER[$key] = str_repeat('c', 48);
            putenv($flag.'=1');
            self::assertSame('orchestrator', GlobalQueueAdmission::owner());
        } finally {
            $native === false ? putenv($key) : putenv($key.'='.$native);
            $nativeFlag === false ? putenv($flag) : putenv($flag.'='.$nativeFlag);
            if ($server === null) { unset($_SERVER[$key]); } else { $_SERVER[$key] = $server; }
            if ($env === null) { unset($_ENV[$key]); } else { $_ENV[$key] = $env; }
        }
    }
}

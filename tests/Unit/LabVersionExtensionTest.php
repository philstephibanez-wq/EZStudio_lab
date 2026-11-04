<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Twig\LabVersionExtension;
use PHPUnit\Framework\TestCase;

final class LabVersionExtensionTest extends TestCase
{
    public function testCurrentVersionIsExposedToTwig(): void
    {
        $version = trim((string) file_get_contents(dirname(__DIR__, 2).'/VERSION'));
        self::assertMatchesRegularExpression('/^\d+\.\d+(?:[a-z]+\d*)?$/D', $version);
        self::assertSame(['lab_version' => $version], (new LabVersionExtension())->getGlobals());
    }

    public function testCanonicalVersionPatternSupportsNumericFixWithoutParentheses(): void
    {
        $pattern = '/^\d+\.\d+(?:[a-z]+\d*)?$/D';
        foreach (['1.5a0', '1.5a1', '1.5a', '1.7a0', '4.2d21', '2.0'] as $valid) {
            self::assertSame(1, preg_match($pattern, $valid), $valid);
        }
        foreach (['1.5a(0)', '1.5a-0', 'v1.5a0', '1.5a0garbage', ''] as $invalid) {
            self::assertSame(0, preg_match($pattern, $invalid), $invalid);
        }
    }
}

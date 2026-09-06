<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\Tests;

use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function preg_match;

/**
 * The numbers this standard mandates are stated twice: once as prose a human reads, and
 * once as configuration a tool executes. Nothing kept the two in step.
 *
 * The pipeline asserts that a consumer does not weaken these values, and it reads them
 * from the configuration in this package — so the configuration is what the estate
 * actually enforces, and `CONVENTIONS.md` is what a reader believes. A change to one and
 * not the other is invisible everywhere else: the fleet would follow the config and the
 * document would keep stating the old bar.
 *
 * This is a test rather than a CI step on purpose. It is an assertion about two files in
 * this repository, it needs nothing installed, and it runs with `composer test`.
 *
 * @internal
 *
 * @coversNothing
 */
final class MandatedValuesTest extends TestCase
{
    private const string CONVENTIONS = __DIR__ . '/../CONVENTIONS.md';

    public function testTheStatedPhpstanLevelIsTheConfiguredOne(): void
    {
        self::assertSame(
            $this->matched('/`level:\s*(\S+?)`/', self::CONVENTIONS),
            $this->matched('/^\s*level:\s*(\S+)/m', __DIR__ . '/../phpstan.neon.dist'),
            'CONVENTIONS.md §Static analysis and phpstan.neon.dist state different levels',
        );
    }

    public function testTheStatedMutationFloorIsTheConfiguredOne(): void
    {
        self::assertSame(
            $this->matched('/`minCoveredMsi:\s*(\d+)`/', self::CONVENTIONS),
            $this->matched('/"minCoveredMsi"\s*:\s*(\d+)/', __DIR__ . '/../infection.json5.dist'),
            'CONVENTIONS.md §Testing form and infection.json5.dist state different floors',
        );
    }

    /**
     * The first capture of $pattern in $file.
     *
     * Failing here rather than returning null is the point: a value that stopped being
     * stated is the same defect as one that changed, and a test comparing null to null
     * would pass on a document that says nothing at all.
     */
    private function matched(string $pattern, string $file): string
    {
        $contents = file_get_contents($file);
        self::assertIsString($contents, "cannot read {$file}");
        self::assertSame(1, preg_match($pattern, $contents, $found), "{$pattern} matches nothing in {$file}");

        return $found[1];
    }
}

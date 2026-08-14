<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\Tests;

use PHPUnit\Framework\TestCase;
use Rak200\CodingStandardPhp\ScanCommand;

/**
 * @internal
 *
 * @coversNothing
 */
final class ScanCommandTest extends TestCase
{
    public function testArgumentsAreTheCommandTheRfcDecided(): void
    {
        // Asserted whole rather than piecemeal, and that is the point of this file. The
        // command drifted in four repositories because it lived as a string in four
        // manifests with nothing comparing them to the decision. Here a mutant that drops
        // a pack, reorders them, or rewrites a flag has this to answer to.
        $this->assertSame(
            [
                'semgrep',
                'scan',
                '--config=p/php',
                '--config=p/security-audit',
                '--error',
                '--sarif',
                '--output=semgrep.sarif',
                '--metrics=off',
                '.',
            ],
            ScanCommand::arguments(),
        );
    }

    public function testTheSecondPackIsPresentBecauseTheFirstAloneMissesTheCanary(): void
    {
        // Named on its own, because losing this pack is the defect that shipped. `p/php`
        // is tuned for precision and answers a planted `eval($_POST[…])` with 0 findings
        // — measured, on 28 files, at every severity. `p/security-audit` carries the
        // `audit` subcategory where the matching rule lives.
        $this->assertContains('--config=p/security-audit', ScanCommand::arguments());
    }

    public function testItAsksForANonZeroExitAndNotForASeverityFilter(): void
    {
        // The two flags that were swapped for each other. `--error` is what turns a
        // finding into an exit code; `--severity` only decides which rules run, so
        // substituting it left the enforcing step comparing a value that could not
        // differ from zero. A gate that cannot fail is the failure this estate keeps
        // finding, and this assertion is the one that would have caught it.
        $arguments = ScanCommand::arguments();

        $this->assertContains('--error', $arguments);

        foreach ($arguments as $argument) {
            $this->assertStringStartsNotWith('--severity', $argument);
        }
    }

    public function testTheReportIsWrittenWhereThePublishingStepLooksForIt(): void
    {
        // The pipeline's middle step uploads by this exact name, under a `hashFiles()`
        // guard — so a renamed report does not fail, it silently uploads nothing.
        $this->assertSame('semgrep.sarif', ScanCommand::REPORT);
        $this->assertContains('--output=semgrep.sarif', ScanCommand::arguments());
    }
}

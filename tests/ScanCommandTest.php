<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Rak200\CodingStandardPhp\ScanCommand;

/**
 * @internal
 */
#[CoversClass(ScanCommand::class)]
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
                '--config=r/php.lang.security.eval-use',
                '--error',
                '--sarif',
                '--output=semgrep.sarif',
                '--metrics=off',
                '.',
            ],
            ScanCommand::arguments(),
        );
    }

    public function testTheRuleThatCatchesTheCanaryIsNamedOnItsOwn(): void
    {
        // This assertion used to name `p/security-audit`, on the belief that the pack
        // carried `eval-use`. It does not: the pack serves 225 rules and not one is PHP,
        // so it cannot change what a PHP scan finds. The rule that does is named directly,
        // because `p/php` excludes it — `confidence: LOW`, where that pack is MEDIUM and
        // HIGH throughout, which is the same tuning that makes it precise.
        //
        // Measured with a planted canary, one config apart on the same tree: 0 findings
        // and exit 0 without this entry, 1 finding and exit 1 with it.
        $this->assertContains('--config=r/php.lang.security.eval-use', ScanCommand::arguments());
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

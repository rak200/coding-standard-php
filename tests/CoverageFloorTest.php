<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rak200\CodingStandardPhp\CoverageFloor;
use Rak200\CodingStandardPhp\FloorException;

use function file_put_contents;
use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function mkdir;
use function rmdir;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * @internal
 *
 * @coversNothing
 */
final class CoverageFloorTest extends TestCase
{
    private string $directory;

    private string $report;

    private string $floorFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/coverage-floor-' . uniqid();
        mkdir($this->directory);
        $this->report = $this->directory . '/coverage.xml';
        $this->floorFile = $this->directory . '/.coverage-floor';
    }

    protected function tearDown(): void
    {
        foreach ([$this->report, $this->floorFile] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        rmdir($this->directory);
    }

    public function testParseFloorReadsANumber(): void
    {
        $this->assertSame(98.0, CoverageFloor::parseFloor("98\n", '.coverage-floor'));
    }

    public function testParseFloorReadsAFractionalFloorAroundWhitespace(): void
    {
        $this->assertSame(97.5, CoverageFloor::parseFloor(' 97.5 ', '.coverage-floor'));
    }

    #[DataProvider('nonNumericProvider')]
    public function testParseFloorRejectsTextThatIsNotANumber(string $text): void
    {
        // The message names the file, because the reader is looking at CI output and has
        // no other way to tell which of several `.coverage-floor` files is at fault.
        $this->expectException(FloorException::class);
        $this->expectExceptionMessage('the/floor does not contain a number');

        CoverageFloor::parseFloor($text, 'the/floor');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonNumericProvider(): iterable
    {
        yield 'a word' => ['high'];

        yield 'empty' => [''];

        yield 'whitespace only' => ["   \n"];

        // The `(float)` cast this replaced read 98 out of this and passed. A floor file
        // is written by a human or by a ratchet script, and either way trailing rubbish
        // means the value is not what someone intended.
        yield 'a number with a tail' => ['98abc'];

        yield 'a percentage sign' => ['98%'];
    }

    public function testParseFloorRejectsAFloorBelowTheHardFloorAndReportsBothNumbers(): void
    {
        $this->expectExceptionMessage('.coverage-floor says 94.99, below the hard floor of 95');

        CoverageFloor::parseFloor('94.99', '.coverage-floor');
    }

    public function testParseFloorAcceptsExactlyTheHardFloor(): void
    {
        $this->assertSame(
            CoverageFloor::HARD_FLOOR,
            CoverageFloor::parseFloor((string) CoverageFloor::HARD_FLOOR, '.coverage-floor'),
        );
    }

    public function testParseCloverReadsStatementTotalsAndComputesAPercentage(): void
    {
        $this->assertSame(
            ['total' => 200, 'covered' => 197, 'percent' => 98.5],
            CoverageFloor::parseClover(self::clover(200, 197), 'coverage.xml'),
        );
    }

    public function testParseCloverRoundsToTwoDecimalsRatherThanTruncating(): void
    {
        // 1624/1659 is 97.8902…, the real figure that first exposed the pcov/xdebug
        // one-statement discrepancy on rak200/utils.
        $this->assertSame(97.89, CoverageFloor::parseClover(self::clover(1659, 1624), 'r.xml')['percent']);
    }

    public function testParseCloverRoundsToTwoDecimalsAndNotToThree(): void
    {
        // 2/3 is 66.666…, which differs at the second and third decimal. The 1624/1659
        // fixture above does not: its third decimal is a zero, so it reads the same
        // whether the precision is 2 or 3 and a mutant that widened it survived. A
        // rounding assertion needs a number that can tell the two apart.
        $this->assertSame(66.67, CoverageFloor::parseClover(self::clover(3, 2), 'r.xml')['percent']);
    }

    public function testParseCloverReadsTheProjectTotalRatherThanAFileScopedOne(): void
    {
        // Real clover nests a <metrics> under every <file>. Addressing project->metrics
        // is what keeps this from reporting one file's numbers as the whole suite's —
        // the TypeScript twin reaches the same element with a regex over the first
        // match, so both must agree about which element that is.
        $xml = sprintf(
            '<coverage><project><metrics statements="200" coveredstatements="197"/>'
            . '<package><file><metrics statements="3" coveredstatements="0"/></file></package>'
            . '</project></coverage>',
        );

        $this->assertSame(200, CoverageFloor::parseClover($xml, 'r.xml')['total']);
    }

    public function testParseCloverToleratesOtherAttributesBetweenTheTwoItReads(): void
    {
        // Clover writers order attributes as they like. Asserted because the fixtures
        // above happen to put the two this parser needs side by side, and the TypeScript
        // twin — which matches them with one regex — had a surviving mutant for exactly
        // this gap.
        $xml = '<coverage><project><metrics files="1" statements="200" elements="7" '
            . 'coveredelements="7" coveredstatements="197"/></project></coverage>';

        $this->assertSame(
            ['total' => 200, 'covered' => 197, 'percent' => 98.5],
            CoverageFloor::parseClover($xml, 'r.xml'),
        );
    }

    #[DataProvider('notACloverReportProvider')]
    public function testParseCloverRejectsADocumentWithNoProjectMetrics(string $xml): void
    {
        $this->expectException(FloorException::class);
        $this->expectExceptionMessage('r.xml is not a clover report with project metrics');

        CoverageFloor::parseClover($xml, 'r.xml');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notACloverReportProvider(): iterable
    {
        yield 'no project element' => ['<coverage/>'];

        yield 'a project with no metrics' => ['<coverage><project/></coverage>'];

        yield 'not well-formed' => ['<coverage><project>'];

        yield 'empty' => [''];
    }

    public function testParseCloverRejectsAReportOfZeroStatementsRatherThanDividingByIt(): void
    {
        $this->expectExceptionMessage('r.xml reports zero statements');

        CoverageFloor::parseClover(self::clover(0, 0), 'r.xml');
    }

    public function testParseCloverLeavesLibxmlErrorHandlingAsItFoundIt(): void
    {
        // A parser that silences libxml globally breaks the next thing in the process to
        // read an XML file — in a PHPUnit run that is another test, and the failure lands
        // nowhere near the cause.
        $previous = libxml_use_internal_errors(false);

        try {
            CoverageFloor::parseClover(self::clover(10, 10), 'r.xml');

            $this->assertFalse(libxml_use_internal_errors(false));
        } finally {
            libxml_use_internal_errors($previous);
        }
    }

    public function testParseCloverLeavesNoErrorsInTheLibxmlBuffer(): void
    {
        // The flag is only half of it: the errors a malformed report produced stay in
        // libxml's buffer until something clears them, and the next caller of
        // `libxml_get_errors()` in this process reads them as its own. Split from the
        // test above because mutation showed the two are independent — removing the
        // `libxml_clear_errors()` call left every other assertion passing.
        //
        // Internal errors must be ON around this, and that is not incidental:
        // `libxml_get_errors()` reports nothing while they are off, so a caller in the
        // default state cannot observe the buffer at all and the assertion below would
        // hold whether or not anything cleared it. The first version of this test was
        // written that way and the mutant survived it.
        $previous = libxml_use_internal_errors(true);

        try {
            libxml_clear_errors();

            try {
                CoverageFloor::parseClover('<coverage><project>', 'r.xml');
            } catch (FloorException) {
                // The point is the buffer afterwards, not the exception.
            }

            $this->assertSame([], libxml_get_errors());
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function testEvaluatePassesInsideTheToleranceAndReportsTheRise(): void
    {
        file_put_contents($this->floorFile, "98.5\n");
        file_put_contents($this->report, self::clover(100, 99));

        $this->assertSame(
            ['actual' => 99.0, 'floor' => 98.5, 'total' => 100, 'covered' => 99, 'rose' => true],
            CoverageFloor::evaluate($this->report, $this->floorFile),
        );
    }

    public function testEvaluatePassesAtExactlyTheTolerance(): void
    {
        // The boundary the rule declares acceptable, and the reason `evaluate` rounds the
        // difference before comparing it: 99.0 - 98.0 is not 1.0 in binary floating point,
        // and a direct `> TOLERANCE` fails this case while every other test passes.
        file_put_contents($this->floorFile, "98\n");
        file_put_contents($this->report, self::clover(100, 99));

        $result = CoverageFloor::evaluate($this->report, $this->floorFile);

        $this->assertSame(99.0, $result['actual']);
        $this->assertTrue($result['rose']);
    }

    public function testEvaluateRoundsTheExcessToTwoPlacesAndNotToOne(): void
    {
        // 1.04 over the floor. Rounded to two places it is 1.04 and the gate fails;
        // rounded to one it is 1.00 and the gate passes. Without this case the precision
        // in `round($actual - $floor, 2)` is asserted by nothing, which mutation testing
        // said out loud — the 2 could become a 1 and every other test stayed green. The
        // same case pins the rounding *function*: `floor()` gives 1.0 here and passes.
        file_put_contents($this->floorFile, "97.96\n");
        file_put_contents($this->report, self::clover(100, 99));

        $this->expectException(FloorException::class);

        CoverageFloor::evaluate($this->report, $this->floorFile);
    }

    public function testEvaluateRoundsTheExcessToTwoPlacesAndNotToThree(): void
    {
        // The other side of the same assertion, and it has to be a passing case: 1.004
        // over the floor rounds to 1.00 at two places and is inside the tolerance, while
        // at three places it is 1.004 and would fail. `ceil()` gives 2.0 here and would
        // fail too, so this pins the function in the direction the case above cannot.
        file_put_contents($this->floorFile, "97.996\n");
        file_put_contents($this->report, self::clover(100, 99));

        $result = CoverageFloor::evaluate($this->report, $this->floorFile);

        $this->assertSame(99.0, $result['actual']);
        $this->assertTrue($result['rose']);
    }

    public function testEvaluateFailsMoreThanOnePointAboveTheFloorNamingAllThreeNumbers(): void
    {
        file_put_contents($this->floorFile, "95\n");
        file_put_contents($this->report, self::clover(100, 99));

        // The whole message: the two numbers alone read as a complaint about improving
        // coverage. The instruction is the half that makes it actionable, and a mutant
        // dropping it leaves a gate nobody knows how to satisfy.
        $this->expectExceptionMessage(
            '99.00% is more than 1.00 points above the floor of 95.00% — raise the floor in this pull request, so the gain is locked in by a check rather than by anyone remembering',
        );

        CoverageFloor::evaluate($this->report, $this->floorFile);
    }

    public function testEvaluatePassesExactlyAtTheFloorAndDoesNotReportARise(): void
    {
        file_put_contents($this->floorFile, "95\n");
        file_put_contents($this->report, self::clover(100, 95));

        $result = CoverageFloor::evaluate($this->report, $this->floorFile);

        $this->assertSame(95.0, $result['actual']);
        $this->assertFalse($result['rose']);
    }

    public function testEvaluateFailsBelowTheFloorNamingBothNumbers(): void
    {
        file_put_contents($this->floorFile, "99\n");
        file_put_contents($this->report, self::clover(100, 98));

        $this->expectExceptionMessage('98.00% is below the floor of 99.00%');

        CoverageFloor::evaluate($this->report, $this->floorFile);
    }

    public function testEvaluateFailsWhenTheFloorFileIsAbsentBeforeLookingAtTheReport(): void
    {
        file_put_contents($this->report, self::clover(100, 100));

        // The whole message, path included. Asserting the prose alone left every mutant
        // that dropped or reordered the concatenation alive: a message reading
        // "is missing — the floor is per-repo state…" with no path in it names nothing,
        // and CI output is the only place anyone ever reads it.
        $this->expectExceptionMessage(
            $this->floorFile . ' is missing — the floor is per-repo state and every repository owes one',
        );

        CoverageFloor::evaluate($this->report, $this->floorFile);
    }

    public function testEvaluateFailsWhenTheReportIsAbsent(): void
    {
        file_put_contents($this->floorFile, "95\n");

        // Both halves, and in order: the second occurrence of the path is the flag value
        // the reader is meant to copy, so a mutant that dropped it would leave a message
        // telling them to run the suite with `--coverage-clover=` and nothing after it.
        $this->expectExceptionMessage(
            $this->report . ' is missing — run the suite with --coverage-clover=' . $this->report,
        );

        CoverageFloor::evaluate($this->report, $this->floorFile);
    }

    /**
     * A clover report with the given statement totals, trimmed to what the parser reads.
     */
    private static function clover(int $total, int $covered): string
    {
        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<coverage generated="1"><project timestamp="1"><metrics files="1" loc="10" ncloc="10" '
            . 'classes="1" methods="1" coveredmethods="1" conditionals="0" coveredconditionals="0" '
            . 'statements="%d" coveredstatements="%d" elements="1" coveredelements="1"/>'
            . '</project></coverage>',
            $total,
            $covered,
        );
    }
}

<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\Tests;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function exec;
use function file_put_contents;
use function implode;
use function is_file;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

/**
 * The binary itself: $argv, stdio and the exit code. Everything it decides lives in src/
 * and is tested there in-process — a child process is invisible to the coverage driver,
 * so these cases prove the wiring rather than the logic.
 *
 * @internal
 *
 * @coversNothing
 */
final class CoverageFloorBinaryTest extends TestCase
{
    private const string BINARY = __DIR__ . '/../bin/coverage-floor';

    private string $directory;

    private string $report;

    private string $floorFile;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/coverage-floor-bin-' . uniqid();
        mkdir($this->directory);
        $this->report = $this->directory . '/coverage.xml';
        $this->floorFile = $this->directory . '/.coverage-floor';

        file_put_contents(
            $this->report,
            '<coverage><project><metrics statements="100" coveredstatements="99"/></project></coverage>',
        );
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

    public function testExitsZeroAndPrintsTheMeasurementWhenTheFloorIsMet(): void
    {
        file_put_contents($this->floorFile, "99\n");

        ['status' => $status, 'output' => $output] = $this->execute();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('coverage 99.00% (99/100 statements), floor 99.00%', $output);
    }

    public function testEmitsANoticeWhenCoverageHasRisenInsideTheTolerance(): void
    {
        file_put_contents($this->floorFile, "98.5\n");

        ['status' => $status, 'output' => $output] = $this->execute();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('::notice::coverage rose to 99.00%', $output);
    }

    public function testExitsOneWhenCoverageIsMoreThanOnePointAboveTheFloor(): void
    {
        file_put_contents($this->floorFile, "95\n");

        ['status' => $status, 'output' => $output] = $this->execute();

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            '::error::coverage floor: 99.00% is more than 1.00 points above the floor of 95.00%',
            $output,
        );
    }

    public function testExitsOneWithAGithubErrorAnnotationWhenTheFloorIsMissed(): void
    {
        file_put_contents($this->floorFile, "99.5\n");

        ['status' => $status, 'output' => $output] = $this->execute();

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            '::error::coverage floor: 99.00% is below the floor of 99.50%',
            $output,
        );
    }

    public function testExitsOneWhenTheFloorFileIsAbsent(): void
    {
        ['status' => $status, 'output' => $output] = $this->execute();

        $this->assertSame(1, $status);
        $this->assertStringContainsString('::error::coverage floor:', $output);
    }

    /**
     * Runs the binary under the current PHP, merging stderr so the annotations it writes
     * there are visible to the assertions.
     *
     * @return array{status: int, output: string}
     */
    private function execute(): array
    {
        $command = implode(' ', [
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::BINARY),
            escapeshellarg($this->report),
            escapeshellarg($this->floorFile),
            '2>&1',
        ]);

        $lines = [];
        $status = 0;
        exec($command, $lines, $status);

        return ['status' => $status, 'output' => implode("\n", $lines)];
    }
}

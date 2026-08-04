<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp;

use SimpleXMLElement;

use function file_get_contents;
use function is_file;
use function is_numeric;
use function libxml_clear_errors;
use function libxml_use_internal_errors;
use function round;
use function simplexml_load_string;
use function sprintf;

/**
 * Layer 2 (PHP) — the coverage floor, bound to the `coverage` verb.
 *
 * The logic lives here rather than in `bin/`, and the split is not cosmetic: the binary
 * reads `$argv`, writes to stdio and calls `exit`, so testing it meant spawning a child
 * process — and a child process is invisible to the coverage driver, which would have
 * left this file measured at zero while claiming to enforce a floor on everyone else.
 * The estate's own executable was the one piece of code it never measured.
 *
 * Everything here throws {@see FloorException} instead of exiting.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class CoverageFloor
{
    /**
     * The floor below which no repository may set its own floor. A per-repo
     * `.coverage-floor` ratchets **up** from here; it is never lowered to accommodate a
     * failing suite.
     */
    public const float HARD_FLOOR = 95.0;

    /**
     * Reads a floor from the text of a `.coverage-floor` file.
     *
     * @param string $text  raw file contents
     * @param string $label the file's path, for the message
     *
     * @throws FloorException when it is not a number, or is below self::HARD_FLOOR
     */
    public static function parseFloor(string $text, string $label): float
    {
        // `is_numeric`, not a bare `(float)` cast. The cast this replaced turned every
        // typo into 0.0, which then failed as "below the hard floor" — a true statement
        // about a number the file does not contain, sending the reader to look for a
        // lowered floor instead of a malformed one.
        //
        // No `trim()`: since PHP 8.0 `is_numeric` accepts surrounding whitespace and the
        // `(float)` cast has always ignored it, so trimming changed no input this method
        // can receive. The mutation run proved it — removing the call killed nothing.
        if (!is_numeric($text)) {
            throw new FloorException($label . ' does not contain a number');
        }

        $floor = (float) $text;

        if ($floor < self::HARD_FLOOR) {
            throw new FloorException(sprintf(
                '%s says %s, below the hard floor of %s',
                $label,
                $floor,
                self::HARD_FLOOR,
            ));
        }

        return $floor;
    }

    /**
     * Reads statement totals out of a clover report.
     *
     * Clover is the format because the TypeScript side reads it too — one definition of
     * "covered" across both languages, rather than two that drift.
     *
     * @param string $xml   raw report contents
     * @param string $label the file's path, for the message
     *
     * @return array{total: int, covered: int, percent: float}
     *
     * @throws FloorException when the report has no project metrics, or reports none
     */
    public static function parseClover(string $xml, string $label): array
    {
        // Internal error handling rather than `@`: the silence operator would also
        // swallow an error raised by anything this call reaches, and libxml has its own
        // buffer for exactly this. Both the buffer and the flag are restored, because a
        // test runner and a CI job both care about libxml state they did not set.
        //
        // Straight-line, not try/finally. The finally was written for an exception
        // `simplexml_load_string` cannot raise — it reports a malformed document by
        // returning false, and the throw below happens after the restore either way — so
        // it protected nothing and could not be tested. Mutation found it: unwrapping the
        // finally changed no observable behaviour on any input.
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$document instanceof SimpleXMLElement || !isset($document->project->metrics)) {
            throw new FloorException($label . ' is not a clover report with project metrics');
        }

        $metrics = $document->project->metrics;
        $total = (int) $metrics['statements'];
        $covered = (int) $metrics['coveredstatements'];

        if ($total === 0) {
            throw new FloorException($label . ' reports zero statements — the suite covered nothing');
        }

        return [
            'total' => $total,
            'covered' => $covered,
            'percent' => round($covered / $total * 100, 2),
        ];
    }

    /**
     * Compares a clover report against a repository's floor.
     *
     * @param string $report    path to the clover report
     * @param string $floorFile path to the `.coverage-floor` file
     *
     * @return array{actual: float, floor: float, total: int, covered: int, rose: bool}
     *
     * @throws FloorException when either file is missing, unreadable as expected, or the
     *                        measured coverage is below the floor
     */
    public static function evaluate(string $report, string $floorFile): array
    {
        if (!is_file($floorFile)) {
            throw new FloorException(
                $floorFile . ' is missing — the floor is per-repo state and every repository owes one',
            );
        }

        if (!is_file($report)) {
            throw new FloorException($report . ' is missing — run the suite with --coverage-clover=' . $report);
        }

        $floor = self::parseFloor((string) file_get_contents($floorFile), $floorFile);
        ['total' => $total, 'covered' => $covered, 'percent' => $actual] = self::parseClover(
            (string) file_get_contents($report),
            $report,
        );

        if ($actual < $floor) {
            throw new FloorException(sprintf('%.2f%% is below the floor of %.2f%%', $actual, $floor));
        }

        // The ratchet is reported, not enforced: failing a pull request for *improving*
        // coverage is a different policy, and it would have to be decided rather than
        // inherited from the word "monotonic".
        return [
            'actual' => $actual,
            'floor' => $floor,
            'total' => $total,
            'covered' => $covered,
            'rose' => $actual > $floor,
        ];
    }
}

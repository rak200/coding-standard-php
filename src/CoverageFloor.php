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
     * How far above its declared floor a repository may sit without re-declaring it.
     * Beyond this the gate fails, forcing the pull request that won the coverage to
     * record it — the ratchet's second mode.
     */
    public const float TOLERANCE = 1.0;

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
        $document = self::load($xml);

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
     * The files a clover report describes, as the report spells them.
     *
     * A report names every file it measured, which is what makes staleness detectable with
     * no clock involved: a source file on disk that the report never heard of proves the
     * report describes a different tree. Paths come back verbatim — they are absolute and
     * rooted whereever the suite ran, so the caller compares basenames rather than paths.
     *
     * @param string $xml raw report contents
     *
     * @return list<string>
     */
    public static function cloverFiles(string $xml): array
    {
        $document = self::load($xml);

        if (!$document instanceof SimpleXMLElement) {
            return [];
        }

        $files = [];
        foreach ($document->xpath('//file[@name]') ?: [] as $file) {
            $files[] = (string) $file['name'];
        }

        return $files;
    }

    /**
     * The source files a report is missing, by basename.
     *
     * Basenames rather than paths: the report records where the suite ran, which inside a
     * container is not where the caller is looking. Two source files with the same basename
     * in different directories would collapse into one — accepted, because the alternative
     * is path arithmetic between two roots that need not share a prefix.
     *
     * @param list<string> $sources source files on disk
     * @param list<string> $covered files the report describes
     *
     * @return list<string>
     */
    public static function absentFrom(array $sources, array $covered): array
    {
        $known = array_map(static fn (string $path): string => basename($path), $covered);

        return array_values(array_filter(
            $sources,
            static fn (string $path): bool => !in_array(basename($path), $known, true),
        ));
    }

    /**
     * Compares a clover report against a repository's floor.
     *
     * @param string       $report    path to the clover report
     * @param string       $floorFile path to the `.coverage-floor` file
     * @param list<string> $sources   source files the report must describe; empty skips the
     *                                staleness checks, which is what a caller with nothing to
     *                                compare against should get rather than a guess
     *
     * @return array{actual: float, floor: float, total: int, covered: int, rose: bool}
     *
     * @throws FloorException when either file is missing, unreadable as expected, or the
     *                        measured coverage is below the floor or more than
     *                        {@see self::TOLERANCE} points above it
     */
    public static function evaluate(string $report, string $floorFile, array $sources = []): array
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
        // @infection-ignore-all CastString: `is_file` above guarantees a readable path, so
        // file_get_contents cannot return false here and dropping the cast changes nothing.
        $xml = (string) file_get_contents($report);
        ['total' => $total, 'covered' => $covered, 'percent' => $actual] = self::parseClover($xml, $report);

        // A report is an artefact of the run that produced it, and nothing regenerates it:
        // `coverage.xml` is in the seeded .gitignore, so `coverage` on its own grades
        // whatever is on disk. Measured on rak200/utils: a class added to src/ with no test
        // at all, and the verb printed `coverage rose to 97.95% — raise .coverage-floor to
        // match` and exited 0. Not merely a stale number — the advice was the opposite of
        // correct. CI never sees this, because the step before it writes the report, which
        // is the bad half: the verb means one thing in the pipeline and a weaker thing on
        // the machine where someone would use it as a pre-push check.
        // rak200/coding-standard-php#52
        if ($sources !== []) {
            // FIRST the file set, because it accuses precisely and without a clock: a source
            // file the report never mentions cannot be explained by a rebase or a touch.
            $absent = self::absentFrom($sources, self::cloverFiles($xml));
            if ($absent !== []) {
                throw new FloorException(sprintf(
                    '%s describes a different tree — it never measured %s. Re-run the suite with --coverage-clover=%s',
                    $report,
                    implode(', ', array_slice($absent, 0, 3)) . (count($absent) > 3 ? sprintf(' and %d more', count($absent) - 3) : ''),
                    $report,
                ));
            }

            // THEN mtime, for what the file set cannot see: lines added to a file the report
            // already lists. This half is a heuristic and says so — a rebase or a checkout
            // moves mtime without moving content — so it names the file and the fix rather
            // than asserting the report is wrong.
            // @infection-ignore-all DecrementInteger: `$sources` is non-empty inside this
            // branch, so the loop always assigns and the seed is never compared.
            $newest = 0;
            $newestFile = '';
            foreach ($sources as $source) {
                // @infection-ignore-all CastInt: the path comes from a directory scan a
                // moment earlier, so filemtime cannot return false without a race no test
                // can stage.
                $at = (int) filemtime($source);
                if ($at > $newest) {
                    $newest = $at;
                    $newestFile = $source;
                }
            }

            // @infection-ignore-all CastInt: `is_file($report)` was checked above.
            if ($newest > (int) filemtime($report)) {
                throw new FloorException(sprintf(
                    '%s is older than %s, so it may describe a tree that has since changed. Re-run the suite with --coverage-clover=%s',
                    $report,
                    $newestFile,
                    $report,
                ));
            }
        }

        if ($actual < $floor) {
            throw new FloorException(sprintf('%.2f%% is below the floor of %.2f%%', $actual, $floor));
        }

        // The ratchet is enforced above the tolerance, and this comment used to say the
        // opposite: "reported, not enforced … it would have to be decided rather than
        // inherited from the word monotonic". It had been decided — RFC 0017, *Testing
        // policy and the coverage floor*, states the one-point band and the reason for it
        // — and neither side read the other, so the estate carried a rule in prose and a
        // refusal to implement it in code, each with its own argument. That is worse than
        // an oversight, because both look deliberate.
        //
        // Rounded before comparing, not compared directly. `$actual` is already rounded to
        // two places while `$floor` is whatever the file says, so `$actual - $floor` lands
        // a few ulps above 1.0 at exactly the boundary and would fail a repository sitting
        // precisely one point over — the one value the rule declares acceptable.
        if (round($actual - $floor, 2) > self::TOLERANCE) {
            throw new FloorException(sprintf(
                '%.2f%% is more than %.2f points above the floor of %.2f%% — raise the floor in this pull request, so the gain is locked in by a check rather than by anyone remembering',
                $actual,
                self::TOLERANCE,
                $floor,
            ));
        }

        return [
            'actual' => $actual,
            'floor' => $floor,
            'total' => $total,
            'covered' => $covered,
            'rose' => $actual > $floor,
        ];
    }

    /**
     * Parses a document without leaking libxml state, or false when it is malformed.
     *
     * Internal error handling rather than `@`: the silence operator would also swallow an
     * error raised by anything this call reaches, and libxml has its own buffer for exactly
     * this. Both the buffer and the flag are restored, because a test runner and a CI job
     * both care about libxml state they did not set.
     *
     * Straight-line, not try/finally. The finally was written for an exception
     * `simplexml_load_string` cannot raise — it reports a malformed document by returning
     * false — so it protected nothing and could not be tested. Mutation found it: unwrapping
     * the finally changed no observable behaviour on any input.
     *
     * One loader for both readers. It was duplicated when `cloverFiles` arrived, and mutation
     * found that too — the copy's restore calls had nothing asserting them, because the
     * reasoning above lived at the other call site.
     *
     * @param string $xml raw document
     */
    private static function load(string $xml): false|SimpleXMLElement
    {
        $previous = libxml_use_internal_errors(true);
        $document = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $document;
    }
}

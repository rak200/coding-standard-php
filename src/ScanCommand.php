<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp;

/**
 * Layer 2 (PHP) — the security scanner's command line.
 *
 * RFC 0017's *Code scanning* decides this command and records it verified against a
 * planted fixture: `4 findings (4 blocking)`, exit 1. It then lived in each repository's
 * own `composer.json`, which is per-repo by construction — Composer never inherits a
 * dependency's scripts — and nothing anywhere compared the four copies to the decision.
 * All four drifted to the same wrong shape, and the canary that would have caught it was
 * never fired. Both packs collapsed to one, and `--severity=ERROR` took the place of
 * `--error`, which are unrelated flags with confusable names:
 *
 *   --error            exit non-zero when there are findings. Without it semgrep reports
 *                      and exits 0, so the enforcing step compares a code that cannot
 *                      differ from zero.
 *   --severity=ERROR   filter which rules RUN. It narrowed the set from 23 to 10 and
 *                      dropped nothing that decides the exit code, because it never
 *                      touched the exit code at all.
 *
 * The verb now binds here instead, which is the split the RFC already states — Layer 1
 * owns the vocabulary, Layer 2 owns what each word does — and the same shape the
 * `coverage` verb has used since it started calling {@see CoverageFloor}. What changes is
 * that the decision is now asserted by a test suite at a 100% mutation floor: a mutant
 * that drops a pack or a flag has a test to answer to, which four copies of a string in
 * four manifests never did.
 *
 * This file holds the arguments and nothing else. `bin/rak200-scan` runs them and owns the
 * exit code, for the reason recorded on {@see CoverageFloor}: a binary that is only
 * reachable through a child process is invisible to coverage, and the estate's own
 * executables were the code it never measured.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class ScanCommand
{
    /**
     * The registry rule packs, in the order the RFC names them.
     *
     * `p/security-audit` is not decoration. It carries the `audit` subcategory, where
     * `eval-use` lives — the rule that matches the negative canary this estate has planted
     * twice. `p/php` alone is tuned for precision and answers a planted `eval($_POST[…])`
     * with `0 findings`.
     */
    public const array PACKS = ['p/php', 'p/security-audit'];

    /** Where the SARIF report is written, for the publishing step to upload. */
    public const string REPORT = 'semgrep.sarif';

    /**
     * The full argument list, executable as-is.
     *
     * @return list<string>
     */
    public static function arguments(): array
    {
        $arguments = ['semgrep', 'scan'];

        foreach (self::PACKS as $pack) {
            $arguments[] = '--config=' . $pack;
        }

        // `--error` is the whole gate. Everything else here decides what is looked at and
        // where the report goes; this is the only flag that turns a finding into a
        // non-zero exit, and therefore the only one the enforcing step can observe.
        $arguments[] = '--error';
        $arguments[] = '--sarif';
        $arguments[] = '--output=' . self::REPORT;

        // Telemetry off: the scanner runs on every pull request in the estate, and a gate
        // that phones home is a gate with a dependency nobody chose.
        $arguments[] = '--metrics=off';

        $arguments[] = '.';

        return $arguments;
    }
}

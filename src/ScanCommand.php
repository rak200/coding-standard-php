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
     * The registry configs, in the order the RFC names them, plus the rule it turned out
     * neither of them carries.
     *
     * The line above used to say `p/security-audit` carried `eval-use` and was therefore
     * what answered the planted `eval($_POST[…])`. It does not. Measured against the
     * registry on 2026-09-23: `p/security-audit` serves 225 rules and **not one of them is
     * PHP** — python 70, java 40, go 24, javascript 20, typescript 20, generic 14, ruby 13,
     * c 9, regex 4, hcl 3, dockerfile 1, kotlin 1 — so it cannot change what a PHP scan
     * finds, and never could. Nothing regressed: the pull request that restored the pack
     * recorded the command running **23** PHP rules, which is exactly what `p/php` alone
     * contributes, and it still runs 23 today.
     *
     * `eval-use` is excluded from `p/php` by the very tuning that makes that pack precise:
     * it is `confidence: LOW`, and every rule in `p/php` is MEDIUM or HIGH. So the rule is
     * named here directly. Measured with a planted canary, same tree, one config apart:
     * without it, 250 rules would have been 249, 24 PHP rules 23, and `1 finding
     * (1 blocking)`, exit 1, would have been `0 findings`, exit 0.
     *
     * The directory — `r/php.lang.security`, 36 rules — was measured and rejected. It
     * carries `weak-crypto`, which matches **any** `md5()` or `sha1()`, and the estate has
     * five such calls that are all correct: four in `rak200/utils`'s `Hash` and one in
     * `rak200/collections`. A gate that reds correct code is a gate people learn to route
     * around.
     *
     * `p/security-audit` stays because it costs nothing and covers the day a repository
     * here holds something other than PHP; it is no longer claimed to do more than that.
     */
    public const array PACKS = ['p/php', 'p/security-audit', 'r/php.lang.security.eval-use'];

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

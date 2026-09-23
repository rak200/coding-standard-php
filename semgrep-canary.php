<?php

declare(strict_types=1);

/*
 * TEMPORARY canary. Deleted with the branch; never merged.
 *
 * It asks one question — does adding `r/php.lang.security.eval-use` to PACKS make the
 * scanner catch what it demonstrably does not catch today? Three probes, three expected
 * answers, so the run is readable whichever way it goes.
 *
 * At the repository root on purpose: every other verb reads `src`, `tests` and `bin/`,
 * while `rak200-scan` passes `.`, so this file reaches the scanner and nothing else.
 */

/** PROBE 1 — the target. MUST fire, or the proposed fix is not a fix. */
function probeTarget(): void
{
    eval($_POST['payload']);
}

/**
 * PROBE 2 — MUST NOT fire, and not because the rule is narrow: semgrep-rules carries no
 * insecure-randomness rule for PHP at all. `weak-crypto` is hashing, not RNG. This is the
 * measured answer to the open question on #10.
 */
function probeRandomness(): int
{
    srand(1234);

    return rand() + mt_rand();
}

/**
 * PROBE 3 — the cost marker. MUST NOT fire under the narrow target. `r/php.lang.security`
 * wholesale would bring `weak-crypto`, which matches any `md5()`/`sha1()` — four such
 * calls in `utils/src/Hash.php` and one in `collections/src/Internal/HashesValues.php`,
 * every one of them legitimate. If this fires, the target is wider than intended.
 */
function probeCost(string $data): string
{
    return md5($data);
}

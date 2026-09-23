<?php

declare(strict_types=1);

/*
 * TEMPORARY scanner canary. Not part of this package, never autoloaded, and deleted with
 * the branch it arrived on. It answers one open question on issue #10: does the `scan`
 * verb's ruleset reach insecure randomness?
 *
 * Two shapes in one file, so the run can be read:
 *
 *   CONTROL    eval($_POST[...])  — `p/security-audit` carries `eval-use`, and RFC 0017
 *              recorded it matching a planted fixture. If this does NOT fire, the file
 *              was not scanned and the run says nothing about the treatment either.
 *
 *   TREATMENT  rand(), mt_rand(), srand() — the question. CONVENTIONS.md §Safe defaults
 *              forbids all three; nothing in this estate enforces that today.
 *
 * It sits at the repository root on purpose: `composer lint`, `analyse`, `test` and
 * `mutation` all read `src`, `tests` and `bin/` only, while `rak200-scan` passes `.` —
 * so this file reaches the scanner and nothing else.
 */

function canaryControl(): void
{
    // CONTROL — expected to fire.
    eval($_POST['payload']);
}

function canaryTreatment(): int
{
    // TREATMENT — the open question.
    srand(1234);

    return rand() + mt_rand() + mt_rand(1, 6);
}

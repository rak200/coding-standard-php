# `rak200-scan`

[← Reference](README.md)

Runs semgrep with the command the estate decides, and exits with semgrep's own code. Installed on
`vendor/bin` and bound to the `scan` verb.

```bash
vendor/bin/rak200-scan
```

## Contents

- [The command it runs](#the-command-it-runs)
- [Why it is a binary and not a composer script](#why-it-is-a-binary-and-not-a-composer-script)

---

## The command it runs

| part | value | why |
| --- | --- | --- |
| packs | `p/php`, `p/security-audit` | both, not one |
| report | `semgrep.sarif` | uploaded by the pipeline as SARIF |
| exit behaviour | `--error` | exit non-zero **when there are findings** |

`--error` and `--severity=ERROR` are unrelated flags with confusable names: the first decides the
exit code, the second filters what is reported. Substituting one for the other leaves a scanner
that finds and reports and never fails.

[↑ Back to top](#rak200-scan)

---

## Why it is a binary and not a composer script

It lived in each repository's own `composer.json`, which is per-repo by construction — Composer
never inherits a dependency's scripts — and nothing compared the copies to the decision. All four
drifted to the same wrong shape. A binary is installed, not copied.

The arguments live in `src/`, apart from the binary, so they can be asserted by a test.

[↑ Back to top](#rak200-scan)

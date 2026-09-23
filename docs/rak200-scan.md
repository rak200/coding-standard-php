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
| packs | `p/php`, `p/security-audit` | the second serves no PHP rule; it is kept for the day a repository here holds something else |
| rule | `r/php.lang.security.eval-use` | named on its own, because neither pack carries it |
| report | `semgrep.sarif` | uploaded by the pipeline as SARIF |
| exit behaviour | `--error` | exit non-zero **when there are findings** |

`--error` and `--severity=ERROR` are unrelated flags with confusable names: the first decides the
exit code, the second filters what is reported. Substituting one for the other leaves a scanner
that finds and reports and never fails.

### Why a rule is named beside the packs

`p/php` is tuned for precision — every rule in it is `confidence: MEDIUM` or `HIGH`. `eval-use` is
`LOW`, so the pack that covers PHP excludes it, and the pack that carries the `audit` subcategory
serves **no PHP rule at all**: 225 rules, none of them this language. A planted
`eval($_POST[…])` therefore passed the gate at `0 findings`, exit 0.

Measured on the same tree, one config apart:

| | packs only | packs + the rule |
| --- | --- | --- |
| rules loaded | 249 | 250 |
| PHP rules run | 23 | 24 |
| findings | 0 | **1 (1 blocking)** |
| exit | 0 | **1** |

The whole `r/php.lang.security` directory, 36 rules, was measured and rejected: it carries
`weak-crypto`, which matches any `md5()` or `sha1()`, and this estate has five such calls that are
all correct.

**What none of it reaches is insecure randomness.** `rand()`, `mt_rand()` and `srand()` produce no
finding under any of these configs, because semgrep-rules carries no PHP rule for them —
`weak-crypto` is hashing, not RNG. §*Safe defaults* states that requirement and nothing enforces
it.

[↑ Back to top](#rak200-scan)

---

## Why it is a binary and not a composer script

It lived in each repository's own `composer.json`, which is per-repo by construction — Composer
never inherits a dependency's scripts — and nothing compared the copies to the decision. All four
drifted to the same wrong shape. A binary is installed, not copied.

The arguments live in `src/`, apart from the binary, so they can be asserted by a test.

[↑ Back to top](#rak200-scan)

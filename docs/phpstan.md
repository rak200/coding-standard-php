# `phpstan.neon.dist`

[← Reference](README.md)

The static-analysis standard: the level, and the three settings beside it. Extended by a
consumer, never copied.

```neon
# phpstan.neon.dist
includes:
    - vendor/rak200/coding-standard-php/phpstan.neon.dist

parameters:
    paths:
        - src
        - tests
```

## Contents

- [What it sets](#what-it-sets)
- [Why `paths` is yours](#why-paths-is-yours)

---

## What it sets

| parameter | value | default? |
| --- | --- | --- |
| `level` | `max` | — |
| `treatPhpDocTypesAsCertain` | `false` | no |
| `reportUnmatchedIgnoredErrors` | `true` | yes, declared as a lock |
| `reportIgnoresWithoutComments` | `true` | no |

**`treatPhpDocTypesAsCertain: false`** — PHP erases generics, so a guard over a `class-string<T>`
or an `iterable<K,V>` is the only check there is, not a redundant one. PHPStan's default reports it
as redundant: four such errors across the estate, all the same identifier, none a bug.

**`reportUnmatchedIgnoredErrors: true`** is already PHPStan's default and is declared here as a
lock. A suppression that has stopped applying is this estate's own *looks green, enforces nothing*
shape.

**`reportIgnoresWithoutComments: true`** is not the default. A suppression must say why, and
PHPStan reads the reason in exactly one form: `@phpstan-ignore <id> (reason)`, one line, once per
identifier.

A local `phpstan.neon` may override any of it and stays untracked.

[↑ Back to top](#phpstanneondist)

---

## Why `paths` is yours

PHPStan resolves a relative `paths` against the file that declares it. A `paths: [src, tests]` set
here would name directories **inside the installed package**, which do not exist, and the analyser
refuses to start. It was shipped that way once and found the first time a repository tried it.

The level and the analyser's settings are the standard; what to look at is the consumer's business.

[↑ Back to top](#phpstanneondist)

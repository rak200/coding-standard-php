# rak200/coding-standard-php

[![PHP](https://img.shields.io/badge/php-8.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen?logo=php&logoColor=white)](phpstan.neon.dist)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

**Layer 2 of the rak200 baseline, for PHP**: the enforcing configuration and the prose that
documents it, versioned together so a repository cannot have one without the other.

Layer 1 — versioning, commits, the pipeline shape, testing and documentation *policy*,
repository hygiene — is language-agnostic and lives in
[rak200/workflow](https://github.com/rak200/workflow), imported alongside this package.

## Install

```bash
composer require --dev rak200/coding-standard-php
```

It brings PHPStan, php-cs-fixer, PHPUnit and Infection with it, so a repository's `require-dev`
cannot drift from its siblings'.

## Use

```neon
# phpstan.neon.dist
includes:
    - vendor/rak200/coding-standard-php/phpstan.neon.dist

parameters:
    paths:
        - src
        - tests
```

```php
// .php-cs-fixer.dist.php
return (require __DIR__ . '/vendor/rak200/coding-standard-php/.php-cs-fixer.dist.php')
    ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests']));
```

**The consumer owns *what to look at*, both times, and not by preference.** PHPStan resolves a
relative `paths` against the file that declares it, and Symfony's Finder validates a directory the
moment it is added — so a `paths` or a finder baked into this package names directories inside
the installed package, which do not exist. The first makes the analyser refuse to start; the
second throws on `require`. Both were shipped that way, and both were found the first time a
repository tried the snippets above.

```json5
// infection.json5.dist — copy and adjust `source`, keep the floor
```

```markdown
<!-- CLAUDE.md -->
@.rak200/CONVENTIONS.md
@vendor/rak200/coding-standard-php/CONVENTIONS.md
```

## What it fixes in place

| Config | The decision it carries |
| --- | --- |
| `phpstan.neon.dist` | `level: max`, over `src/` **and** `tests/` |
| `.php-cs-fixer.dist.php` | `@PhpCsFixer` — the strictest consolidated preset — with five stated overrides |
| `infection.json5.dist` | `minCoveredMsi: 100`; a survivor is killed, never accommodated |
| `bin/coverage-floor` | the `coverage` verb: a clover report against the repo's `.coverage-floor` |

**The overrides are the interesting part**, and each states its reason inline rather than
existing by habit: the `use function` inventory, member order with magic last, natural
(non-Yoda) comparisons, one space around concatenation — and two rules turned *off*
(`phpdoc_to_comment` for `@var`, `return_assignment`) because they would destroy the inline
`/** @var */` idiom that keeps a deficient native stub from distorting real code.

`minMsi` is deliberately absent. Mandating it would silently mandate literal-100% line coverage
as well, which is a different decision and belongs to each repository's `.coverage-floor`.

## Versioning

Bare SemVer tags. Raising the PHP floor, tightening the analyser, or adding a fixer rule that
reformats existing code is a **major**: it can turn a green repository red without a line of its
own changing.

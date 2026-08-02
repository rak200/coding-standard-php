# rak200/coding-standard-php

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
```

```php
// .php-cs-fixer.dist.php
return (require 'vendor/rak200/coding-standard-php/.php-cs-fixer.dist.php')
    ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests']));
```

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

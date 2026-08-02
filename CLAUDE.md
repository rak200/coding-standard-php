# CLAUDE.md

Guidance for Claude Code when working in this repository.

@CONVENTIONS.md

> This repository *is* Layer 2, so the import above is local. Everywhere else it reads
> `@vendor/rak200/coding-standard-php/CONVENTIONS.md`, beside `@.rak200/CONVENTIONS.md`.

## What this repository is

The PHP half of the ecosystem baseline: three config files and the prose that explains them. It
ships **no source and no test suite** — its product *is* the configuration — which is why its CI
calls the language-agnostic pipeline rather than the PHP one.

## What that implies for editing

1. **A config change here reformats or reds other people's repositories.** Tightening the
   analyser, adding a fixer rule, or raising the PHP floor is a **major** bump: a consumer can
   go red without changing a line of its own.
2. **Every override carries its reason inline.** The preset is the strictest consolidated one and
   the ecosystem's rule is that narrowing it is an exception that must justify itself. If you add
   an override, write why in the file, not in a commit message that nobody reads at the point of
   confusion.
3. **Two rules are off for a documented idiom, not for taste** — `phpdoc_to_comment` for `@var`
   and `return_assignment`. They protect the localized `/** @var */` that keeps a deficient
   native stub from distorting real code. Re-enabling either breaks that idiom everywhere.
4. **The prose and the config ship together.** A rule enforced by a config with no prose is a
   surprise; prose describing a rule no config enforces is a lie. Change both in the same PR.
5. **`minCoveredMsi` is the floor and `minMsi` stays absent.** That asymmetry is deliberate —
   see the comment in `infection.json5.dist` before changing it.

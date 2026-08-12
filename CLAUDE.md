# CLAUDE.md

Guidance for Claude Code when working in this repository.

@.rak200/CONVENTIONS.md
@CONVENTIONS.md

> The second import is **local**: this repository *is* Layer 2. Everywhere else it reads
> `@vendor/rak200/coding-standard-php/CONVENTIONS.md`, because Composer does not install a
> package into its own tree. If `.rak200/` is empty, the clone skipped its submodule:
> `git submodule update --init --recursive`.

## What this repository is

The PHP half of the ecosystem baseline: three config files and the prose that explains them. It
ships **no source and no test suite** — its product *is* the configuration — which is why its CI
calls the language-agnostic pipeline rather than the PHP one.

## Where the rules are

In the two imports above, in [README.md](README.md), and in each config file beside the line it
explains. This file restates none of them.

- **What a change here costs a consumer** — [README.md](README.md) §*Versioning*: raising the PHP
  floor, tightening the analyser or adding a reformatting fixer rule is a **major**, because a
  consumer can go red without changing a line of its own.
- **Why each override exists**, and why two rules are turned *off* — [README.md](README.md)
  §*What it fixes in place*, and inline in `.php-cs-fixer.dist.php` beside each one.
- **The prose and the config ship together** — [README.md](README.md), end of §*What it fixes in
  place*. Changing one without the other is the failure this package exists to remove.
- **Why `minCoveredMsi` is the floor and `minMsi` stays absent** — the comment at the top of
  `infection.json5.dist`, and [README.md](README.md).

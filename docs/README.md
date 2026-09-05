# Reference

What this package exposes to a repository that installs it. For installation and an overview, see
the [top-level README](../README.md); for the rules themselves, [CONVENTIONS.md](../CONVENTIONS.md).

| Unit | Doc | What it covers |
| --- | --- | --- |
| `coverage-floor` | [coverage-floor.md](coverage-floor.md) | the `coverage` verb — enforces `.coverage-floor` against a clover report |
| `rak200-scan` | [rak200-scan.md](rak200-scan.md) | the `scan` verb — runs semgrep with the command RFC 0017 decides |
| `phpstan.neon.dist` | [phpstan.md](phpstan.md) | static analysis: level and the three settings beside it |
| `.php-cs-fixer.dist.php` | [php-cs-fixer.md](php-cs-fixer.md) | the formatter preset and how a consumer supplies its finder |
| `infection.json5.dist` | [infection.md](infection.md) | the mutation floor, copied rather than extended |

## What is not here, and why

**`src/` is implementation, not API.** The two binaries hold `argv`, stdio and the exit code; the
logic lives in `src/` so that it can be tested and measured — a child process is invisible to
coverage instrumentation, and the estate's own executable would otherwise be the one piece of code
it never measured. `CoverageFloor`, `FloorException` and `ScanCommand` are `public` because the
binaries and the tests reach them, not because a consumer should.

What a consumer reaches is the five units above: two commands on `vendor/bin`, and three
configuration files it extends or copies.

# rak200 — PHP conventions (Layer 2)

How we write PHP, for every rak200 PHP project. The language-agnostic half — versioning,
commits, the pipeline shape, testing policy, documentation policy, repository hygiene — is
**Layer 1**, in [`rak200/workflow`](https://github.com/rak200/workflow), imported alongside this
file. Nothing here repeats it.

Import both from a project's `CLAUDE.md`:

```markdown
@.rak200/CONVENTIONS.md
@vendor/rak200/coding-standard-php/CONVENTIONS.md
```

## Baseline

- **PHP 8.4+**, with `declare(strict_types=1)` at the top of every file. CI also runs the suite
  on the next PHP minor.
- **No runtime Composer dependencies** — only the extensions a project genuinely needs, declared
  under `require` (`ext-mbstring` wherever `mb_*` is used, `ext-bcmath` for big-number work).
- **One dev dependency**: this package. It brings the analyser, the formatter, the test runner,
  the mutation engine and the coverage-floor binary with it, so a repository's `require-dev` does
  not drift from its siblings'. The one tool it cannot bring is the security scanner — see
  `scan` below.

## The verbs, bound

Layer 1 fixes the vocabulary; here is what each word does in PHP. A repository declares all
eight in `composer.json`; CI asserts their presence.

| Verb | Binding |
| --- | --- |
| `validate` | `composer validate --no-check-publish --strict` — **never declared as a script** |
| `lint` | `php-cs-fixer fix --dry-run --diff` |
| `fix` | `php-cs-fixer fix` |
| `analyse` | `phpstan analyse --memory-limit=512M` |
| `test` | `phpunit` |
| `coverage` | `coverage-floor` — this package's binary, clover report against `.coverage-floor` |
| `scan` | `semgrep scan --config=p/php --severity=ERROR --sarif -o semgrep.sarif` |
| `mutation` | `infection --threads=max` |

Three of them need a word beyond the binding.

**`validate` is the native Composer command, and the manifest must not declare it.** Composer
skips any script shadowing a native command — under `composer validate` *and*
`composer run-script validate` — printing *"A script named validate would override a Composer
command and has been skipped"* before falling through. A declared `validate` would be a script
that can never run, which reads as covered; CI asserts its absence.

**`scan` is the one verb no Composer dependency satisfies.** semgrep is a Python tool, and the
ecosystem standardises on it across languages rather than hunting a native equivalent per
language — so a development environment installs it outside Composer (`pipx install semgrep`)
and CI installs it explicitly. Discovering this cost nothing except a step that would have
exited 127 while looking careful.

**`mutation` takes its scope from the caller.** The verb is the full run; CI narrows it to the
changed lines on a pull request (`--git-diff-lines --git-diff-base=origin/<base>`) because a full
run is tens of minutes on a real library. The floor is identical either way — Layer 1 owns the
word, the pipeline owns when and over what it runs.

## Static analysis

**PHPStan at `level: max`**, over `src/` *and* `tests/`. The committed config is
`phpstan.neon.dist`; a local `phpstan.neon` may override it and stays untracked.

When an error is caused by a **deficient native stub** — a functionMap entry that erases the
value type (`preg_grep`, `sscanf`, …; confirmable inside the phpstan phar) — a localized inline
`/** @var */` documenting the genuinely-known-true type is the **preferred** fix. Never
restructure code or add runtime work to satisfy the analyser. Reserve "fix the underlying cause"
for errors that signal a real type problem.

The formatter config protects that idiom deliberately: `phpdoc_to_comment` ignores `@var`, and
`return_assignment` is off. Neither is style preference; both exist to keep the annotation where
it belongs.

## Code style

**`@PhpCsFixer`, the strictest consolidated preset**, over `src/` and `tests/`. The overrides are
few and each carries its rationale inline in `.php-cs-fixer.dist.php`: the `use function`
inventory, the member order, natural (non-Yoda) comparisons, one space around concatenation, and
the two rules above.

Run the fixer on the language floor (8.4) to match it. A newer runtime needs
`PHP_CS_FIXER_IGNORE_ENV=1` and prints a harmless version warning.

## Naming — the shortest name that stays unambiguous and discoverable

Brevity is the tie-breaker, not the goal. In precedence order:

1. **Invariant families outrank brevity** — the `*OrNull` suffix, the `is*` predicate prefix,
   and verb families (`parse*` / `to*`) are never shortened away.
2. **A consolidated cross-language synonym** beats the PHP-specific name: `join` over `implode`,
   `slice` over `array_slice`.
3. **A widely-recognised abbreviation** — `Str`, `Num`, `Arr`, `Dt`, `Id`, `Url`, `Dir`, `Tmp`;
   `fooBarStr` over `fooBarString` — never an obscure one (`fmt`, `cnt`, `lvl`).
4. **Drop a word the qualified name already carries**: `File::mimeType` → `File::mime`.

Lean aggressive: propose the shorter form and let it be vetoed. An API-breaking rename lands only
on a major bump, shipping a `@deprecated` alias kept for one major cycle.

## Prefer `rak200/utils` over native PHP

Where a clean semantic equivalent exists, prefer it — `Str::repeat` over `str_repeat`,
`Str::lower` over `mb_strtolower`, `Arr::has` over `array_key_exists`, `Arr::is` over `is_array`.
Many helpers exist precisely to fix a native's shortcomings.

Keep the native only when:

- **(a)** there is no equivalent (`ord`, `fmod`, `iconv`, `htmlspecialchars`, case-insensitive
  `stripos`);
- **(b)** the wrapper would break the method's contract — a "never throws" sanitizer keeping
  `preg_replace(...) ?? ''` because the throwing wrapper would violate the guarantee; or
- **(c)** the method **is** the wrapper for that native (`Str::byteLen` on `strlen`).

## `use function` and first-class callables

- **Every native function a class still calls is imported via `use function`.** That block is the
  auditable inventory of the natives deliberately kept under the rule above. Functions only;
  constants stay unqualified.
- **Pass callables with first-class syntax, never as strings** — `func(...)`,
  `self::method(...)`, not `'func'` or `['Class', 'method']`. It keeps the reference statically
  checked, IDE-navigable, and bound by `use function`. It does not apply to APIs that take a
  function *name as data* (`function_exists`, `is_callable`), or when the symbol is computed at
  runtime.

## Member order

`constants → properties → constructor → non-magic methods → magic methods`. Don't drop a constant
beside its first use mid-class. Enforced by the formatter.

## Correctness over efficiency

Efficiency is never the goal and benchmarks are never chased — but needless work is still
avoided. Two costs are worth removing even when the tighter form reads worse: **a redundant
second pass** over the same data, and **an intermediate array** a single pass would avoid. The
other sanctioned lever is **laziness** — offer generator-returning variants for paths that may
handle large or unbounded data.

## Safe defaults

- **Strings are multibyte-safe by default** (`mb_*`).
- **Randomness is cryptographically secure only** — `random_int` / `random_bytes`, never `rand()`
  or `mt_rand()`.
- **Dates use `DateTimeImmutable`**; no mutable `DateTime`.
- **The public API takes and returns native PHP types** where possible.

## Documentation form

Layer 1 mandates that documentation exists; this is what it looks like in PHP.

- Every class carries a PHPDoc summary (one short paragraph) plus
  `@author rak200 <rak.ricardo@windowslive.com>`. The same attribution string is used wherever an
  author appears, `composer.json` included.
- Every `public` method carries a PHPDoc stating what it does. `@param` / `@return` / `@throws`
  are added **only when they convey something beyond the type signature** — units, semantics,
  edge-case behaviour, the condition of a throw.
- Private helpers are documented only when the implementation is non-obvious.

**Reference pages** live in `docs/`, sized by unit: an index (`docs/README.md`) with a
`Class | Doc | What it covers` table, and one page per unit with a `# ClassName` heading, a
back-link, the one-line summary matching the class PHPDoc, a fenced `use` block, a `## Contents`
TOC anchoring every public method, then one section per method group. Show each call's output in
a trailing `// …` comment; document `bare` and `*OrNull` variants on one line together; give
time-sensitive helpers a *shape* example rather than a literal value.

## Testing form

Layer 1 sets the policy — mirrored trees, one file per unit, contract assertions, the
`minCoveredMsi: 100` floor. In PHP:

- **PHPUnit**, with `failOnWarning` and `failOnRisky` enabled.
- The test namespace mirrors the source namespace: `Rak200\Foo\Bar` →
  `Rak200\Foo\Tests\BarTest`.
- Test methods use PSR-12 camelCase — `testReturnsBlankForWhitespaceOnly`, never snake_case.
- **Infection** guards test quality: config committed as `infection.json5.dist`, coverage from
  Xdebug locally and pcov in CI. A surviving mutant is *ignored* only when provably equivalent,
  via an in-code `@infection-ignore-all` anchored on the **smallest node that isolates just the
  equivalent construct** — so the condition mutators on the same line stay live. The annotation
  has no per-mutator scope; the config-side `ignoreSourceCodeByRegex` is avoided because a
  full-line regex rots into dead config on any edit to that line.

## IDE

The PHP linter in VS Code is **DEVSENSE PHP Tools**. Its diagnostic codes are 4-digit `PHP0xxx`
(`PHP0420`, never `PHP420` — a 3-digit code silently fails to match). Suppress a false positive
per declaration with a `@suppress PHP0420` PHPDoc tag (preferred — it keeps the check live
everywhere else), or per path via `php.problems.exclude`. `@suppress` tags are inert to PHPStan,
php-cs-fixer and CI.

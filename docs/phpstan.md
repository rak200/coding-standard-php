# `phpstan.neon.dist`

[← Reference](README.md)

The static-analysis standard: the level, the three settings beside it, and two rules of its own.
Extended by a consumer, never copied.

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
- [The rules it ships](#the-rules-it-ships)
  - [`rak200.firstClassCallable`](#rak200firstclasscallable)
  - [`rak200.docSummary`](#rak200docsummary)
- [Why `paths` is yours](#why-paths-is-yours)

---

## What it sets

| parameter | value | default? |
| --- | --- | --- |
| `level` | `max` | — |
| `treatPhpDocTypesAsCertain` | `false` | no |
| `reportUnmatchedIgnoredErrors` | `true` | yes, declared as a lock |
| `reportIgnoresWithoutComments` | `true` | no |
| `rak200.documented` | `[%currentWorkingDirectory%/src]` | this package's own, read by `rak200.docSummary` |

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

## The rules it ships

Both are registered here, so a repository that includes this file runs them with nothing to add.
Where one is genuinely wrong for a line, the suppression goes on that line, names the identifier,
and says why.

### `rak200.firstClassCallable`

A callable is passed with first-class syntax, never as a string or an array.

```php
array_map('trim', $xs);           // Parameter $callback receives the callable 'trim' as a string; …
array_map(trim(...), $xs);        // not reported
usort($xs, [$this, 'compare']);   // reported, as an array
usort($xs, $this->compare(...));  // not reported
function_exists('iconv');         // not reported: the parameter takes a string
```

It reports only where both hold:

- **The parameter wants a callable and nothing else.** `function_exists()` takes a string and
  `is_callable()` takes anything, so a name handed to them is data. A `callable-string` parameter
  asks for the name itself, and a `callable|string` one takes any string. An optional callable
  still counts.
- **The argument is a callable written as a literal**, a constant string or a constant array. A
  name computed at run time is not one, and neither is a closure.

It follows functions, methods, static methods and constructors, by position, by name, and into a
trailing variadic. A call whose callee is computed at run time — `$fn(…)`, `$object->$method(…)`,
`new $class(…)` — is not followed.

Where a string is genuinely what the callee needs, the suppression goes on the argument's line and
says why:

```php
// @phpstan-ignore rak200.firstClassCallable (the queue serialises the handler by name)
```

Why the rule exists is in [CONVENTIONS.md](../CONVENTIONS.md), §*`use function` and first-class
callables*.

### `rak200.docSummary`

Every class, interface, trait and enum, and every public method, carries a PHPDoc summary, in the
directories `rak200.documented` names.

```php
final class Bare {}                                   // Bare has no PHPDoc summary.

/** @internal */
final class Tagged {}                                 // reported: the first text is a tag

/** Converts one value at a time. */
final class Converter
{
    /** @return list<string> */
    public function names(): array { return []; }     // Converter::names() has no PHPDoc summary.

    private function helper(): void {}                // not reported: not public
}
```

The summary is the doc comment's first text, so a doc comment that opens with a tag has none, and
`{@see Other}` counts as text. The rule asks that a summary is there; whether it says the right
thing is for a reader. An anonymous class has no name to document and is passed over. A trait is
read where it is declared.

`rak200.documented` is `[%currentWorkingDirectory%/src]`: a doc comment on public code is the
documentation that travels with the package, and a test never leaves its repository. A repository
whose code lives elsewhere replaces the list rather than adding to it:

```neon
parameters:
    rak200:
        documented!:
            - %currentWorkingDirectory%/lib
```

Why the rule exists is in [CONVENTIONS.md](../CONVENTIONS.md), §*Documentation form*.

[↑ Back to top](#phpstanneondist)

---

## Why `paths` is yours

PHPStan resolves a relative `paths` against the file that declares it. A `paths: [src, tests]` set
here would name directories **inside the installed package**, which do not exist, and the analyser
refuses to start. It was shipped that way once and found the first time a repository tried it.

The level and the analyser's settings are the standard; what to look at is the consumer's business.

[↑ Back to top](#phpstanneondist)

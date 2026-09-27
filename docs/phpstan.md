# `phpstan.neon.dist`

[← Reference](README.md)

The static-analysis standard: the level, the three settings beside it, and one rule of its own.
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
- [The rule it ships](#the-rule-it-ships)
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

## The rule it ships

`rak200.firstClassCallable` — a callable is passed with first-class syntax, never as a string or
an array. Registered here, so a repository that includes this file runs it with nothing to add.

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

[↑ Back to top](#phpstanneondist)

---

## Why `paths` is yours

PHPStan resolves a relative `paths` against the file that declares it. A `paths: [src, tests]` set
here would name directories **inside the installed package**, which do not exist, and the analyser
refuses to start. It was shipped that way once and found the first time a repository tried it.

The level and the analyser's settings are the standard; what to look at is the consumer's business.

[↑ Back to top](#phpstanneondist)

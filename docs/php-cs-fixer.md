# `.php-cs-fixer.dist.php`

[← Reference](README.md)

The formatter preset. It returns a configured `PhpCsFixer\Config` that the consumer completes with
its own finder.

```php
// .php-cs-fixer.dist.php
return (require __DIR__ . '/vendor/rak200/coding-standard-php/.php-cs-fixer.dist.php')
    ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests']));
```

## Contents

- [Why the finder is yours](#why-the-finder-is-yours)
- [The coverage annotation the runner cannot read](#the-coverage-annotation-the-runner-cannot-read)
- [The one risky rule](#the-one-risky-rule)

---

## Why the finder is yours

Symfony's `Finder` validates a directory **the moment it is added**. A finder baked into this
package names directories inside the installed package, so `require` throws before the consumer's
file has done anything. Like PHPStan's `paths`, it was shipped that way and found on first use.

The rules are the standard; what to format is the consumer's business.

[↑ Back to top](#php-cs-fixerdistphp)

---

## The coverage annotation the runner cannot read

`@PhpCsFixer` enables `php_unit_test_class_requires_covers`, which inserts `@coversNothing` into
any test class that declares no coverage target. **PHPUnit removed docblock metadata in 12**, and
this package pins `^13.2` — so the formatter half of the standard was writing a form the test-runner
half does not read. The rule is off here for that reason.

Measured in this repository, with every class still carrying the annotation:

```
requireCoverageMetadata="true"  ->  Tests: 54, Risky: 54
                                    "This test does not define a code coverage target
                                     but is expected to do so"
```

**`php_unit_attributes` is not the fix to reach for.** It is absent from the preset, and adding it
would convert each `@coversNothing` into a live `#[CoversNothing]` — which PHPUnit *does* read, and
which means what it says. On a repository that still carries the annotation, that conversion takes
coverage from full to zero, and it arrives through `composer fix`, a diff nobody reads. It becomes
safe only once every consumer declares real targets with `#[CoversClass]`.

What this standard does **not** yet do is require those targets. Declaring one is
`#[CoversClass(TheClassUnderTest::class)]` plus `requireCoverageMetadata="true"` in `phpunit.xml`,
where `failOnRisky` makes an omission exit 1 — but nothing here mandates either, so a test class
that names nothing it covers is today caught by no mechanism.

[↑ Back to top](#php-cs-fixerdistphp)

---

## The one risky rule

`declare(strict_types=1)` at the top of every file has been in this standard since its first
version, and until now **nothing enforced it**. The rule that does is `declare_strict_types`, and
it ships in three rule sets — `@PhpCsFixer:risky`, `@Symfony:risky`, `@PHP70Migration:risky` — all
of them risky, none of them taken here. The effective rule set never mentioned the declaration.

Measured against a class with no declaration, before enabling it:

```
composer lint             ->  Found 0 of 1 files, exit 0
phpstan, level: max       ->  [OK] No errors
```

Both gates pass a file the standard says must not exist. Enabled, the same file:

```
composer lint             ->  Found 1 of 1 files, exit 8
                              + declare(strict_types=1);
```

**It is the single rule, not the set.** `setRiskyAllowed(true)` is already on, so a risky rule can
be named on its own; the sets that carry this one also carry dozens of other semantic changes that
would have to be measured consumer by consumer first.

The cost of omitting it is not stylistic. Without the declaration PHP coerces scalars at every call
boundary: `ping(1)` against a `string` parameter succeeds and hands the body `'1'`. That is the
silent-wrong-answer class `level: max`, `treatPhpDocTypesAsCertain: false` and
`reportUnmatchedIgnoredErrors: true` are all there to remove.

[↑ Back to top](#php-cs-fixerdistphp)

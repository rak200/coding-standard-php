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

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

---

## Why the finder is yours

Symfony's `Finder` validates a directory **the moment it is added**. A finder baked into this
package names directories inside the installed package, so `require` throws before the consumer's
file has done anything. Like PHPStan's `paths`, it was shipped that way and found on first use.

The rules are the standard; what to format is the consumer's business.

[↑ Back to top](#php-cs-fixerdistphp)

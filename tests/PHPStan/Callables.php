<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\Tests\PHPStan;

use Closure;

/**
 * Signatures for {@see FirstClassCallableRuleTest} to call: one parameter of each shape the
 * rule has to tell apart, and two targets to point a callable at. The analyser reads this
 * class; nothing calls it.
 */
final class Callables
{
    /** @var null|(Closure(string): string) */
    public readonly ?Closure $fn;

    /** @param null|(callable(string): string) $fn */
    public function __construct(?callable $fn = null)
    {
        $this->fn = $fn === null ? null : $fn(...);
    }

    /** @param callable(string): string $fn */
    public function take(callable $fn): void {}

    /** @param callable(string): string $fn */
    public static function takeStatically(callable $fn): void {}

    /** @param callable(string): string ...$fns */
    public function takeMany(callable ...$fns): void {}

    /** @param callable(string): string $fn */
    public function between(string $label, callable $fn): void {}

    /** @param callable-string $fn */
    public function takeName(string $fn): void {}

    /** @param (callable(string): string)|string $fn */
    public function takeEither(callable|string $fn): void {}

    public function twice(string $text): string
    {
        return $text . $text;
    }

    public static function thrice(string $text): string
    {
        return $text . $text . $text;
    }
}

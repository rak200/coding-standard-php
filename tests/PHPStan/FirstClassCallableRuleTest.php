<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\Tests\PHPStan;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Rak200\CodingStandardPhp\PHPStan\FirstClassCallableRule;

use function array_filter;
use function bin2hex;
use function file_put_contents;
use function random_bytes;
use function sprintf;
use function sys_get_temp_dir;
use function unlink;

/**
 * @internal
 *
 * @extends RuleTestCase<FirstClassCallableRule>
 */
#[CoversClass(FirstClassCallableRule::class)]
final class FirstClassCallableRuleTest extends RuleTestCase
{
    /**
     * The file a case is analysed in: the snippet lands on {@see self::LINE}, inside a function
     * whose parameters give it something of every shape to pass, and {@see Callables} holds
     * the signatures it calls.
     *
     * It is written per case rather than kept in the tree, because a file of deliberate
     * violations under `tests/` is one this repository's own analysis would grade — and
     * grading it is the one thing this repository's analysis must not be asked to skip.
     */
    private const string FIXTURE = <<<'PHP'
        <?php

        declare(strict_types=1);

        namespace Rak200\CodingStandardPhp\Tests\PHPStan;

        use Closure;

        /**
         * @param list<string>            $xs
         * @param class-string<Callables> $class
         * @param Closure(string): string $closure
         */
        function fixture(array $xs, Callables $callables, ?Callables $maybe, string $name, string $class, Closure $closure): void
        {
            %s
        }
        PHP;

    private const int LINE = 16;

    private const string MESSAGE = 'Parameter $%s receives the callable %s as %s; pass it with first-class callable syntax.';

    private ?string $file = null;

    protected function tearDown(): void
    {
        if ($this->file !== null) {
            unlink($this->file);
        }

        parent::tearDown();
    }

    public static function getAdditionalConfigFiles(): array
    {
        // The configuration a consumer includes, so each case runs under the settings the rule
        // ships with, and the registration below is the registration a consumer gets.
        return [__DIR__ . '/../../phpstan.neon.dist'];
    }

    public function testTheConfigurationAConsumerIncludesRegistersIt(): void
    {
        // A rule that is tested and never registered is enforced by nothing, and nothing else
        // in this repository would notice.
        $registered = array_filter(
            self::getContainer()->getServicesByTag('phpstan.rules.rule'),
            static fn (mixed $rule): bool => $rule instanceof FirstClassCallableRule,
        );

        self::assertCount(1, $registered);
    }

    #[DataProvider('literals')]
    public function testReportsACallableWrittenAsALiteral(string $snippet, string $parameter, string $given, string $form): void
    {
        $this->analyse([$this->fixture($snippet)], [
            [sprintf(self::MESSAGE, $parameter, $given, $form), self::LINE],
        ]);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function literals(): iterable
    {
        yield 'a function, by name' => ['array_map("trim", $xs);', 'callback', "'trim'", 'a string'];

        yield 'a later position' => ['usort($xs, "strcmp");', 'callback', "'strcmp'", 'a string'];

        yield 'a method, as an array' => ['array_map([$callables, "twice"], $xs);', 'callback', "array{Rak200\\CodingStandardPhp\\Tests\\PHPStan\\Callables, 'twice'}", 'an array'];

        yield 'a static method, as an array' => ['array_map([Callables::class, "thrice"], $xs);', 'callback', "array{'Rak200\\\\CodingStandardPhp\\\\Tests\\\\PHPStan\\\\Callables', 'thrice'}", 'an array'];

        yield 'a static method, by name' => ['array_map("Rak200\CodingStandardPhp\Tests\PHPStan\Callables::thrice", $xs);', 'callback', "'Rak200\\\\CodingStandardPhp\\\\Tests\\\\PHPStan\\\\Callables::thrice'", 'a string'];

        yield 'to a method' => ['$callables->take("trim");', 'fn', "'trim'", 'a string'];

        yield 'to a nullsafe method' => ['$maybe?->take("trim");', 'fn', "'trim'", 'a string'];

        yield 'to a static method' => ['Callables::takeStatically("trim");', 'fn', "'trim'", 'a string'];

        yield 'to a constructor' => ['new Callables("trim");', 'fn', "'trim'", 'a string'];

        yield 'to the callable one of two parameters' => ['$callables->between("trim", "trim");', 'fn', "'trim'", 'a string'];

        yield 'past the first variadic' => ['$callables->takeMany(trim(...), "strtoupper");', 'fns', "'strtoupper'", 'a string'];

        yield 'by name, out of order' => ['array_map(array: $xs, callback: "trim");', 'callback', "'trim'", 'a string'];

        yield 'through a variable' => ['$fn = "strtoupper"; array_map($fn, $xs);', 'callback', "'strtoupper'", 'a string'];

        yield 'to Closure::fromCallable' => ['Closure::fromCallable("trim");', 'callback', "'trim'", 'a string'];
    }

    #[DataProvider('exempt')]
    public function testLeavesAloneWhatIsNotACallableLiteral(string $snippet): void
    {
        $this->analyse([$this->fixture($snippet)], []);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function exempt(): iterable
    {
        yield 'first-class syntax' => ['array_map(trim(...), $xs);'];

        yield 'a name as data' => ['function_exists("iconv");'];

        yield 'a name to test' => ['is_callable("trim");'];

        yield 'a parameter that asks for the name' => ['$callables->takeName("trim");'];

        yield 'a parameter that takes any string too' => ['$callables->takeEither("trim");'];

        yield 'a name computed at run time' => ['if (is_callable($name)) { array_map($name, $xs); }'];

        yield 'a string that names no callable' => ['$callables->take("nope");'];

        yield 'a call through a variable' => ['$closure("trim");'];

        yield 'a method named at run time' => ['$callables->{$name}("trim");'];

        yield 'a class named at run time' => ['$class::takeStatically("trim");'];

        yield 'a static method named at run time' => ['Callables::{$name}("trim");'];

        yield 'a class instantiated by name' => ['new $class("trim");'];

        yield 'a function that does not exist' => ['undefined_function("trim");'];

        yield 'a method that does not exist' => ['$callables->undefinedMethod("trim");'];

        yield 'a function with no parameters' => ['time("trim");'];
    }

    public function testReadsEveryArgumentOfACall(): void
    {
        // An argument that is no callable at all does not end the reading, and two literals
        // in one call are two reports.
        $this->analyse([$this->fixture('$callables->takeMany("nope", "trim", "strtoupper");')], [
            [sprintf(self::MESSAGE, 'fns', "'trim'", 'a string'), self::LINE],
            [sprintf(self::MESSAGE, 'fns', "'strtoupper'", 'a string'), self::LINE],
        ]);
    }

    public function testReportsOnTheLineOfTheArgument(): void
    {
        // A call that spans lines is reported where the literal is, which is also the line a
        // suppression has to sit on.
        $snippet = <<<'PHP'
            array_map(
                "trim",
                $xs,
            );
            PHP;

        $this->analyse([$this->fixture($snippet)], [
            [sprintf(self::MESSAGE, 'callback', "'trim'", 'a string'), self::LINE + 1],
        ]);
    }

    protected function getRule(): Rule
    {
        return new FirstClassCallableRule(self::createReflectionProvider());
    }

    private function fixture(string $snippet): string
    {
        $this->file = sys_get_temp_dir() . '/rak200-first-class-callable-' . bin2hex(random_bytes(8)) . '.php';
        file_put_contents($this->file, sprintf(self::FIXTURE, $snippet));

        return $this->file;
    }
}

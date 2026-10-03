<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\Tests\PHPStan;

use PHPStan\File\FileHelper;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Rak200\CodingStandardPhp\PHPStan\DocSummaryRule;

use function array_filter;
use function array_map;
use function array_reverse;
use function bin2hex;
use function file_put_contents;
use function is_dir;
use function mkdir;
use function random_bytes;
use function rmdir;
use function sys_get_temp_dir;
use function unlink;

/**
 * @internal
 *
 * @extends RuleTestCase<DocSummaryRule>
 */
#[CoversClass(DocSummaryRule::class)]
final class DocSummaryRuleTest extends RuleTestCase
{
    /**
     * What every fixture opens with. A case's code starts on the line after it, so a line in a
     * case is reported {@see self::OFFSET} lines further down the file.
     *
     * The fixtures are written per case into a directory of their own rather than kept in the
     * tree: a fixture's doc comments are the thing under test, and the formatter rewrites doc
     * comments in any file it is given.
     */
    private const string HEADER = "<?php\n\ndeclare(strict_types=1);\n\nnamespace Fixture;\n\n";

    private const int OFFSET = 6;

    /** Where this test's fixtures are written; `src` under it is what the rule reads. */
    private string $root = '';

    /** @var list<string> */
    private array $written = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/rak200-doc-summary-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->written) as $path) {
            is_dir($path) ? rmdir($path) : unlink($path);
        }

        parent::tearDown();
    }

    public static function getAdditionalConfigFiles(): array
    {
        // The configuration a consumer includes, so the registration below is the one a
        // consumer gets.
        return [__DIR__ . '/../../phpstan.neon.dist'];
    }

    public function testTheConfigurationAConsumerIncludesRegistersIt(): void
    {
        // A rule that is tested and never registered is enforced by nothing; one registered
        // over the wrong directory is enforced over nothing, and stays green either way.
        $registered = array_filter(
            self::getContainer()->getServicesByTag('phpstan.rules.rule'),
            static fn (mixed $rule): bool => $rule instanceof DocSummaryRule,
        );

        self::assertCount(1, $registered);

        // The analyser's working directory is the project root on the command line. Inside
        // this test's container it is PHPStan's own, which is why it is read rather than
        // assumed.
        $root = self::getContainer()->getParameter('currentWorkingDirectory');
        self::assertIsString($root);
        self::assertSame(['documented' => [$root . '/src']], self::getContainer()->getParameter('rak200'));
    }

    /**
     * @param list<array{string, int}> $expected
     */
    #[DataProvider('undocumented')]
    public function testReportsWhatHasNoSummary(string $code, array $expected): void
    {
        $this->analyse(
            [$this->fixture($code)],
            array_map(static fn (array $error): array => [$error[0], $error[1] + self::OFFSET], $expected),
        );
    }

    /**
     * @return iterable<string, array{string, list<array{string, int}>}>
     */
    public static function undocumented(): iterable
    {
        yield 'a class with no doc comment' => [
            <<<'PHP'
                final class Bare {}
                PHP,
            [['Fixture\Bare has no PHPDoc summary.', 1]],
        ];

        yield 'a class whose doc comment is only a tag' => [
            <<<'PHP'
                /** @internal */
                final class Tagged {}
                PHP,
            [['Fixture\Tagged has no PHPDoc summary.', 2]],
        ];

        yield 'a class whose doc comment is empty' => [
            <<<'PHP'
                /** */
                final class Blank {}
                PHP,
            [['Fixture\Blank has no PHPDoc summary.', 2]],
        ];

        yield 'a doc comment whose first text is a tag' => [
            <<<'PHP'
                /**
                 * @internal
                 *
                 * Prose after a tag is not a summary.
                 */
                final class Late {}
                PHP,
            [['Fixture\Late has no PHPDoc summary.', 6]],
        ];

        yield 'a public method with no doc comment' => [
            <<<'PHP'
                /** Documented. */
                final class Host
                {
                    public function run(): void {}
                }
                PHP,
            [['Fixture\Host::run() has no PHPDoc summary.', 4]],
        ];

        yield 'a public method whose doc comment is only a type' => [
            <<<'PHP'
                /** Documented. */
                final class Host
                {
                    /** @return list<string> */
                    public function names(): array
                    {
                        return [];
                    }
                }
                PHP,
            [['Fixture\Host::names() has no PHPDoc summary.', 5]],
        ];

        yield 'a method public by default, and a constructor' => [
            <<<'PHP'
                /** Documented. */
                final class Host
                {
                    public function __construct() {}

                    function run(): void {}
                }
                PHP,
            [
                ['Fixture\Host::__construct() has no PHPDoc summary.', 4],
                ['Fixture\Host::run() has no PHPDoc summary.', 6],
            ],
        ];

        yield 'an interface and its method' => [
            <<<'PHP'
                interface Contract
                {
                    public function run(): void;
                }
                PHP,
            [
                ['Fixture\Contract has no PHPDoc summary.', 1],
                ['Fixture\Contract::run() has no PHPDoc summary.', 3],
            ],
        ];

        yield 'a trait and its method' => [
            <<<'PHP'
                trait Shared
                {
                    public function run(): void {}
                }
                PHP,
            [
                ['Fixture\Shared has no PHPDoc summary.', 1],
                ['Fixture\Shared::run() has no PHPDoc summary.', 3],
            ],
        ];

        yield 'an enum, its case and its method' => [
            <<<'PHP'
                enum Status: string
                {
                    case On = 'on';

                    public function label(): string
                    {
                        return $this->value;
                    }
                }
                PHP,
            [
                ['Fixture\Status has no PHPDoc summary.', 1],
                ['Fixture\Status::On has no PHPDoc summary.', 3],
                ['Fixture\Status::label() has no PHPDoc summary.', 5],
            ],
        ];

        yield 'a public property' => [
            <<<'PHP'
                /** Documented. */
                final class Host
                {
                    public string $name = '';
                }
                PHP,
            [['Fixture\Host::$name has no PHPDoc summary.', 4]],
        ];

        yield 'a promoted property, on its own line' => [
            <<<'PHP'
                /** Documented. */
                final class Host
                {
                    /** Takes the name. */
                    public function __construct(
                        public readonly string $name,
                        private readonly int $hidden,
                    ) {}
                }
                PHP,
            [['Fixture\Host::$name has no PHPDoc summary.', 6]],
        ];

        yield 'a property promoted by a constructor that is not public' => [
            <<<'PHP'
                /** Documented. */
                final class Host
                {
                    private function __construct(public readonly int $count) {}
                }
                PHP,
            [['Fixture\Host::$count has no PHPDoc summary.', 4]],
        ];

        yield 'a constant public by default, and an interface constant' => [
            <<<'PHP'
                /** Documented. */
                final class Host
                {
                    const LIMIT = 3;
                }

                interface Contract
                {
                    public const string NAME = 'contract';
                }
                PHP,
            [
                ['Fixture\Host::LIMIT has no PHPDoc summary.', 4],
                ['Fixture\Contract has no PHPDoc summary.', 7],
                ['Fixture\Contract::NAME has no PHPDoc summary.', 9],
            ],
        ];
    }

    #[DataProvider('documented')]
    public function testLeavesAloneWhatIsSummarised(string $code): void
    {
        $this->analyse([$this->fixture($code)], []);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function documented(): iterable
    {
        yield 'a one-line summary' => [
            <<<'PHP'
                /** Does one thing. */
                final class OneLine
                {
                    /** Runs it. */
                    public function run(): void {}
                }
                PHP,
        ];

        yield 'a summary over several lines, tags after it' => [
            <<<'PHP'
                /**
                 * Does one thing,
                 * and says so at length.
                 *
                 * @internal
                 */
                final class Several {}
                PHP,
        ];

        yield 'a summary that opens with an inline tag' => [
            <<<'PHP'
                /** {@see Several}, by another name. */
                final class Alias {}
                PHP,
        ];

        yield 'members that are not public' => [
            <<<'PHP'
                /** Documented. */
                class Hidden
                {
                    private const int LIMIT = 3;

                    protected string $name = '';

                    /** Takes what it needs. */
                    public function __construct(private readonly int $count, int $plain = 0) {}

                    protected function hook(): void {}

                    private function helper(): void {}
                }
                PHP,
        ];

        yield 'public members with a summary each' => [
            <<<'PHP'
                /** Documented. */
                final class Host
                {
                    /** The most it takes. */
                    public const int LIMIT = 3;

                    /** What it is called. */
                    public string $name = '';

                    /** Takes the size. */
                    public function __construct(
                        /** How many it holds. */
                        public readonly int $size,
                    ) {}
                }

                /** On or off. */
                enum Status: string
                {
                    /** Running. */
                    case On = 'on';
                }
                PHP,
        ];

        yield 'an anonymous class' => [
            <<<'PHP'
                /** Documented. */
                final class Factory
                {
                    /** Makes one. */
                    public function make(): object
                    {
                        return new class {
                            public function run(): void {}
                        };
                    }
                }
                PHP,
        ];
    }

    public function testReadsOnlyTheDirectoriesWhereCodeTravels(): void
    {
        // A sibling whose name starts with the documented one is not inside it.
        $this->analyse([$this->fixture('final class Elsewhere {}', 'srcx')], []);
    }

    protected function getRule(): Rule
    {
        return new DocSummaryRule([$this->root . '/src'], self::getContainer()->getByType(FileHelper::class));
    }

    private function fixture(string $code, string $directory = 'src'): string
    {
        $directory = $this->root . '/' . $directory;
        $file = $directory . '/Fixture.php';

        mkdir($directory, 0o777, true);
        file_put_contents($file, self::HEADER . $code . "\n");
        $this->written = [...$this->written, $this->root, $directory, $file];

        return $file;
    }
}

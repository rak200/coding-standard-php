<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\PHPStan;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Property;
use PhpParser\PrettyPrinter\Standard;
use PHPStan\Analyser\Scope;
use PHPStan\File\FileHelper;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_map;
use function preg_replace;
use function sprintf;
use function str_starts_with;
use function trim;

/**
 * Layer 2 (PHP) — every class, interface, trait and enum, and every public member of one, carries
 * a PHPDoc summary wherever the code travels.
 *
 * A doc comment on public code is the documentation a package carries inside itself: it is what
 * a consumer's editor shows over a call, long after `docs/` was left behind. That is why the
 * rule reads only the directories named in `rak200.documented`, which is `src/` unless a
 * repository says otherwise. A test's methods are public and documented by their names, and a
 * test never leaves its repository.
 *
 * A public member is a method, a property, a constant or an enum case. A promoted property is a
 * property like any other, and its doc comment sits on the parameter that declares it.
 *
 * What it can ask is that a summary exists: the doc comment's first text is prose, not a tag.
 * `@return list<string>` alone documents a type, which the signature could already say. Whether
 * the summary says the right thing is not something a rule can read. An anonymous class has no
 * name to document and is passed over; a trait is read where it is declared, not where it is
 * used.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 *
 * @implements Rule<ClassLike>
 */
final class DocSummaryRule implements Rule
{
    /** The identifier its reports carry, which is the name a suppression gives. */
    public const string IDENTIFIER = 'rak200.docSummary';

    /** @var list<string> */
    private readonly array $directories;

    /**
     * Built by the analyser from `rak200.documented`, the directories whose code travels. Each
     * is absolute, and normalised the way the analyser normalises the files it reads, so a
     * path written with either separator matches.
     *
     * @param list<string> $documented
     */
    public function __construct(array $documented, FileHelper $fileHelper)
    {
        $this->directories = array_map(
            static fn (string $directory): string => $fileHelper->normalizePath($directory) . \DIRECTORY_SEPARATOR,
            $documented,
        );
    }

    /** Every declaration that can hold methods: a class, an interface, a trait and an enum. */
    public function getNodeType(): string
    {
        return ClassLike::class;
    }

    /** One report for a declaration with no summary, and one for each such public member. */
    public function processNode(Node $node, Scope $scope): array
    {
        // Only a named declaration has a namespaced name. An anonymous class reaches this rule
        // carrying a name PHPStan generated for it, and no namespaced name at all.
        $name = $node->namespacedName;

        if ($name === null || !$this->travels($scope->getFile())) {
            return [];
        }

        $errors = [];

        foreach (self::documentable($node, $name->toString()) as [$label, $documentable]) {
            if (!self::summarised($documentable->getDocComment())) {
                $errors[] = RuleErrorBuilder::message(sprintf('%s has no PHPDoc summary.', $label))
                    ->identifier(self::IDENTIFIER)
                    ->line($documentable->getStartLine())
                    ->build()
                ;
            }
        }

        return $errors;
    }

    /**
     * What on a declaration carries a doc comment of its own, each under the name a report
     * gives it: the declaration first, then every public member in the order it is written —
     * methods, properties, promoted ones included, constants and enum cases.
     *
     * @return iterable<array{string, Node}>
     */
    private static function documentable(ClassLike $declaration, string $name): iterable
    {
        yield [$name, $declaration];

        foreach ($declaration->stmts as $member) {
            if ($member instanceof ClassMethod) {
                if ($member->isPublic()) {
                    yield [sprintf('%s::%s()', $name, $member->name->name), $member];
                }

                // A parameter is public only when it promotes a property, so these are the
                // public properties the method declares, whatever its own visibility.
                foreach ($member->params as $parameter) {
                    if ($parameter->isPublic()) {
                        yield [sprintf('%s::%s', $name, new Standard()->prettyPrintExpr($parameter->var)), $parameter];
                    }
                }
            } elseif ($member instanceof Property && $member->isPublic()) {
                yield [sprintf('%s::$%s', $name, $member->props[0]->name->name), $member];
            } elseif ($member instanceof ClassConst && $member->isPublic()) {
                yield [sprintf('%s::%s', $name, $member->consts[0]->name->name), $member];
            } elseif ($member instanceof EnumCase) {
                yield [sprintf('%s::%s', $name, $member->name->name), $member];
            }
        }
    }

    /** Whether a file sits under one of the directories the rule reads. */
    private function travels(string $file): bool
    {
        foreach ($this->directories as $directory) {
            if (str_starts_with($file, $directory)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a doc comment opens with prose. With the delimiters and each line's leading
     * asterisk taken away, the first text left is the summary, unless it is a tag.
     */
    private static function summarised(?Doc $comment): bool
    {
        $text = trim(preg_replace('~^\s*/\*\*|\s*\*/\s*$|^\s*\*~m', '', $comment?->getText() ?? '') ?? '');

        return $text !== '' && $text[0] !== '@';
    }
}

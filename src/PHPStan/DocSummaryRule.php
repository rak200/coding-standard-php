<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\PHPStan;

use PhpParser\Comment\Doc;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
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
 * Layer 2 (PHP) — every class, interface, trait and enum, and every public method, carries a
 * PHPDoc summary wherever the code travels.
 *
 * A doc comment on public code is the documentation a package carries inside itself: it is what
 * a consumer's editor shows over a call, long after `docs/` was left behind. That is why the
 * rule reads only the directories named in `rak200.documented`, which is `src/` unless a
 * repository says otherwise. A test's methods are public and documented by their names, and a
 * test never leaves its repository.
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

    /** One report for a declaration with no summary, and one for each such public method. */
    public function processNode(Node $node, Scope $scope): array
    {
        // Only a named declaration has a namespaced name. An anonymous class reaches this rule
        // carrying a name PHPStan generated for it, and no namespaced name at all.
        $name = $node->namespacedName;

        if ($name === null || !$this->travels($scope->getFile())) {
            return [];
        }

        $errors = [];

        if (!self::summarised($node->getDocComment())) {
            $errors[] = RuleErrorBuilder::message(sprintf('%s has no PHPDoc summary.', $name->toString()))
                ->identifier(self::IDENTIFIER)
                ->build()
            ;
        }

        foreach ($node->getMethods() as $method) {
            if ($method->isPublic() && !self::summarised($method->getDocComment())) {
                $errors[] = RuleErrorBuilder::message(sprintf('%s::%s() has no PHPDoc summary.', $name->toString(), $method->name->name))
                    ->identifier(self::IDENTIFIER)
                    ->line($method->getStartLine())
                    ->build()
                ;
            }
        }

        return $errors;
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

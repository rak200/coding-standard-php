<?php

declare(strict_types=1);

/**
 * Layer 2 (PHP) — code style. Consumed by a repository as:
 *
 *     <?php
 *     return (require 'vendor/rak200/coding-standard-php/.php-cs-fixer.dist.php')
 *         ->setFinder(PhpCsFixer\Finder::create()->in([__DIR__ . '/src', __DIR__ . '/tests']));
 *
 * The preset is @PhpCsFixer — the strictest consolidated one. Every deviation below is a
 * deliberate, load-bearing override with its reason stated; the ecosystem's rule is that a
 * narrowed standard is an exception that must justify itself, never a default.
 *
 * Run it on the language floor (8.4). A newer runtime needs PHP_CS_FIXER_IGNORE_ENV=1 and
 * prints a harmless version warning.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 */

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@PhpCsFixer' => true,
        '@PHP84Migration' => true,

        // The `use function` inventory. Every native a class still calls is imported at the
        // top of the file, which makes the block an auditable list of the natives deliberately
        // kept — the rule that governs when a native beats a helper is in CONVENTIONS.md.
        // Functions only: constants stay unqualified.
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => false,
            'import_functions' => true,
        ],
        'native_function_invocation' => false,

        // `constants → properties → constructor → non-magic methods → magic methods`.
        // Magic last, so the interesting surface of a class is what you read first.
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant_public', 'constant_protected', 'constant_private',
                'property_public', 'property_protected', 'property_private',
                'construct',
                'method_public', 'method_protected', 'method_private',
                'magic',
                'destruct',
            ],
        ],

        // Natural reading order in comparisons. Yoda conditions defend against an assignment
        // typo that PHPStan at level max already catches, and they cost every reader.
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],

        // `'x ' . $y`, never the preset's glued `'x '.$y`. Concatenation is an operator and
        // reads like one.
        'concat_space' => ['spacing' => 'one'],

        // Two rules that would destroy a documented idiom, and are off for that reason alone.
        //
        // A PHPStan error caused by a deficient native stub — a functionMap entry that erases
        // the value type — is fixed with a localized inline `/** @var */` stating the genuinely
        // known type. Restructuring code or adding runtime work to satisfy an analyser is the
        // wrong trade. `phpdoc_to_comment` would demote that annotation to a plain comment,
        // and `return_assignment` would inline the very variable it annotates.
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
        'return_assignment' => false,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()
            ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    );

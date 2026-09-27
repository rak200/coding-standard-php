<?php

declare(strict_types=1);

namespace Rak200\CodingStandardPhp\PHPStan;

use PhpParser\Node;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\ArgumentsNormalizer;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ExtendedParametersAcceptor;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\VerbosityLevel;

use function count;
use function sprintf;

/**
 * Layer 2 (PHP) — a callable is passed with first-class syntax, never as a string or an array.
 *
 * The rule is a preference, and its reason is the moment a mistake shows. `trim(...)` is a
 * call, so the editor resolves the name while it is being typed and marks `trimm(...)` on the
 * spot. `'trim'` is text: `'trimm'` looks exactly as right, and nothing says otherwise until
 * the analyser runs. It also escapes `use function`, so a native kept that way is missing from
 * the inventory a class keeps of them.
 *
 * It reads types, and that is what makes the exceptions CONVENTIONS.md lists cost nothing. A
 * report needs two things at once. The parameter must want a callable and nothing else:
 * `function_exists()` takes a string and `is_callable()` takes anything, so a name handed to
 * them is data, and a `callable-string` parameter asks for the name itself. And the argument
 * must be a callable written as a literal — a name computed at run time is not one, whatever
 * it turns out to hold.
 *
 * @author rak200 <rak.ricardo@windowslive.com>
 *
 * @implements Rule<CallLike>
 */
final class FirstClassCallableRule implements Rule
{
    /** The identifier its reports carry, which is the name a suppression gives. */
    public const string IDENTIFIER = 'rak200.firstClassCallable';

    public function __construct(private readonly ReflectionProvider $reflectionProvider) {}

    public function getNodeType(): string
    {
        return CallLike::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $variants = $this->variants($node, $scope);

        if ($variants === []) {
            return [];
        }

        // `getArgs()` refuses a first-class callable, and none arrives here: PHPStan hands a
        // rule `trim(...)` as a node of its own, never as the call it is written as.
        $acceptor = ParametersAcceptorSelector::selectFromArgs($scope, $node->getArgs(), $variants);
        $parameters = $acceptor->getParameters();
        $errors = [];

        // Named arguments come back in the order of the parameters they bind to, so from here
        // a position is a parameter. A position past the last one binds to it, which in any
        // call the analyser accepts means it is variadic.
        foreach (ArgumentsNormalizer::reorderArgs($acceptor, $node->getArgs()) ?? [] as $position => $arg) {
            $parameter = $parameters[$position] ?? $parameters[count($parameters) - 1] ?? null;

            if ($parameter === null || !$this->wantsCallable($parameter->getType())) {
                continue;
            }

            $given = $scope->getType($arg->value);

            if (!$given->isCallable()->yes()) {
                continue;
            }

            $form = match (true) {
                $given->getConstantStrings() !== [] => 'a string',
                $given->getConstantArrays() !== [] => 'an array',
                default => null,
            };

            if ($form !== null) {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Parameter $%s receives the callable %s as %s; pass it with first-class callable syntax.',
                    $parameter->getName(),
                    $given->describe(VerbosityLevel::precise()),
                    $form,
                ))->identifier(self::IDENTIFIER)->line($arg->getStartLine())->build();
            }
        }

        return $errors;
    }

    /**
     * Whether a parameter asks for a callable and for nothing else. An optional callable is
     * still one, so `null` is set aside; a parameter that is a string as well asks for the
     * name, and a literal is what it should get.
     */
    private function wantsCallable(Type $type): bool
    {
        $type = TypeCombinator::removeNull($type);

        return $type->isCallable()->yes() && !$type->isString()->yes();
    }

    /**
     * The signatures a call may bind to, or none where the callee is not known.
     *
     * @return list<ExtendedParametersAcceptor>
     */
    private function variants(CallLike $call, Scope $scope): array
    {
        if ($call instanceof FuncCall) {
            return $call->name instanceof Name && $this->reflectionProvider->hasFunction($call->name, $scope)
                ? $this->reflectionProvider->getFunction($call->name, $scope)->getVariants()
                : [];
        }

        return $this->method($call, $scope)?->getVariants() ?? [];
    }

    /**
     * The method a call reaches, the constructor where the call is `new`, and nothing where
     * the name or the class is computed at run time.
     *
     * A nullsafe call gets nothing here, and that is deliberate. PHPStan walks `$a?->b()` a
     * second time, as the method call it is once `$a` is known not to be null, and that walk
     * reaches this rule too; reading both reported every such literal twice.
     */
    private function method(CallLike $call, Scope $scope): ?ExtendedMethodReflection
    {
        if ($call instanceof MethodCall) {
            return $call->name instanceof Identifier
                ? $scope->getMethodReflection($scope->getType($call->var), $call->name->name)
                : null;
        }

        if ($call instanceof StaticCall) {
            return $call->class instanceof Name && $call->name instanceof Identifier
                ? $scope->getMethodReflection($scope->resolveTypeByName($call->class), $call->name->name)
                : null;
        }

        return $call instanceof New_ && $call->class instanceof Name
            ? $scope->getMethodReflection($scope->resolveTypeByName($call->class), '__construct')
            : null;
    }
}

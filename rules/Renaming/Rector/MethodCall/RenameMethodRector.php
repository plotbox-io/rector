<?php

declare (strict_types=1);
namespace Rector\Renaming\Rector\MethodCall;

use PhpParser\BuilderHelpers;
use PhpParser\Node;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Interface_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ReflectionProvider;
use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\NodeManipulator\ClassManipulator;
use Rector\Core\Rector\AbstractScopeAwareRector;
use Rector\Core\Reflection\ReflectionResolver;
use Rector\Renaming\Contract\MethodCallRenameInterface;
use Rector\Renaming\ValueObject\MethodCallRename;
use Rector\Renaming\ValueObject\MethodCallRenameWithArrayKey;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use RectorPrefix202306\Webmozart\Assert\Assert;
/**
 * @see \Rector\Tests\Renaming\Rector\MethodCall\RenameMethodRector\RenameMethodRectorTest
 */
final class RenameMethodRector extends AbstractScopeAwareRector implements ConfigurableRectorInterface
{
    /**
     * @readonly
     * @var \Rector\Core\NodeManipulator\ClassManipulator
     */
    private $classManipulator;
    /**
     * @readonly
     * @var \Rector\Core\Reflection\ReflectionResolver
     */
    private $reflectionResolver;
    /**
     * @readonly
     * @var \PHPStan\Reflection\ReflectionProvider
     */
    private $reflectionProvider;
    /**
     * @var MethodCallRenameInterface[]
     */
    private $methodCallRenames = [];
    public function __construct(ClassManipulator $classManipulator, ReflectionResolver $reflectionResolver, ReflectionProvider $reflectionProvider)
    {
        $this->classManipulator = $classManipulator;
        $this->reflectionResolver = $reflectionResolver;
        $this->reflectionProvider = $reflectionProvider;
    }
    public function getRuleDefinition() : RuleDefinition
    {
        return new RuleDefinition('Turns method names to new ones.', [new ConfiguredCodeSample(<<<'CODE_SAMPLE'
$someObject = new SomeExampleClass;
$someObject->oldMethod();
CODE_SAMPLE
, <<<'CODE_SAMPLE'
$someObject = new SomeExampleClass;
$someObject->newMethod();
CODE_SAMPLE
, [new MethodCallRename('SomeExampleClass', 'oldMethod', 'newMethod')])]);
    }
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes() : array
    {
        return [MethodCall::class, StaticCall::class, Class_::class, Interface_::class];
    }
    /**
     * @param MethodCall|StaticCall|Class_|Interface_ $node
     */
    public function refactorWithScope(Node $node, Scope $scope) : ?Node
    {
        if ($node instanceof Class_ || $node instanceof Interface_) {
            return $this->refactorClass($node, $scope);
        }
        return $this->refactorMethodCallAndStaticCall($node);
    }
    /**
     * @param mixed[] $configuration
     */
    public function configure(array $configuration) : void
    {
        Assert::allIsAOf($configuration, MethodCallRenameInterface::class);
        $this->methodCallRenames = $configuration;
    }
    /**
     * @param \PhpParser\Node\Expr\MethodCall|\PhpParser\Node\Expr\StaticCall $call
     */
    private function shouldSkipClassMethod($call, MethodCallRenameInterface $methodCallRename) : bool
    {
        $classReflection = $this->reflectionResolver->resolveClassReflectionSourceObject($call);
        if (!$classReflection instanceof ClassReflection) {
            return \false;
        }
        $targetClass = $methodCallRename->getClass();
        if (!$this->reflectionProvider->hasClass($targetClass)) {
            return \false;
        }
        $targetClassReflection = $this->reflectionProvider->getClass($targetClass);
        if ($classReflection->getName() === $targetClassReflection->getName()) {
            return \false;
        }
        // different with configured ClassLike source? it is a child, which may has old and new exists
        if (!$classReflection->hasMethod($methodCallRename->getOldMethod())) {
            return \false;
        }
        return $classReflection->hasMethod($methodCallRename->getNewMethod());
    }
    /**
     * @param \PhpParser\Node\Stmt\Class_|\PhpParser\Node\Stmt\Interface_ $classOrInternace
     */
    private function hasClassNewClassMethod($classOrInternace, MethodCallRenameInterface $methodCallRename) : bool
    {
        return (bool) $classOrInternace->getMethod($methodCallRename->getNewMethod());
    }
    private function shouldKeepForParentInterface(MethodCallRenameInterface $methodCallRename, ?ClassReflection $classReflection) : bool
    {
        if (!$classReflection instanceof ClassReflection) {
            return \false;
        }
        // interface can change current method, as parent contract is still valid
        if (!$classReflection->isInterface()) {
            return \false;
        }
        return $this->classManipulator->hasParentMethodOrInterface($methodCallRename->getObjectType(), $methodCallRename->getOldMethod(), $methodCallRename->getNewMethod());
    }
    /**
     * @param \PhpParser\Node\Stmt\Class_|\PhpParser\Node\Stmt\Interface_ $classOrInterface
     * @return \PhpParser\Node\Stmt\Class_|\PhpParser\Node\Stmt\Interface_|null
     */
    private function refactorClass($classOrInterface, Scope $scope)
    {
        if (!$scope->isInClass()) {
            return null;
        }
        $classReflection = $scope->getClassReflection();
        $hasChanged = \false;
        foreach ($classOrInterface->getMethods() as $classMethod) {
            foreach ($this->methodCallRenames as $methodCallRename) {
                if (!$this->isName($classMethod->name, $methodCallRename->getOldMethod())) {
                    continue;
                }
                if (!$this->nodeTypeResolver->isMethodStaticCallOrClassMethodObjectType($classMethod, $methodCallRename->getObjectType())) {
                    continue;
                }
                if ($this->shouldKeepForParentInterface($methodCallRename, $classReflection)) {
                    continue;
                }
                if ($this->hasClassNewClassMethod($classOrInterface, $methodCallRename)) {
                    continue;
                }
                $classMethod->name = new Identifier($methodCallRename->getNewMethod());
                $hasChanged = \true;
            }
        }
        if ($hasChanged) {
            return $classOrInterface;
        }
        return null;
    }
    /**
     * @param \PhpParser\Node\Expr\StaticCall|\PhpParser\Node\Expr\MethodCall $call
     * @return \PhpParser\Node\Expr\ArrayDimFetch|null|\PhpParser\Node\Expr\MethodCall|\PhpParser\Node\Expr\StaticCall
     */
    private function refactorMethodCallAndStaticCall($call)
    {
        foreach ($this->methodCallRenames as $methodCallRename) {
            if (!$this->isName($call->name, $methodCallRename->getOldMethod())) {
                continue;
            }
            if (!$this->nodeTypeResolver->isMethodStaticCallOrClassMethodObjectType($call, $methodCallRename->getObjectType())) {
                continue;
            }
            if ($this->shouldSkipClassMethod($call, $methodCallRename)) {
                continue;
            }
            $call->name = new Identifier($methodCallRename->getNewMethod());
            if ($methodCallRename instanceof MethodCallRenameWithArrayKey) {
                return new ArrayDimFetch($call, BuilderHelpers::normalizeValue($methodCallRename->getArrayKey()));
            }
            return $call;
        }
        return null;
    }
}

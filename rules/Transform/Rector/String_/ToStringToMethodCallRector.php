<?php

declare (strict_types=1);
namespace Rector\Transform\Rector\String_;

use PhpParser\Node;
use PhpParser\Node\Expr\Cast\String_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Type\ObjectType;
use Rector\Core\Contract\Rector\ConfigurableRectorInterface;
use Rector\Core\Rector\AbstractRector;
use Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample;
use Symplify\RuleDocGenerator\ValueObject\RuleDefinition;
use RectorPrefix20220606\Webmozart\Assert\Assert;
/**
 * @see \Rector\Tests\Transform\Rector\String_\ToStringToMethodCallRector\ToStringToMethodCallRectorTest
 */
final class ToStringToMethodCallRector extends \Rector\Core\Rector\AbstractRector implements \Rector\Core\Contract\Rector\ConfigurableRectorInterface
{
    /**
     * @var array<string, string>
     */
    private $methodNamesByType = [];
    public function getRuleDefinition() : \Symplify\RuleDocGenerator\ValueObject\RuleDefinition
    {
        return new \Symplify\RuleDocGenerator\ValueObject\RuleDefinition('Turns defined code uses of "__toString()" method  to specific method calls.', [new \Symplify\RuleDocGenerator\ValueObject\CodeSample\ConfiguredCodeSample(<<<'CODE_SAMPLE'
$someValue = new SomeObject;
$result = (string) $someValue;
$result = $someValue->__toString();
CODE_SAMPLE
, <<<'CODE_SAMPLE'
$someValue = new SomeObject;
$result = $someValue->getPath();
$result = $someValue->getPath();
CODE_SAMPLE
, ['SomeObject' => 'getPath'])]);
    }
    /**
     * @return array<class-string<Node>>
     */
    public function getNodeTypes() : array
    {
        return [\PhpParser\Node\Expr\Cast\String_::class, \PhpParser\Node\Expr\MethodCall::class];
    }
    /**
     * @param String_|MethodCall $node
     */
    public function refactor(\PhpParser\Node $node) : ?\PhpParser\Node
    {
        if ($node instanceof \PhpParser\Node\Expr\Cast\String_) {
            return $this->processStringNode($node);
        }
        return $this->processMethodCall($node);
    }
    /**
     * @param mixed[] $configuration
     */
    public function configure(array $configuration) : void
    {
        \RectorPrefix20220606\Webmozart\Assert\Assert::allString(\array_keys($configuration));
        \RectorPrefix20220606\Webmozart\Assert\Assert::allString($configuration);
        /** @var array<string, string> $configuration */
        $this->methodNamesByType = $configuration;
    }
    private function processStringNode(\PhpParser\Node\Expr\Cast\String_ $string) : ?\PhpParser\Node
    {
        foreach ($this->methodNamesByType as $type => $methodName) {
            if (!$this->isObjectType($string->expr, new \PHPStan\Type\ObjectType($type))) {
                continue;
            }
            return $this->nodeFactory->createMethodCall($string->expr, $methodName);
        }
        return null;
    }
    private function processMethodCall(\PhpParser\Node\Expr\MethodCall $methodCall) : ?\PhpParser\Node
    {
        foreach ($this->methodNamesByType as $type => $methodName) {
            if (!$this->isObjectType($methodCall->var, new \PHPStan\Type\ObjectType($type))) {
                continue;
            }
            if (!$this->isName($methodCall->name, '__toString')) {
                continue;
            }
            $methodCall->name = new \PhpParser\Node\Identifier($methodName);
            return $methodCall;
        }
        return null;
    }
}

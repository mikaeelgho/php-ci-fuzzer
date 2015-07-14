<?php

namespace PHPCfg\AstVisitor;

use PhpParser\NodeVisitorAbstract;
use PhpParser\Node;

class MagicStringResolver extends NodeVisitorAbstract {
    
    protected $classStack = [];
    protected $parentStack = [];
    protected $functionStack = [];
    protected $methodStack = [];

    public function enterNode(Node $node) {
        if ($node instanceof Node\Stmt\ClassLike) {
            $this->classStack[] = $node->namespacedName->toString();
            if (!empty($node->extends) && !is_array($node->extends)) {
                // Should always be fully qualified
                $this->parentStack[] = $node->extends->toString();
            } else {
                $this->parentStack[] = '';
            }
        } elseif ($node instanceof Node\Stmt\Function_) {
            $this->functionStack[] = $node->namespacedName->toString();
        } elseif ($node instanceof Node\Stmt\ClassMethod) {
            $this->methodStack[] = end($this->classStack) . '::' . $node->name;
        } elseif ($node instanceof Node\Name) {
            switch (strtolower($node->toString())) {
                case 'self':
                    if (!empty($this->classStack)) {
                        return new Node\Name\FullyQualified(end($this->classStack), $node->getAttributes());
                    }
                    break;
                case 'parent':
                    if (!empty($this->parentStack) && '' !== end($this->parentStack)) {
                        return new Node\Name\FullyQualified(end($this->parentStack), $node->getAttributes());
                    }
            }
        } elseif ($node instanceof Node\Scalar\MagicConst\Class_) {
            if (!empty($this->classStack)) {
                return new Node\Scalar\String_(end($this->classStack), $node->getAttributes());
            }
        } elseif ($node instanceof Node\Scalar\MagicConst\Trait_) {
            // Traits can't nest, so this works...
            if (!empty($this->classStack)) {
                return new Node\Scalar\String_(end($this->classStack), $node->getAttributes());
            }
        } elseif ($node instanceof Node\Scalar\MagicConst\Namespace_) {
            if (!empty($this->classStack)) {
                return new Node\Scalar\String_($this->stripClass(end($this->classStack)), $node->getAttributes());
            }
        } elseif ($node instanceof Node\Scalar\MagicConst\Function_) {
            if (!empty($this->functionStack)) {
                return new Node\Scalar\String_(end($this->functionStack), $node->getAttributes());
            }
        } elseif ($node instanceof Node\Scalar\MagicConst\Method) {
            if (!empty($this->methodStack)) {
                return new Node\Scalar\String_(end($this->methodStack), $node->getAttributes());
            }
        } elseif ($node instanceof Node\Scalar\MagicConst\Line) {
            return new Node\Scalar\LNumber($node->getLine(), $node->getAttributes());
        }
    }

    public function leaveNode(Node $node) {
        if ($node instanceof Node\Stmt\ClassLike) {
            assert(end($this->classStack) === $node->namespacedName->toString());
            array_pop($this->classStack);
            array_pop($this->parentStack);
        } elseif ($node instanceof Node\Stmt\Function_) {
            assert(end($this->functionStack) === $node->namespacedName->toString());
            array_pop($this->functionStack);
        } elseif ($node instanceof Node\Stmt\ClassMethod) {
            assert(end($this->methodStack) === end($this->classStack) . '::' . $node->name);
            array_pop($this->methodStack);
        }
    }

    private function stripClass($class) {
        $parts = explode('\\', $class);
        array_pop($parts);
        return implode('\\', $parts);
    }

}
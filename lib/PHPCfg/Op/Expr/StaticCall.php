<?php

declare(strict_types=1);

/**
 * This file is part of PHP-CFG, a Control flow graph implementation for PHP
 *
 * @copyright 2015 Anthony Ferrara. All rights reserved
 * @license MIT See LICENSE at the root of the project for more info
 */

namespace PHPCfg\Op\Expr;

use PHPCfg\Block;
use PHPCfg\Func;
use PHPCfg\Op\Expr;
use PhpCfg\Operand;

class StaticCall extends Expr
{
    public Operand $class;

    public Operand $name;

    public array $args;

    public ?Block $inBlock;

    public ?Block $call;

    public function __construct(Operand $class, Operand $name, array $args, array $attributes, ?Block $inBlock)
    {
        parent::__construct($attributes);
        $this->class = $this->addReadRef($class);
        $this->name = $this->addReadRef($name);
        $this->args = $this->addReadRefs(...$args);
        $this->inBlock = $inBlock;
        $this->call = null;
        Func::addMethodCall($this);
    }

    public function updateCall()
    {
        $children = Func::findRelatedMethodBlocks($this->class, $this->name);
        foreach ($children as $methodBlock) {
            $this->inBlock->children[] = $methodBlock;
        }
        $this->call = empty($children) ? null : current($children)->cfg;
    }

    public function getVariableNames(): array
    {
        return ['class', 'name', 'args', 'result'];
    }

    public function getSubBlocks(): array
    {
        return ['call'];
    }
}

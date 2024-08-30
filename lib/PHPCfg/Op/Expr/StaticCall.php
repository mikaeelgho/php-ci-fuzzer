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
use PHPCfg\Op\Expr;
use PhpCfg\Operand;

class StaticCall extends Expr
{
    public Operand $class;

    public Operand $name;

    public array $args;

    public ?Block $call;

    public function __construct(Operand $class, Operand $name, array $args, array $attributes, ?Block $call)
    {
        parent::__construct($attributes);
        $this->class = $this->addReadRef($class);
        $this->name = $this->addReadRef($name);
        $this->args = $this->addReadRefs(...$args);
        $this->call = $call;
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

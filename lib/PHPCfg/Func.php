<?php

declare(strict_types=1);

/**
 * This file is part of PHP-CFG, a Control flow graph implementation for PHP
 *
 * @copyright 2015 Anthony Ferrara. All rights reserved
 * @license MIT See LICENSE at the root of the project for more info
 */

namespace PHPCfg;

use PHPCfg\Op\CallableOp;

class Func extends Op
{
    private static array $allFunctions = [];

    /* Constants for the $flags property.
     * The first six flags match PhpParser Class_ flags. */
    const FLAG_PUBLIC = 0x01;

    const FLAG_PROTECTED = 0x02;

    const FLAG_PRIVATE = 0x04;

    const FLAG_STATIC = 0x08;

    const FLAG_ABSTRACT = 0x10;

    const FLAG_FINAL = 0x20;

    const FLAG_RETURNS_REF = 0x40;

    const FLAG_CLOSURE = 0x80;

    /** @var string */
    public $name;

    /** @var int */
    public $flags;

    /** @var */
    public Op\Type $returnType;

    /** @var Operand\Literal */
    public $class;

    /** @var Op\Expr\Param[] */
    public $params;

    /** @var Block|null */
    public $cfg;

    /** @var CallableOp|null */
    public $callableOp;

    public function __construct(string $name, int $flags, Op\Type $returnType, ?Operand $class, array $attributes = [])
    {
        parent::__construct($attributes);
        $this->name = $name;
        $this->flags = $flags;
        $this->returnType = $returnType;
        $this->class = $class;
        $this->params = [];
        $this->cfg = new Block();
        Func::$allFunctions[] = $this;
    }

    public function getScopedName(): string
    {
        if (null !== $this->class) {
            return $this->class->value . '::' . $this->name;
        }

        return $this->name;
    }

    public static function findRelatedMethodBlocks(?Operand $className, Operand $methodName): array
    {
        if ((is_null($className) || $className instanceof Operand\Literal) && $methodName instanceof Operand\Literal) {
            $scopedName = $methodName->value;
            if (null !== $className) {
                $scopedName = $className->value . '::' . $methodName->value;
            }

            foreach (Func::$allFunctions as $func) {
                if ($func->getScopedName() == $scopedName) {
                    return [$func];
                }
            }
        }
        return [];
    }
}

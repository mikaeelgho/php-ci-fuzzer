<?php

declare(strict_types=1);

/**
 * This file is part of PHP-CFG, a Control flow graph implementation for PHP
 *
 * @copyright 2015 Anthony Ferrara. All rights reserved
 * @license MIT See LICENSE at the root of the project for more info
 */

namespace PHPCfg;

class Block
{
    /** @var Op[] */
    public $children = [];

    /** @var Block[] */
    public $parents = [];

    /** @var Op\Phi[] */
    public $phi = [];

    public $dead = false;

    public function __construct(self $parent = null)
    {
        if ($parent) {
            $this->parents[] = $parent;
        }
    }

    public function getAttributes(): array
    {
        if (count($this->phi) == 0) {
            return ['block' => $this];
        }
        return array_merge(current($this->phi)->getAttributes(), ['block' => $this]);
    }

    public function create()
    {
        return new static();
    }

    public function addParent(self $parent)
    {
        if (!in_array($parent, $this->parents, true)) {
            $this->parents[] = $parent;
        }
    }
}

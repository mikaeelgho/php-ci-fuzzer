<?php

declare(strict_types=1);

/**
 * This file is part of PHP-CFG, a Control flow graph implementation for PHP
 *
 * @copyright 2015 Anthony Ferrara. All rights reserved
 * @license MIT See LICENSE at the root of the project for more info
 */

namespace PHPCfg\Printer;

use PHPCfg\Func;
use PHPCfg\Op;
use PHPCfg\Printer;
use PHPCfg\Script;
use phpDocumentor\GraphViz\Edge;
use phpDocumentor\GraphViz\Graph;
use phpDocumentor\GraphViz\Node;

class GraphViz extends Printer
{
    protected $options = [
        'graph' => [],
        'node' => [
            'shape' => 'rect',
        ],
        'edge' => [],
    ];

    protected $graph;

    public function __construct(array $options = [])
    {
        parent::__construct();
        $this->options = $options + $this->options;
    }

    public function printScript(Script $script)
    {
        $i = 0;
        $graph = $this->createGraph();
        $rendered = [];
        $nodes = new \SplObjectStorage();
        $this->printFuncWithHeader($script->main, $graph, $nodes, $rendered, 'func_' . ++$i . '_');
        foreach ($script->functions as $func) {
            $this->printFuncWithHeader($func, $graph, $nodes, $rendered, 'func_' . ++$i . '_');
        }
        $this->addEdges($script->main->getScopedName(), $graph, $nodes, $rendered);
        foreach ($script->functions as $func) {
            $this->addEdges($func->getScopedName(), $graph, $nodes, $rendered);
        }

        return $graph;
    }

    public function printFunc(Func $func)
    {
        $graph = $this->createGraph();
        $rendered = [];
        $this->printFuncInfo($func, $graph, new \SplObjectStorage(), $rendered, '');

        return $graph;
    }

    public function printVars(Func $func)
    {
        $graph = Graph::create('vars');
        foreach ($this->options['graph'] as $name => $value) {
            $setter = 'set' . $name;
            $graph->{$setter}($value);
        }
        $rendered = $this->render($func->cfg);
        $nodes = new \SplObjectStorage();
        foreach ($rendered['varIds'] as $var) {
            if (empty($var->ops) && empty($var->usages)) {
                continue;
            }
            $id = $rendered['varIds'][$var];
            $output = $this->renderOperand($var);
            $nodes[$var] = $this->createNode('var_' . $id, $output);
            $graph->setNode($nodes[$var]);
        }
        foreach ($rendered['varIds'] as $var) {
            foreach ($var->ops as $write) {
                $b = $write->getAttribute('block');
                foreach ($write->getVariableNames() as $varName) {
                    $vs = $write->{$varName};
                    if (!is_array($vs)) {
                        $vs = [$vs];
                    }
                    foreach ($vs as $v) {
                        if (!$v || $write->isWriteVariable($varName) || !$nodes->contains($v)) {
                            continue;
                        }
                        $edge = $this->createEdge($nodes[$v], $nodes[$var]);
                        if ($b) {
                            $edge->setlabel('Block<' . $rendered['blockIds'][$b] . '>' . $write->getType() . ':' . $varName);
                        } else {
                            $edge->setlabel($write->getType() . ':' . $varName);
                        }
                        $graph->link($edge);
                    }
                }
            }
        }

        return $graph;
    }

    protected function printFuncWithHeader(Func $func, Graph $graph, \SplObjectStorage $nodes, array &$rendered, $prefix)
    {
        $name = $func->getScopedName();
        $header = $this->createNode(
            $prefix . 'header', (substr($func->getFile(), 12)) . ":" . ($func->getLine())
        );
        $graph->setNode($header);

        $start = $this->printFuncInfo($func, $graph, $nodes, $rendered, $prefix);
        $edge = $this->createEdge($header, $start);
        $graph->link($edge);
    }

    protected function printFuncInfo(Func $func, Graph $graph, \SplObjectStorage $nodes, array &$rendered, $prefix)
    {
        $newRendered = $this->render($func);
        $rendered[$func->getScopedName()] = $newRendered;
        foreach ($newRendered['blocks'] as $block) {
            $blockId = $newRendered['blockIds'][$block];
            $ops = $newRendered['blocks'][$block];
            $output = '';
            $firstLine = -1;
            $lastLine = -1;
            $fileName = "unknown";
            foreach ($ops as $op) {
                //$output .= $this->indent("\n" . $op['label'] . "\n\n");
                if ($op['op']->getFile() != 'unknown') {
                    $fileName = substr($op['op']->getFile(), 12);
                }
                if ($op['op']->getLine() != -1) {
                    $lastLine = $op['op']->getLine();
                    if ($firstLine == -1) {
                        $firstLine = $op['op']->getLine();
                    }
                }
            }
            if ($firstLine != -1) {
                $output .= $fileName . ":" . $firstLine . '-' . $lastLine;
            }
            $nodes[$block] = $this->createNode($prefix . 'block_' . $blockId, $output);
            $graph->setNode($nodes[$block]);
        }

        return $nodes[$func->cfg];
    }

    public function addEdges(string $funcScopedName, Graph $graph, \SplObjectStorage $nodes, array $rendered)
    {
        foreach ($rendered[$funcScopedName]['blocks'] as $block) {
            foreach ($rendered[$funcScopedName]['blocks'][$block] as $op) {
                foreach ($op['childBlocks'] as $child) {
                    $edge = $this->createEdge($nodes[$block], $nodes[$child['block']]);
                    $edge->setlabel($child['name']);
                    $graph->link($edge);
                }
            }
        }
    }

    /**
     * @param string $str
     */
    protected function indent($str, $levels = 1): string
    {
        if ($levels > 1) {
            $str = $this->indent($str, $levels - 1);
        }

        return str_replace(["\n", '\\l'], '\\l    ', $str);
    }

    private function createGraph()
    {
        $graph = Graph::create('cfg');
        foreach ($this->options['graph'] as $name => $value) {
            $setter = 'set' . $name;
            $graph->{$setter}($value);
        }

        return $graph;
    }

    private function createNode($id, $content)
    {
        $node = new Node($id, $content);
        foreach ($this->options['node'] as $name => $value) {
            $node->{'set' . $name}($value);
        }

        return $node;
    }

    private function createEdge(Node $from, Node $to)
    {
        $edge = new Edge($from, $to);
        foreach ($this->options['edge'] as $name => $value) {
            $edge->{'set' . $name}($value);
        }

        return $edge;
    }
}

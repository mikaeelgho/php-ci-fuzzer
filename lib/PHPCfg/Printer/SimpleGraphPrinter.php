<?php

declare(strict_types=1);

namespace PHPCfg\Printer;

use PHPCfg\Func;
use PHPCfg\Op\Phi;
use PHPCfg\Printer;
use PHPCfg\Script;
use phpDocumentor\GraphViz\Edge;
use phpDocumentor\GraphViz\Node;

class SimpleGraphPrinter extends Printer
{
    protected $options = [
        'graph' => [],
        'node' => [
            'shape' => 'rect',
        ],
        'edge' => [],
    ];

    protected $edges;

    public function __construct(array $options = [])
    {
        parent::__construct();
        $this->options = $options + $this->options;
    }

    public function printScript(Script $script)
    {
        $i = 0;
        $edges = [];// $this->createGraph();
        $rendered = [];
        $nodes = new \SplObjectStorage();
        $this->printFuncWithHeader($script->main, $edges, $nodes, $rendered, 'func_' . ++$i . '_');
        foreach ($script->functions as $func) {
            $this->printFuncWithHeader($func, $edges, $nodes, $rendered, 'func_' . ++$i . '_');
        }
        $this->addEdges($script->main->getScopedName(), $edges, $nodes, $rendered);
        foreach ($script->functions as $func) {
            $this->addEdges($func->getScopedName(), $edges, $nodes, $rendered);
        }

        $output = '';
        foreach ($edges as $edge) {
            if ($edge->getFrom()->getName() == ':-1:-1') continue;
            /**
             * @var Edge $edge
             */
            $output .= $edge->getFrom()->getName() . " > " . $edge->getTo()->getName() . "\n";
        }
        return $output;
    }

    protected function printFuncWithHeader(Func $func, array &$edges, \SplObjectStorage $nodes, array &$rendered, $prefix)
    {
        $name = $func->getScopedName();
        $header = $this->createNode(
            $prefix . 'header', (substr($func->getFile(), 12)) . ":" . ($func->getLine()) . ":" . ($func->getLine())
        );
        //$graph->setNode($header);

        $start = $this->printFuncInfo($func, $edges, $nodes, $rendered, $prefix);
        $edge = $this->createEdge($header, $start);
        $edges[] = ($edge);
    }

    protected function printFuncInfo(Func $func, array &$edges, \SplObjectStorage $nodes, array &$rendered, $prefix)
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
                if ($op['op'] instanceof Phi || $op['op'] instanceof Func) {
                    continue;
                }
                if ($op['op']->getFile() != 'unknown') {
                    $fileName = substr($op['op']->getFile(), 12);
                }
                if ($op['op']->getLine() != -1) {
                    $lastLine = max($op['op']->getLine(), $lastLine);
                    if ($firstLine == -1) {
                        $firstLine = $op['op']->getLine();
                    } else {
                        $firstLine = max($op['op']->getLine(), $firstLine);
                    }
                }
            }
            if ($firstLine != -1) {
                $output = $fileName . ":" . $firstLine . '-' . $lastLine;
            }
            $nodes[$block] = $this->createNode($prefix . 'block_' . $blockId, $output);
            //$graph->setNode($nodes[$block]);
        }

        return $nodes[$func->cfg];
    }

    public function addEdges(string $funcScopedName, array &$edges, \SplObjectStorage $nodes, array $rendered)
    {
        foreach ($rendered[$funcScopedName]['blocks'] as $block) {
            foreach ($rendered[$funcScopedName]['blocks'][$block] as $op) {
                foreach ($op['childBlocks'] as $child) {
                    if (strlen($nodes[$child['block']]->getName()) == 0) {
                        continue;
                    }
                    $edge = $this->createEdge($nodes[$block], $nodes[$child['block']]);
                    $edge->setlabel($child['name']);
                    $edges[] = ($edge);
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


    private function createNode($id, $content)
    {
        $node = new Node($content, $content);
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

    public function printFunc(Func $func)
    {
        // TODO: Implement printFunc() method.
    }
}

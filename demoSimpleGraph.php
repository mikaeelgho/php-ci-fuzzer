<?php

declare(strict_types=1);

/**
 * This file is part of PHP-CFG, a Control flow graph implementation for PHP
 *
 * @copyright 2015 Anthony Ferrara. All rights reserved
 * @license MIT See LICENSE at the root of the project for more info
 */

use PhpParser\ParserFactory;

require __DIR__ . '/vendor/autoload.php';

$inputFiles = getInputFiles($argc, $argv);

$parser = new PHPCfg\Parser((new ParserFactory())->create(ParserFactory::PREFER_PHP7));

$declarations = new PHPCfg\Visitor\DeclarationFinder();
$calls = new PHPCfg\Visitor\CallFinder();
$variables = new PHPCfg\Visitor\VariableFinder();

$traverser = new PHPCfg\Traverser();

$traverser->addVisitor($declarations);
$traverser->addVisitor($calls);
$traverser->addVisitor(new PHPCfg\Visitor\Simplifier());
$traverser->addVisitor($variables);

$combinedScript = mergeFiles($inputFiles, $parser);

$traverser->traverse($combinedScript);

$dumper = new PHPCfg\Printer\SimpleGraphPrinter();
echo $dumper->printScript($combinedScript);

function getInputFiles($argc, $argv)
{
    if ($argc >= 2) {
        $inputPath = $argv[1];

        if (is_dir($inputPath)) {
            // Collect all PHP files from the directory recursively
            $files = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($inputPath));
            foreach ($iterator as $file) {
                if ($file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
            return $files;
        } elseif (is_file($inputPath)) {
            // If it's a single file, return it
            return [$inputPath];
        } else {
            // Assuming multiple file paths are provided
            return array_slice($argv, 1);
        }
    }

    // Default case: use the script itself
    return [__FILE__];
}

function mergeFiles(array $files, PHPCfg\Parser $parser)
{
    $combinedScript = new PHPCfg\Script();

    foreach ($files as $file) {
        $code = file_get_contents($file);
        $script = $parser->parse($code, $file);

        // Combine the parsed script into the combined script
        foreach ($script->functions as $function) {
            $combinedScript->functions[] = $function;
        }
        if (!isset($combinedScript->main)) {
            $combinedScript->main = $script->main;
        }
    }

    return $combinedScript;
}

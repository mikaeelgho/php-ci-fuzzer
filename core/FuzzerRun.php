#!/usr/bin/env php
<?php declare(strict_types=1);

namespace PhpFuzzer;

error_reporting(E_ALL);

$foundAutoload = false;
foreach ([__DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../../autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        $foundAutoload = true;
        break;
    }
}

if (!$foundAutoload) {
    echo "Broken installation: Failed to find autoload.php.\n";
    exit(1);
}

$fuzzer = new Fuzzer();
exit($fuzzer->handleCliArgs());

<?php declare(strict_types=1);

namespace PhpFuzzer;

use PhpFuzzer\Instrumentation\FileInfo;
use PhpFuzzer\Instrumentation\Instrumentor;
use PhpFuzzer\Mutation\Mutator;
use PhpFuzzer\Mutation\RNG;

final class Fuzzer
{
    private Instrumentor $instrumentor;
    private Corpus $corpus;
    private string $corpusDir;
    private string $outputDir;
    private Mutator $mutator;
    private RNG $rng;
    private Config $config;
    public ?string $targetPath = null;

    private ?string $coverageDir = null;
    /** @var array<string, FileInfo> */
    private array $fileInfos = [];
    private ?string $lastInput = null;

    private int $runs = 0;
    private int $lastInterestingRun = 0;
    private int $initialFeatures;
    private float $startTime;
    private int $mutationDepthLimit = 5;
    private int $maxRuns = PHP_INT_MAX;
    private int $lenControlFactor = 200;
    private int $timeout = 3;

    // Counts all crashes, including duplicates
    private int $crashes = 0;
    private int $maxCrashes = 100;

    public function __construct()
    {
        $this->outputDir = getcwd();
        // $this->instrumentor = new Instrumentor(FuzzingContext::class);
        $this->rng = new RNG();
        $this->config = new Config();
        $this->mutator = new Mutator($this->rng, $this->config->dictionary);
        $this->corpus = new Corpus();
    }

    private function loadTarget(string $path): void
    {
        if (!is_file($path)) {
            throw new FuzzerException('Target "' . $path . '" does not exist');
        }

        $this->targetPath = $path;
        // Unbind $this and make config available as $config variable.
        (static function (Config $config) use ($path) {
            $fuzzer = $config; // For backwards compatibility.
            require $path;
        })($this->config);
    }

    public function setCorpusDir(string $path): void
    {
        $this->corpusDir = $path;
        if (!is_dir($this->corpusDir)) {
            throw new FuzzerException('Corpus directory "' . $this->corpusDir . '" does not exist');
        }
    }

    public function setCoverageDir(string $path): void
    {
        $this->coverageDir = $path;
    }

    public function fuzz(): void
    {
        if (!$this->loadCorpus()) {
            return;
        }

        // Start with a short maximum length, increase if we fail to make progress.
        $maxLen = min($this->config->maxLen, max(4, $this->corpus->getMaxLen()));

        // Don't count runs while loading the corpus.
        $this->runs = 0;
        $this->startTime = microtime(true);
        while ($this->runs < $this->maxRuns) {
            $origEntry = $this->corpus->getRandomEntry($this->rng);
            $input = $origEntry !== null ? $origEntry->input : "";
            $crossOverEntry = $this->corpus->getRandomEntry($this->rng);
            $crossOverInput = $crossOverEntry !== null ? $crossOverEntry->input : null;
            for ($m = 0; $m < $this->mutationDepthLimit; $m++) {
                $input = $this->mutator->mutate($input, $maxLen, $crossOverInput);
                $entry = $this->runInput($input);
                if ($entry->crashInfo) {
                    if ($this->corpus->addCrashEntry($entry)) {
                        $entry->storeAtPath($this->outputDir . '/crash-' . $entry->hash . '.txt');
                        $this->printCrash('CRASH', $entry);
                    } else {
                        echo "DUPLICATE CRASH\n";
                    }
                    if (++$this->crashes >= $this->maxCrashes) {
                        echo "Maximum of {$this->maxCrashes} crashes reached, aborting\n";
                        return;
                    }
                    break;
                }

                $this->corpus->computeUniqueFeatures($entry);
                if ($entry->uniqueFeatures) {
                    $this->corpus->addEntry($entry);
                    $entry->storeAtPath($this->corpusDir . '/' . $entry->hash . '.txt');

                    $this->lastInterestingRun = $this->runs;
                    $this->printAction('NEW', $entry);
                    break;
                }

                if ($origEntry !== null &&
                    \strlen($entry->input) < \strlen($origEntry->input) &&
                    $entry->hasAllUniqueFeaturesOf($origEntry)
                ) {
                    // Preserve unique features of original entry,
                    // even if they are not unique anymore at this point.
                    $entry->uniqueFeatures = $origEntry->uniqueFeatures;
                    if ($this->corpus->replaceEntry($origEntry, $entry)) {
                        $entry->storeAtPath($this->corpusDir . '/' . $entry->hash . '.txt');
                    }
                    unlink($origEntry->path);

                    $this->lastInterestingRun = $this->runs;
                    $this->printAction('REDUCE', $entry);
                    break;
                }
            }

            if ($maxLen < $this->config->maxLen) {
                // Increase max length if we haven't made progress in a while.
                $logMaxLen = (int)log($maxLen, 2);
                if (($this->runs - $this->lastInterestingRun) > $this->lenControlFactor * $logMaxLen) {
                    $maxLen = min($this->config->maxLen, $maxLen + $logMaxLen);
                    $this->lastInterestingRun = $this->runs;
                }
            }
        }
    }

    private function isAllowedException(\Throwable $e): bool
    {
        foreach ($this->config->allowedExceptions as $allowedException) {
            if ($e instanceof $allowedException) {
                return true;
            }
        }
        return false;
    }

    private function runInput(string $input): CorpusEntry
    {
        $this->runs++;
        if (\extension_loaded('pcntl')) {
            \pcntl_alarm($this->timeout);
        }

        // Remember the last input in case PHP generates a fatal error.
        $this->lastInput = $input;
        FuzzingContext::reset();
        $crashInfo = null;
        try {
            $output = [];
            $resultCode = 0;
            exec("docker run -it --rm -v $(pwd)/../:/app php-trace php /app/example/src/main.php $input && python ../php-trace/extract_trace.py", $output, $resultCode);
            echo "HELLO  " . $resultCode;
        } catch (\ParseError $e) {
            echo "PARSE ERROR $e\n";
            echo "INSTRUMENTATION BROKEN? -- ABORTING";
            exit(-1);
        } catch (\Throwable $e) {
            if (!$this->isAllowedException($e)) {
                $crashInfo = (string)$e;
            }
        }

        $features = $this->edgeCountsToFeatures(FuzzingContext::loadCountFromFile());
        return new CorpusEntry($input, $features, $crashInfo);
    }

    /**
     * @param array<int, int> $edgeCounts
     * @return array<int, bool>
     */
    private function edgeCountsToFeatures(array $edgeCounts): array
    {
        $features = [];
        foreach ($edgeCounts as $edge => $count) {
            $feature = $this->edgeCountToFeature($edge, $count);
            $features[$feature] = true;
        }
        return $features;
    }

    private function edgeCountToFeature(int $edge, int $count): int
    {
        if ($count < 4) {
            $encodedCount = $count - 1;
        } else if ($count < 8) {
            $encodedCount = 3;
        } else if ($count < 16) {
            $encodedCount = 4;
        } else if ($count < 32) {
            $encodedCount = 5;
        } else if ($count < 128) {
            $encodedCount = 6;
        } else {
            $encodedCount = 7;
        }
        return $encodedCount << 56 | $edge;
    }

    private function loadCorpus(): bool
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->corpusDir),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        $entries = [];
        foreach ($it as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $path = $file->getPathname();
            $input = file_get_contents($path);
            $entry = $this->runInput($input);
            $entry->path = $path;
            if ($entry->crashInfo) {
                $this->printCrash("CORPUS CRASH", $entry);
                return false;
            }

            $entries[] = $entry;
        }

        // Favor short entries.
        usort($entries, function (CorpusEntry $a, CorpusEntry $b) {
            return \strlen($a->input) <=> \strlen($b->input);
        });
        foreach ($entries as $entry) {
            $this->corpus->computeUniqueFeatures($entry);
            if ($entry->uniqueFeatures) {
                $this->corpus->addEntry($entry);
            }
        }
        $this->initialFeatures = $this->corpus->getNumFeatures();
        return true;
    }

    private function printAction(string $action, CorpusEntry $entry): void
    {
        $time = microtime(true) - $this->startTime;
        $mem = memory_get_usage();
        $numFeatures = $this->corpus->getNumFeatures();
        $numNewFeatures = $numFeatures - $this->initialFeatures;
        $maxLen = $this->corpus->getMaxLen();
        $maxLenLen = \strlen((string)$maxLen);
        echo sprintf(
            "%-6s run: %d (%4.0f/s), ft: %d (%.0f/s), corp: %d (%s), len: %{$maxLenLen}d/%d, t: %.0fs, mem: %s\n",
            $action, $this->runs, $this->runs / $time,
            $numFeatures, $numNewFeatures / $time,
            $this->corpus->getNumCorpusEntries(),
            $this->formatBytes($this->corpus->getTotalLen()),
            \strlen($entry->input), $maxLen,
            $time, $this->formatBytes($mem));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 10 * 1024) {
            return $bytes . 'b';
        } else if ($bytes < 10 * 1024 * 1024) {
            $kiloBytes = (int)round($bytes / 1024);
            return $kiloBytes . 'kb';
        } else {
            $megaBytes = (int)round($bytes / (1024 * 1024));
            return $megaBytes . 'mb';
        }
    }

    private function printCrash(string $prefix, CorpusEntry $entry): void
    {
        echo "$prefix in $entry->path!\n";
        echo $entry->crashInfo . "\n";
    }

    public function renderCoverage(): void
    {
        if ($this->coverageDir === null) {
            throw new FuzzerException('Missing coverage directory');
        }

        $renderer = new CoverageRenderer($this->coverageDir);
        $renderer->render($this->fileInfos, $this->corpus->getSeenBlockMap());
    }

    private function minimizeCrash(string $path): void
    {
        if (!is_file($path)) {
            throw new FuzzerException("Crash input \"$path\" does not exist");
        }

        $input = file_get_contents($path);
        $entry = $this->runInput($input);
        if (!$entry->crashInfo) {
            throw new FuzzerException("Crash input did not crash");
        }

        while ($this->runs < $this->maxRuns) {
            $newInput = $input;
            for ($m = 0; $m < $this->mutationDepthLimit; $m++) {
                $newInput = $this->mutator->mutate($newInput, $this->config->maxLen, null);
                if (\strlen($newInput) >= \strlen($input)) {
                    continue;
                }

                $newEntry = $this->runInput($newInput);
                if (!$newEntry->crashInfo) {
                    continue;
                }

                $newEntry->storeAtPath(getcwd() . '/minimized-' . md5($newInput) . '.txt');

                $len = \strlen($newInput);
                $this->printCrash("CRASH with length $len", $newEntry);
                $input = $newInput;
            }
        }
    }

    public function handleCliArgs(): int
    {
        try {
            $this->setCorpusDir('./core/corpus');
            $this->loadTarget('example/src/main.php');

            $this->setupTimeoutHandler();
            $this->setupErrorHandler();
            $this->setupShutdownHandler();
            $this->fuzz();
        } catch (FuzzerException $e) {
            echo $e->getMessage() . PHP_EOL;
            return 1;
        }
        return 0;
    }

    private function createTemporaryCorpusDirectory(): string
    {
        do {
            $corpusDir = sys_get_temp_dir() . '/corpus-' . mt_rand();
        } while (file_exists($corpusDir));
        if (!@mkdir($corpusDir)) {
            throw new FuzzerException("Failed to create temporary corpus directory $corpusDir");
        }
        return $corpusDir;
    }

    private function setupTimeoutHandler(): void
    {
        if (\extension_loaded('pcntl')) {
            \pcntl_signal(SIGALRM, function () {
                throw new \Error("Timeout of {$this->timeout} seconds exceeded");
            });
            \pcntl_async_signals(true);
        }
    }

    private function setupErrorHandler(): void
    {
        set_error_handler(function ($errno, $errstr, $errfile, $errline) {
            if (!(error_reporting() & $errno)) {
                return true;
            }

            throw new \Error(sprintf(
                '[%d] %s in %s on line %d', $errno, $errstr, $errfile, $errline));
        });
    }

    private function setupShutdownHandler(): void
    {
        // If a fatal error occurs, at least recover the crashing input.
        // TODO: We could support fork mode to continue fuzzing after this (and allow minimization).
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error === null || $error['type'] != E_ERROR || $this->lastInput === null) {
                return;
            }

            $crashInfo = "Fatal error: {$error['message']} in {$error['file']} on line {$error['line']}";
            $entry = new CorpusEntry($this->lastInput, [], $crashInfo);
            $entry->storeAtPath($this->outputDir . '/crash-' . $entry->hash . '.txt');
            $this->printCrash('CRASH', $entry);
        });
    }
}


error_reporting(E_ALL);

$foundAutoload = false;
foreach ([__DIR__ . '/../vendor/autoload.php'] as $file) {
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

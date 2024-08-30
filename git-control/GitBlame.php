<?php

namespace Digikala\Supernova\Lib\Composer;

class GitBlame
{
    const ERROR_DAYS_LIMIT = 3;

    public static function run()
    {
        $root = 'example/src/';
        $srcDir = "../$root";

        $recentFiles = self::getRecentFiles($srcDir, $root);

        foreach ($recentFiles as $ind => $file) {
            $recentFiles[$ind]['file'] = substr($file['file'], strlen($root));
            $recentFiles[$ind]['editor'] = self::getGitBlameInfo($srcDir, $recentFiles[$ind]['file'], $file['line']);
        }

        echo implode("\n", array_map(function ($x) {
            return $x['file'] . ":" . $x['line'] . ':' . $x['editor']['email'];
        }, $recentFiles));
        echo "\n";

    }

    private static function getGitBlameInfo(string $projectPath, string $filePath, string $lineNumber, int $retries = 30): ?array
    {
        // Use 'git blame' to get the commit author for the file and line with the error
        $gitBlameOutput = shell_exec("git -C $projectPath blame -e --unified=0 -L $lineNumber,+1 $filePath 2> /dev/null");
        if (!$gitBlameOutput) {
            if ($lineNumber > 1 && $retries > 0) {
                return self::getGitBlameInfo($projectPath, $filePath, intval($lineNumber) - 1, $retries - 1);
            } elseif ($retries > 0) {
                return self::getGitBlameInfo($projectPath, $filePath, intval($lineNumber) + 1, $retries - 1);
            } else {
                return null;
            }
        }
        preg_match('/\(<(.*?)> (20[123]\d-\d\d-\d\d)/', $gitBlameOutput, $authorAndDateMatches);

        // Extract the commit author from 'git blame' output
        if (!isset($authorAndDateMatches[1])) {
            if ($lineNumber > 1 && $retries > 0) {
                return self::getGitBlameInfo($projectPath, $filePath, intval($lineNumber) - 1, $retries - 1);
            } elseif ($retries > 0) {
                return self::getGitBlameInfo($projectPath, $filePath, intval($lineNumber) + 1, $retries - 1);
            } else {
                return null;
            }
        }
        return [
            'email' => $authorAndDateMatches[1],
            'date' => $authorAndDateMatches[2],
        ];
    }

    private static function getRecentFiles(string $srcDir, string $root): array
    {
        $baseCommitCommand = "git -C $srcDir rev-list --max-count=1 --before=\"" . self::ERROR_DAYS_LIMIT . " days ago\" HEAD";
        $gitDiff = shell_exec("git -C $srcDir diff --unified=0 --diff-filter=d $($baseCommitCommand)");
        $lines = explode("\n", $gitDiff);
        $result = [];
        $currentFile = '';
        $currentLineNumberPos = null;
        $currentLineNumberNeg = null;

        foreach ($lines as $line) {
            if (preg_match('/^diff --git a\/(.*) b\//', $line, $matches)) {
                $currentFile = $matches[1];
            } elseif (preg_match('/^@@ -(\d+).*\+(\d+),?(\d*) @@/', $line, $matches)) {
                $currentLineNumberNeg = (int)$matches[1];
                $currentLineNumberPos = (int)$matches[2];
            } elseif (strpos($line, '+') === 0 && strpos($line, '+++') !== 0) {
                if ($currentFile && $currentLineNumberPos) {
                    $result[] = ['file' => $currentFile, 'line' => $currentLineNumberPos];
                    $currentLineNumberPos++;
                }
            } elseif (strpos($line, '-') === 0 && strpos($line, '---') !== 0) {
                if ($currentFile && $currentLineNumberNeg !== null) {
                    $result[] = ['file' => $currentFile, 'line' => $currentLineNumberNeg];
                    $currentLineNumberNeg++;
                }
            }
        }

        return array_filter($result, fn($res) => (strpos($res['file'], $root) === 0));
    }
}

(new GitBlame())->run();
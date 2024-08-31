<?php declare(strict_types=1);


namespace PhpFuzzer;

use PhpFuzzer\Instrumentation\FileInfo;

final class CoverageRenderer
{
    private string $outDir;

    public function __construct(string $outDir)
    {
        $this->outDir = $outDir;
    }

    /**
     * @param FileInfo[] $fileInfos
     * @param array<int, bool> $seenBlocks
     */
    public function render(array $fileInfos, array $seenBlocks): void
    {
        @mkdir($this->outDir);

        $overview = "<table>\n";

        $prefix = Util::getCommonPathPrefix(array_keys($fileInfos));
        $totalNumCovered = 0;
        $totalNumTotal = 0;
        ksort($fileInfos);
        foreach ($fileInfos as $path => $fileInfo) {
            $posToBlockIndex = array_flip($fileInfo->blockIndexToPos);
            ksort($posToBlockIndex);

            $code = file_get_contents($path);
            $result = '<pre>';
            $lastPos = 0;
            $numCovered = 0;
            $numTotal = count($posToBlockIndex);
            foreach ($posToBlockIndex as $pos => $blockIndex) {
                $result .= htmlspecialchars(\substr($code, $lastPos, $pos - $lastPos));
                $covered = isset($seenBlocks[$blockIndex]);
                $numCovered += $covered;
                $color = $covered ? "green" : "red";
                $result .= '<span style="background-color: ' . $color . '">' . $code[$pos] . '</span>';
                $lastPos = $pos + 1;
            }
            $result .= htmlspecialchars(\substr($code, $lastPos));
            $result .= '</pre>';

            $shortPath = str_replace($prefix, '', $path);
            $outPath = $this->outDir . '/' . $shortPath . '.html';
            @mkdir(dirname($outPath), 0777, true);
            file_put_contents($outPath, $result);

            $overview .= <<<HTML
            <tr>
                <td><a href="$shortPath.html">$shortPath</a></td>
                <td>$numCovered/$numTotal</td>
            </tr>
            HTML;

            $totalNumCovered += $numCovered;
            $totalNumTotal += $numTotal;
        }

        $overview .= "<tr><td>Total</td><td>$totalNumCovered/$totalNumTotal</td></tr>";
        $overview .= '</table>';
        file_put_contents($this->outDir . '/index.html', $overview);
    }
}

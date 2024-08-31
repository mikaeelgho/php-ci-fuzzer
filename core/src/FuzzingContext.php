<?php


namespace PhpFuzzer;
final class FuzzingContext {
    /** @var int */
    public static $prevBlock = 0;
    /** @var array<int, int> */
    public static $edges = [];

    public static function reset(): void {
        self::$prevBlock = 0;
        self::$edges = [];
    }

    public static function loadFromFile(): array {
        // load from output.log array of sequence
    }
    public static function loadCountFromFile(): array {
        // use loadFromFile()
        // i want this output: ['file1:linex'=> count1, 'file1:liney'=> count2, 'file2:linez'=> count3]
    }

    /**
     * @template T
     * @param int $blockIndex
     * @param T $returnValue
     * @return T
     */
    public static function traceBlock($blockIndex, $returnValue) {
        $key = self::$prevBlock << 28 | $blockIndex;
        self::$edges[$key] = (self::$edges[$key] ?? 0) + 1;
        return $returnValue;
    }
}

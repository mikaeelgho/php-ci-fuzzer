<?php declare(strict_types=1);

require 'RP/Depend.php';

function method1()
{
    return 'method_return';
}

class SampleClass
{
    public static function getId(string $input): string
    {
        if (Depend::validCharacters($input)) {
            return "valid";
        }
        return "id1";
    }

    public static function getIds(string $y): ?string
    {
        $r = method1();
        for ($t = 0; $t < 2; $t++) {
            $r .= self::getId($y);
        }
        return $r;
    }
}

if ($argc > 1) {
    $x = $argv[1];
    echo SampleClass::getIds($x);
} else {
    echo "No input provided.";
}

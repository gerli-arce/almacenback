<?php 
namespace App\gLibraries;

class gJSON
{
    public static function parse(string $text): array
    {
        $array = json_decode($text, true);
        return $array;}
    public static function stringify(mixed $object): string
    {
        $string = json_encode($object, JSON_PRETTY_PRINT);
        return $string;
    }
    public static function parseable(string $text): bool
    {
        try {
            gJSON::parse($text);
            return true;
        } catch (\Throwable$th) {
            return false;
        }
    }
    public static function flatten(
        mixed $object,
        $notation = '.',
        string $prev = ''): array
    {
        $flattened = array();
        foreach ($object as $key => $value) {
            $type = gettype($value);
            if ($type == 'array') {
                $prev_key = $prev ? $prev . $notation . $key : $key;

                $object2 = gJSON::flatten($value, $notation, $prev_key);
                foreach ($object2 as $key2 => $value2) {
                    $flattened[$key2] = $value2;
                }
            } else {
                $prev_key = $prev ? $prev . $notation : '';
                $flattened["$prev_key$key"] = $value;
            }
        }
        return $flattened;
    }
    public static function restore($object, $notation = '.')
    {
        $restored = array();
        foreach ($object as $key => $value) {
            $keys = explode($notation, $key);
            if (count($keys) > 1) {
                $key = array_shift($keys);
                $newkey = implode($notation, $keys);
                $kv = $restored[$key] ?? array();
                $kv[$newkey] = $value;
                $restored[$key] =
                gJSON::restore($kv, $notation);
            } else {
                $restored[$key] = $value;}}
        return $restored;
    }
}

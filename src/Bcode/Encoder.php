<?php
/**
 * Desc: Bencode
 * User: baagee
 * Date: 2019/3/8
 * Time: 下午3:34
 */

namespace DHT\Bcode;
class Encoder
{
    public static function encode($value)
    {
        if (is_array($value)) {
            if (self::isAssoc($value)) {
                ksort($value, SORT_STRING);
                $result = '';
                foreach ($value as $k => $v)
                    $result .= self::encode("$k") . self::encode($v);
                return "d{$result}e";
            } else {
                $result = '';
                foreach ($value as $v)
                    $result .= self::encode($v);
                return "l{$result}e";
            }
        } else if (is_int($value)) {
            return "i{$value}e";
        } else if (is_string($value)) {
            return strlen($value) . ":$value";
        } else {
            $type = gettype($value);
            throw new \Exception("Bencode supports only integers, strings and arrays. $type given.");
        }
    }

    private static function isAssoc(array $array)
    {
        $i = 0;
        foreach ($array as $k => $v)
            if ($k !== $i++)
                return true;
        return false;
    }
}
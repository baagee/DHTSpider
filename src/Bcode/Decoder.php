<?php
/**
 * Desc: 解码
 * User: baagee
 * Date: 2019/3/8
 * Time: 下午3:34
 */

namespace DHT\Bcode;

class Decoder
{
    private $string, $pos = 0;

    private function __construct($string)
    {
        $this->string = $string;
    }

    public static function decode($string)
    {
        $decode = new self($string);
        return $decode->doDecode();
    }

    private function doDecode()
    {
        switch ($this->read()) {
            case 'i':
                $this->seek();
                return $this->readInt('e');
            case 'l':
                $this->seek();
                $result = array();
                while ($this->read() !== 'e')
                    $result[] = $this->doDecode();
                $this->seek();
                return $result;
            case 'd':
                $this->seek();
                $result = array();
                while ($this->read() !== 'e')
                    $result[$this->parseString()] = $this->doDecode();
                $this->seek();
                return $result;
            default:
                return $this->parseString();
        }
    }

    private function read($len = 1)
    {
        $result = (string)substr($this->string, $this->pos, $len);
        $len2   = strlen($result);
        if ($len !== $len2)
            throw new \Exception("$len bytes expected but only $len2 bytes remain");
        return $result;
    }

    private function seek($len = 1)
    {
        $this->pos += $len;
    }

    private function readInt($delimiter)
    {
        $result = $this->remove($this->find($delimiter));
        $this->seek(strlen($delimiter));
        $int = (int)$result;
        if ("$int" !== $result)
            throw new \Exception("Invalid integer: $result");
        return $int;
    }

    private function remove($len)
    {
        $string = $this->read($len);
        $this->seek($len);
        return $string;
    }

    private function find($needle)
    {
        $pos = strpos($this->string, $needle, $this->pos);
        if ($pos === false)
            throw new \Exception("'$needle' not found");
        return $pos - $this->pos;
    }

    private function parseString()
    {
        $len = $this->readInt(':');
        if ($len === false)
            throw new \Exception("Length of string ($len) must not be negative");
        return $this->remove($len);
    }
}
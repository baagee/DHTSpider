<?php
/**
 * Desc:
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午7:17
 */

namespace DHT;

class Tool
{
    /**
     * 把字符串转换为数字
     * @param string $str 要转换的字符串
     * @return string 转换后的字符串
     */
    public static function hash2int($str)
    {
        return hexdec(bin2hex($str));
    }

    /**
     * @param $data
     * @return null|string|string[]
     */
    public static function character($data)
    {
        if (!empty($data)) {
            $fileType = mb_detect_encoding($data, array('UTF-8', 'GBK', 'LATIN1', 'BIG5'));
            if ($fileType != 'UTF-8') {
                $data = mb_convert_encoding($data, 'utf-8', $fileType);
            }
        }
        return $data;
    }

    /**
     * 生成随机字符串
     * @param  integer $length 要生成的长度
     * @return   string 生成的字符串
     */
    public static function randomString($length = 20)
    {
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= chr(mt_rand(0, 255));
        }
        return $str;
    }

    /**
     * 生成一个node id
     * @return   string 生成的node id
     */
    public static function getNodeId()
    {
        return sha1(self::randomString(), true);
    }

    public static function getNeighbor($target, $nid)
    {
        return substr($target, 0, 10) . substr($nid, 10, 10);
    }

    /**
     * 对nodes列表编码
     * @param  mixed $nodes 要编码的列表
     * @return string        编码后的数据
     */
    public static function encodeNodes($nodes)
    {
        // 判断当前nodes列表是否为空
        if (count($nodes) == 0) {
            return $nodes;
        }
        $n = '';
        // 循环对node进行编码
        foreach ($nodes as $node) {
            $n .= pack('a20Nn', $node->nodeId, ip2long($node->ip), $node->port);
        }
        return $n;
    }

    /**
     * 对nodes列表解码
     * @param  string $msg 要解码的数据
     * @return mixed      解码后的数据
     */
    public static function decodeNodes($msg)
    {
        // 先判断数据长度是否正确
        if ((strlen($msg) % 26) != 0) {
            return array();
        }
        $n = array();
        // 每次截取26字节进行解码
        foreach (str_split($msg, 26) as $s) {
            // 将截取到的字节进行字节序解码
            $r   = unpack('a20nid/Nip/np', $s);
            $n[] = new Node(long2ip($r['ip']), $r['p'], $r['nid']);
        }
        return $n;
    }
}
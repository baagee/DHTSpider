<?php
/**
 * Desc:
 * User: baagee
 * Date: 2018/12/28
 * Time: 下午2:41
 */

namespace DHT;

use Rych\Bencode\Decoder;
use Rych\Bencode\Encoder;
use Swoole\Client;

/**
 * Class MetaData
 * @package DHT
 */
class MetaData
{
    /**
     * @var string
     */
    public static $_bt_protocol = 'BitTorrent protocol';
    /**
     * @var int
     */
    public static $BT_MSG_ID = 20;
    /**
     * @var int
     */
    public static $EXT_HANDSHAKE_ID = 0;
    /**
     * @var int
     */
    public static $PIECE_LENGTH = 16384;

    /**
     * @param $client
     * @param $infoHash
     * @return array|bool
     */
    public static function downloadMetadata(Client $client, $infoHash)
    {
        $packet = self::sendHandShake($client, $infoHash);
        if ($packet === false) {
            return false;
        }
        if (self::checkHandshake($packet, $infoHash) === false) {
            return false;
        }
        $packet = self::sendExtHandshake($client);
        if ($packet === false) {
            return false;
        }

        $metadata_size = self::getMetadataSize($packet);
        if ($metadata_size > self::$PIECE_LENGTH * 1000) {
            return false;
        }
        $metadata  = array();
        $piecesNum = ceil($metadata_size / (self::$PIECE_LENGTH));//2 ^ 14

        $ut_metadata = self::getUtMetadata($packet);

        for ($i = 0; $i < $piecesNum; $i++) {
            if (self::requestMetadata($client, $ut_metadata, $i) === false) {
                return false;
            }

            $packet = self::recvAll($client);
            if ($packet === false) {
                return false;
            }

            $ee   = substr($packet, 0, strpos($packet, "ee") + 2);
            $dict = Decoder::decode(substr($ee, strpos($packet, "d")));

            if (isset($dict['msg_type']) && $dict['msg_type'] != 1) {
                return false;
            }

            $_metadata = substr($packet, strpos($packet, "ee") + 2);

            if (strlen($_metadata) > self::$PIECE_LENGTH) {
                return false;
            }

            $metadata[] = $_metadata;
        }
        $metadata = implode('', $metadata);

        $_data     = [];
        $metadata  = Decoder::decode($metadata);
        $_infohash = strtoupper(bin2hex($infoHash));
        if (isset($metadata['name']) && $metadata['name'] != '') {
            $_data['name']        = Tool::character($metadata['name']);
            $_data['infoHash']    = $_infohash;
            $_data['files']       = isset($metadata['files']) ? $metadata['files'] : '';
            $_data['length']      = isset($metadata['length']) ? $metadata['length'] : 0;
            $_data['pieceLength'] = isset($metadata['piece length']) ? $metadata['piece length'] : 0;
            //$_data['length_format'] = Func::sizecount($_data['length']);
            $_data['magnetUrl'] = 'magnet:?xt=urn:btih:' . $_infohash;
            unset($metadata);
        } else {
            return false;
        }
        return $_data;
    }

    /**
     * bep_0009
     * @param $client
     * @param $ut_metadata
     * @param $piece
     * @return bool
     */
    protected static function requestMetadata(Client $client, $ut_metadata, $piece)
    {
        $msg     = chr(self::$BT_MSG_ID) . chr($ut_metadata) . Encoder::encode(array("msg_type" => 0, "piece" => $piece));
        $msg_len = pack("I", strlen($msg));
        if (!BIG_ENDIAN) {
            $msg_len = strrev($msg_len);
        }
        $_msg = $msg_len . $msg;

        $rs = $client->send($_msg);
        if ($rs === false) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param $client
     * @return bool|string
     */
    protected static function recvAll(Client $client)
    {
        $data_length = $client->recv(4, true);
        if ($data_length === false) {
            return false;
        }

        if (strlen($data_length) != 4) {
            return false;
        }

        $data_length = intval(unpack('N', $data_length)[1]);

        if ($data_length == 0) {
            return false;
        }

        if ($data_length > self::$PIECE_LENGTH * 1000) {
            return false;
        }

        $data = '';
        while (true) {
            if ($data_length > 8192) {
                if (($_data = $client->recv(8192, true)) == false) {
                    //echo $data_length.PHP_EOL;
                    return false;
                } else {
                    $data        .= $_data;
                    $data_length = $data_length - 8192;
                }
            } else {
                if (($_data = $client->recv($data_length, true)) == false) {
                    return false;
                } else {
                    $data .= $_data;
                    break;
                }
            }
        }
        return $data;
    }

    /**
     * @param Client $client
     * @param        $infohash
     * @return bool|string
     */
    protected static function sendHandShake(Client $client, $infohash)
    {
        $bt_protocol = self::$_bt_protocol;
        $bt_header   = chr(strlen($bt_protocol)) . $bt_protocol;
        $ext_bytes   = "\x00\x00\x00\x00\x00\x10\x00\x00";
        $peer_id     = Tool::getNodeId();
        $packet      = $bt_header . $ext_bytes . $infohash . $peer_id;
        $rs          = $client->send($packet);
        if ($rs === false) {
            return false;
        }
        $data = $client->recv(4096, 0);
        if ($data === false) {
            return false;
        }
        return $data;
    }

    /**
     * @param $packet
     * @param $self_infohash
     * @return bool
     */
    protected static function checkHandshake($packet, $self_infohash)
    {
        $bt_header_len = ord(substr($packet, 0, 1));
        $packet        = substr($packet, 1);
        if ($bt_header_len != strlen(self::$_bt_protocol)) {
            return false;
        }
        $bt_header = substr($packet, 0, $bt_header_len);
        $packet    = substr($packet, $bt_header_len);
        if ($bt_header != self::$_bt_protocol) {
            return false;
        }
        $packet   = substr($packet, 8);
        $infohash = substr($packet, 0, 20);
        if ($infohash != $self_infohash) {
            return false;
        }
        return true;
    }

    /**
     * @param $client
     * @return bool
     */
    protected static function sendExtHandshake(Client $client)
    {
        $msg     = chr(self::$BT_MSG_ID) . chr(self::$EXT_HANDSHAKE_ID) . Encoder::encode(array("m" => array("ut_metadata" => 1)));
        $msg_len = pack("I", strlen($msg));
        if (!BIG_ENDIAN) {
            $msg_len = strrev($msg_len);
        }
        $msg = $msg_len . $msg;
        $rs  = $client->send($msg);
        if ($rs === false) {
            return false;
        }
        $data = $client->recv(4096, 0);
        if ($data === false) {
            return false;
        }
        return $data;
    }

    /**
     * @param $data
     * @return int
     */
    protected static function getUtMetadata($data)
    {
        $ut_metadata = '_metadata';
        $index       = strpos($data, $ut_metadata) + strlen($ut_metadata) + 1;
        return intval($data[$index]);
    }


    /**
     * @param $data
     * @return int
     */
    protected static function getMetadataSize($data)
    {
        $metadata_size = 'metadata_size';
        $start         = strpos($data, $metadata_size) + strlen($metadata_size) + 1;
        $data          = substr($data, $start);
        $e_index       = strpos($data, "e");
        return intval(substr($data, 0, $e_index));
    }
}
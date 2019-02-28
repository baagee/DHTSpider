<?php
/**
 * Desc: Node节点
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午7:22
 */

namespace DHT;

/**
 * Class Node
 * @package DHT
 */
class Node
{
    /**
     * @var string
     */
    protected $ip = '';
    /**
     * @var int
     */
    protected $port = 0;
    /**
     * @var string
     */
    protected $nodeId = '';

    /**
     * Node constructor.
     * @param        $ip
     * @param        $port
     * @param string $nodeId
     */
    public function __construct($ip, $port, $nodeId = '')
    {
        $this->ip   = $ip;
        $this->port = $port;
        if (empty($nodeId)) {
            $nodeId = Tool::getNodeId();
        }
        $this->nodeId = $nodeId;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->$name;
    }

    /**
     * @return string
     */
    public function getIp(): string
    {
        return $this->ip;
    }

    /**
     * @return string
     */
    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    /**
     * @return string
     */
    public function getPort(): string
    {
        return $this->port;
    }
}

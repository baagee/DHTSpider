<?php
/**
 * Desc:
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午7:22
 */

namespace DhtSpider\Dht;

use DhtSpider\Tool\Tool;

class Node
{
    protected $ip     = '';
    protected $port   = 0;
    protected $nodeId = '';

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

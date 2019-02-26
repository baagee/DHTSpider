<?php
/**
 * Desc: Dht客户端
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午5:31
 */

namespace DhtSpider\Dht;
use DhtSpider\Tool\Tool;

trait DhtClient
{
    /**
     * 找到节点
     * @param string $ip
     * @param int    $port
     * @param null   $id
     */
    public function findNode($ip, $port, $id = null)
    {
        if (is_null($id)) {
            $target = Tool::getNodeId();
        } else {
            // 否则伪造一个相邻id
            $target = Tools::getNeighbor($id, DHTSpider::getNodeId());
        }
        //        echo '查找朋友 ' . $ip . ' 是否在线' . PHP_EOL;
        // 定义发送数据 认识新朋友的。
        $msg = array(
            't' => Tool::randomString(2),
            'y' => 'q',
            'q' => 'find_node',
            'a' => array(
                'id'     => DHTSpider::getNodeId(),
                'target' => $target
            )
        );
        // 发送请求数据到对端
        $this->sendMessage($msg, $ip, $port);
    }
}
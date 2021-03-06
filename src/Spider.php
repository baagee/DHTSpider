<?php
/**
 * Desc: DHT爬虫
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午5:31
 */

namespace DHT;

use DHT\Bcode\Decoder;
use DHT\Bcode\Encoder;
use SandFox\Bencode\Bencode;
use Swoole\Process;

/**
 * Class Spider
 * @package DHT
 */
class Spider
{
    /**
     * @var Node 爬虫自身的node
     */
    protected $node = null;

    /**
     * @var array 初始的node
     */
    protected $startNodes = [
        ['ip' => 'router.bittorrent.com', 'port' => 6881],
        ['ip' => 'dht.transmissionbt.com', 'port' => 6881],
        ['ip' => 'router.utorrent.com', 'port' => 6881]
    ];

    /**
     * @var UdpServer
     */
    protected $udpServer = null;

    /**
     * @var array 保存获取到的node节点
     */
    protected static $nodes = [];

    /**
     * Spider constructor.
     * @param UdpServer $server
     * @param array     $startNodes
     */
    public function __construct(UdpServer $server, array $startNodes = [])
    {
        $this->udpServer  = $server;
        $this->node       = new Node($this->udpServer->ip, $this->udpServer->port, Tool::getNodeId());
        $this->startNodes = array_merge($this->startNodes, $startNodes);
    }

    /**
     * 开始爬取
     */
    public function crawl()
    {
        Log::info(__METHOD__ . ' 开始运行');
        $this->udpServer->on('WorkerStart', function ($server, $work_id) {
            $this->onWorkStart($server, $work_id);
        });
        $this->udpServer->on('Packet', function ($server, $data, $client_info) {
            $this->onPacket($server, $data, $client_info);
        });
        $this->udpServer->start();
    }

    /**
     * worker进程开始
     * @param $server
     * @param $work_id
     */
    public function onWorkStart($server, $work_id)
    {
        Log::info(__METHOD__ . '  work_id=' . $work_id);
        swoole_timer_tick(3000, function ($timer_id) {
            $num = count(self::$nodes);
            Log::info('当前node数量：' . $num);
            if ($num == 0) {
                $this->joinDhtNet();
            }
            $this->autoFindNode();
        });
    }

    /**
     * 接收到数据时
     * @param $server
     * @param $data
     * @param $client_info
     * @return bool
     */
    public function onPacket($server, $data, $client_info)
    {
        // Log::info(__METHOD__ . ' client_info=' . $client_info['address'] . ':' . $client_info['port']);
        if (strlen($data) == 0) {
            return false;
        }
        try {
            $msg = Decoder::decode($data);
            if (!isset($msg['y'])) {
                return false;
            }
            if ($msg['y'] == 'r') {
                // 如果是回复, 且包含nodes信息 添加到路由表
                if (array_key_exists('nodes', $msg['r'])) {
                    $this->batchAddNode($msg);
                }
            } elseif ($msg['y'] == 'q') {
                // 如果是请求, 则执行请求判断
                $this->response($msg, $client_info['address'], $client_info['port']);
            }
        } catch (\Throwable $e) {
            Log::error('Error:' . 'File:' . $e->getFile() . ':' . $e->getLine() . 'msg=' . $e->getMessage() . '  code=' . $e->getCode());
        }
    }

    /**
     * 批量添加node
     * @param $msg
     */
    protected function batchAddNode($msg)
    {
        Log::info(__METHOD__ . ' 批量添加node');
        // 先检查接收到的信息是否正确
        if (!isset($msg['r']['nodes']) || !isset($msg['r']['nodes'][1]))
            return;
        // 对nodes数据进行解码
        $nodes = Tool::decodeNodes($msg['r']['nodes']);
        // 对nodes循环处理
        foreach ($nodes as $node) {
            // 将node加入到路由表中
            self::addNode($node);
        }
    }

    /**
     * 响应
     * @param $msg
     * @param $ip
     * @param $port
     */
    protected function response($msg, $ip, $port)
    {
        switch ($msg['q']) {
            case 'ping':
                //确认你是否在线
                $this->onPing($msg, $ip, $port);
                break;
            case 'find_node':
                //向服务器发出寻找节点的请求
                $this->onFindNode($msg, $ip, $port);
                break;
            case 'get_peers':
                // 处理get_peers请求
                $this->onGetPeers($msg, $ip, $port);
                break;
            case 'announce_peer':
                // 处理announce_peer请求
                $this->onAnnouncePeer($msg, $ip, $port);
                break;
            default:
                break;
        }
    }

    /**
     * 发送信息
     * @param $msg
     * @param $ip
     * @param $port
     * @return bool
     */
    protected function sendMessage($msg, $ip, $port)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            Log::warning(__METHOD__ . ' ip 不合法:' . $ip);
            return false;
        }
        $data = Encoder::encode($msg);
        $this->udpServer->sendTo($ip, $port, $data);
        return true;
    }

    /**
     * 加入网络
     */
    protected function joinDhtNet()
    {
        Log::info(__METHOD__ . ' 自动重新加入网络');
        if (count(self::$nodes) == 0) {
            foreach ($this->startNodes as $node) {
                //将自身伪造的ID 加入预定义的DHT网络
                $this->findNode(gethostbyname($node['ip']), $node['port']);
            }
        }
    }

    /**
     *  添加node
     * @param Node $node
     */
    protected function addNode(Node $node)
    {
        $hexKey = hexdec($node->getNodeId());
        if (array_key_exists($hexKey, self::$nodes)) {
            unset(self::$nodes[$hexKey]);
        }
        self::$nodes[$hexKey] = $node;
    }

    /**
     * 自动查找node节点
     */
    protected function autoFindNode()
    {
        Log::info(__METHOD__ . ' 自动查找节点');
        while (count(self::$nodes) > 0) {
            // 从路由表中删除第一个node并返回被删除的node
            $node = array_shift(self::$nodes);
            // 发送查找find_node到node中
            $this->findNode($node->getIp(), $node->getPort(), $node->getNodeId());
        }
    }

    /**
     * 获取一定数量的node
     * @param $count
     * @return array
     */
    protected function getNodes($count)
    {
        if (count(self::$nodes) <= $count) {
            return self::$nodes;
        }
        return array_slice(self::$nodes, 0, $count);
    }

    /**
     * 查找node
     * @param      $ip
     * @param      $port
     * @param null $node_id
     */
    protected function findNode($ip, $port, $node_id = null)
    {
        if (is_null($node_id)) {
            $target = Tool::getNodeId();
        } else {
            // 否则伪造一个相邻id
            $target = Tool::getNeighbor($node_id, $this->node->getNodeId());
        }
        Log::info(__METHOD__ . '查找朋友');
        // 定义发送数据 认识新朋友的。
        $msg = [
            't' => Tool::randomString(2),
            'y' => 'q',
            'q' => 'find_node',
            'a' => [
                'id'     => $this->node->getNodeId(),
                'target' => $target
            ]
        ];
        // 发送请求数据到对端
        $this->sendMessage($msg, $ip, $port);
    }

    /**
     * @param $msg
     * @param $ip
     * @param $port
     */
    protected function onPing($msg, $ip, $port)
    {
        // Log::info(__METHOD__ . ' 朋友【' . $ip . '】正在确认你是否在线');
        // 获取对端node id
        $id = $msg['a']['id'];
        // 生成回复数据
        $msg = [
            't' => $msg['t'],
            'y' => 'r',
            'r' => [
                'id' => $this->node->getNodeId()
            ]
        ];
        // 将node加入路由表
        self::addNode(new Node($ip, $port, $id));
        // 发送回复数据
        $this->sendMessage($msg, $ip, $port);
    }

    /**
     * @param $msg
     * @param $ip
     * @param $port
     */
    protected function onFindNode($msg, $ip, $port)
    {
        // Log::info(__METHOD__ . ' 朋友【' . $ip . '】向你发出寻找节点的请求');
        // 获取对端node id
        $id = $msg['a']['id'];
        // 生成回复数据
        $msg = array(
            't' => $msg['t'],
            'y' => 'r',
            'r' => array(
                'id'    => Tool::getNeighbor($id, $this->node->getNodeId()),
                'nodes' => Tool::encodeNodes($this->getNodes(16))
            )
        );
        // 将node加入路由表
        self::addNode(new Node($ip, $port, $id));
        // 发送回复数据
        $this->sendMessage($msg, $ip, $port);
    }

    /**
     * @param $msg
     * @param $ip
     * @param $port
     */
    protected function onGetPeers($msg, $ip, $port)
    {
        Log::info(__METHOD__ . '朋友【' . $ip . '】向你发出查找资源的请求');
        // 获取info_hash信息
        $infoHash = $msg['a']['info_hash'];
        // 获取node id
        $node_id = $msg['a']['id'];
        // 生成回复数据
        $msg = [
            't' => $msg['t'],
            'y' => 'r',
            'r' => [
                'id'    => Tool::getNeighbor($node_id, $this->node->getNodeId()),
                'nodes' => Tool::encodeNodes($this->getNodes(16)),
                'token' => substr($infoHash, 0, 3)
            ]
        ];

        $node = new Node($ip, $port, $node_id);
        // 将node加入路由表
        self::addNode($node);
        // 向对端发送回复数据
        $this->sendMessage($msg, $ip, $port);
    }

    /**
     * @param $msg
     * @param $ip
     * @param $port
     * @return bool
     */
    protected function onAnnouncePeer($msg, $ip, $port)
    {
        Log::info(__METHOD__ . '朋友【' . $ip . '】找到资源了, 通知你一声');
        $infoHash = $msg['a']['info_hash'];
        $token    = $msg['a']['token'];
        $tid      = $msg['t'];

        // 验证token是否正确
        if (substr($infoHash, 0, 3) != $token) {
            // Log::warning(__METHOD__ . 'Token 不正确');
            return false;
        }

        if (!(isset($msg['a']['implied_port']) && $msg['a']['implied_port'] != 0)) {
            $port = $msg['a']['port'];
        }
        if ($port >= 65536 || $port <= 0) {
            Log::warngin(__METHOD__ . ' port不正确');
            return false;
        }
        if ($tid == '') {
            Log::warngin(__METHOD__ . ' tid为空');
            return false;
        }
        // 生成回复数据
        $msg = [
            't' => $tid,
            'y' => 'r',
            'r' => [
                'id' => $this->node->getNodeId()
            ]
        ];
        $this->sendMessage($msg, $ip, $port);
        Log::info(__METHOD__ . ' infoHash=' . $infoHash);
        $this->getMetaData($ip, $port, $infoHash);
    }

    /**
     * 获取磁力链接信息
     * @param $ip
     * @param $port
     * @param $infoHash
     */
    protected function getMetaData($ip, $port, $infoHash)
    {
        $process = new Process(function (Process $worker) use ($ip, $port, $infoHash) {
            try {
                $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
                if (!@$client->connect($ip, $port, 5)) {
                    Log::warngin('tpc连接' . $ip . ':' . $port . '失败');
                } else {
                    Log::info('tpc连接' . $ip . ':' . $port . '成功');
                    $rs = MetaData::downloadMetadata($client, $infoHash);
                    $client->close();
                    // todo 记录结果
                    if ($rs !== false) {
                        Log::metadata(json_encode($rs, JSON_UNESCAPED_UNICODE));
                    } else {
                        Log::error('获取metaData信息失败');
                    }
                }
                $worker->exit(0);
            } catch (\Throwable $e) {
                Log::error('Error: File:' . $e->getFile() . ':' . $e->getLine() . ' code=' . $e->getCode() . ' msg=' . $e->getMessage());
            }
        }, false);
        $process->start();
    }
}
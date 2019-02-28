<?php
/**
 * Desc: DHT爬虫
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午5:31
 */

namespace DHT;

use Rych\Bencode\Bencode;
use Rych\Bencode\Decoder;
use Swoole\Process;

class Spider
{
    protected $node = null;

    protected $startNodes = [
        ['ip' => 'router.bittorrent.com', 'port' => 6881],
        ['ip' => 'dht.transmissionbt.com', 'port' => 6881],
        ['ip' => 'router.utorrent.com', 'port' => 6881]
    ];

    protected $udpServer = null;

    protected static $nodes = [];

    public function __construct(UdpServer $server, array $startNodes = [])
    {
        $this->udpServer  = $server;
        $this->node       = new Node($this->udpServer->ip, $this->udpServer->port, Tool::getNodeId());
        $this->startNodes = array_merge($this->startNodes, $startNodes);
    }

    public function crawl()
    {
        Log::info(__METHOD__ . ' 开始运行');
        $this->udpServer->on('WorkerStart', function ($server, $work_id) {
            $this->onWorkStart($server, $work_id);
        });
        $this->udpServer->on('Packet', function ($server, $data, $client_info) {
            $this->onPacket($server, $data, $client_info);
        });
        // $this->udpServer->on('task', function ($server, $taskId, $reactorId, $data) {
        //     echo '222' . PHP_EOL;
        // });
        $this->udpServer->on('finish', function ($server, $task_id, $data) {
            echo "AsyncTask[$task_id] finished: $data" . PHP_EOL;
        });
        $this->udpServer->start();
    }

    public function onWorkStart($server, $work_id)
    {
        Log::info(__METHOD__ . '  work_id=' . $work_id);
        swoole_timer_tick(3000, function ($timer_id) {
            if (count(self::$nodes) == 0) {
                $this->joinDhtNet();
            }
            $this->autoFindNode();
        });
    }

    public function onPacket($server, $data, $client_info)
    {
        Log::info(__METHOD__ . ' client_info=' . $client_info['address'] . ':' . $client_info['port']);
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
                    // $this->batchAddNode($msg);
                }
            } elseif ($msg['y'] == 'q') {
                // 如果是请求, 则执行请求判断
                $this->response($msg, $client_info['address'], $client_info['port']);
            }
        } catch (\Throwable $e) {
            Log::error('Error:' . 'File:' . $e->getFile() . ':' . $e->getLine() . 'msg=' . $e->getMessage() . '  code=' . $e->getCode());
        }
    }

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

    protected function sendMessage($msg, $ip, $port)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            Log::warning(__METHOD__ . ' ip 不合法:' . $ip);
            return false;
        }
        $data = Bencode::encode($msg);
        $this->udpServer->sendTo($ip, $port, $data);
        return true;
    }

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

    protected function addNode(Node $node)
    {
        $hexKey = hexdec($node->getNodeId());
        if (array_key_exists($hexKey, self::$nodes)) {
            unset(self::$nodes[$hexKey]);
        }
        self::$nodes[$hexKey] = $node;
    }

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

    protected function getNodes($count)
    {
        if (count(self::$nodes) <= $count) {
            return self::$nodes;
        }
        return array_slice(self::$nodes, 0, $count);
    }

    protected function findNode($ip, $port, $node_id = null)
    {
        if (is_null($node_id)) {
            $target = Tool::getNodeId();
        } else {
            // 否则伪造一个相邻id
            $target = Tool::getNeighbor($node_id, $this->node->getNodeId());
        }
        Log::info(__METHOD__ . '查找朋友 ' . $ip . ' 是否在线');
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

    protected function onPing($msg, $ip, $port)
    {
        Log::info(__METHOD__ . ' 朋友【' . $ip . '】正在确认你是否在线');
        // 获取对端node id
        $id = $msg['a']['id'];
        // 生成回复数据
        $msg = [
            't' => $msg['t'],
            'y' => 'r',
            'r' => [
                'id' => Tool::getNeighbor($id, $this->node->getNodeId())
            ]
        ];
        // 将node加入路由表
        self::addNode(new Node($ip, $port, $id));
        // 发送回复数据
        $this->sendMessage($msg, $ip, $port);
    }

    protected function onFindNode($msg, $ip, $port)
    {
        Log::info(__METHOD__ . ' 朋友【' . $ip . '】向你发出寻找节点的请求');
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

    protected function onAnnouncePeer($msg, $ip, $port)
    {
        Log::info(__METHOD__ . '朋友【' . $ip . '】找到资源了, 通知你一声');
        $infoHash = $msg['a']['info_hash'];
        $token    = $msg['a']['token'];
        $tid      = $msg['t'];

        // 验证token是否正确
        if (substr($infoHash, 0, 3) != $token) {
            Log::warning(__METHOD__ . 'Token 不正确');
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
                    Log::metadata(json_encode($rs, JSON_UNESCAPED_UNICODE));
                }
                $worker->exit(0);
            } catch (\Throwable $e) {
                Log::error('Error: File:' . $e->getFile() . ':' . $e->getLine() . ' code=' . $e->getCode() . ' msg=' . $e->getMessage());
            }
        }, false);
        $process->start();
    }
}
<?php
/**
 * Desc: 爬虫
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午5:31
 */

namespace DhtSpider;

use DhtSpider\Console\Log;
use DhtSpider\Dht\AbstractDht;
use DhtSpider\Dht\DhtClient;
use DhtSpider\Dht\DhtServer;
use Rych\Bencode\Bencode;
use Swoole\Server;

class Spider extends AbstractDht
{
    use DhtClient, DhtServer;

    protected $config = [
        'ip'              => '0.0.0.0',
        'port'            => 7890,
        'worker_num'      => 1,
        'daemonize'       => false,
        'task_worker_num' => 200
    ];

    protected const BOOTSTRAP_NODES = [
        ['router.bittorrent.com', 6881],
        ['dht.transmissionbt.com', 6881],
        ['router.utorrent.com', 6881]
    ];

    protected $udpServer = null;

    public function __construct(array $config = [])
    {
        date_default_timezone_set('PRC');
        $this->config    = array_merge($this->config, $config);
        $this->udpServer = new Server($this->config['ip'], $this->config['port'], SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
        $this->udpServer->set([
            'worker_num'               => $this->config['worker_num'],//设置启动的worker进程数
            'daemonize'                => $this->config['daemonize'],//是否后台守护进程
            'max_request'              => 0, //todo 防止 PHP 内存溢出, 一个工作进程处理 X 次任务后自动重启 (注: 0,不自动重启)
            'dispatch_mode'            => 2,//保证同一个连接发来的数据只会被同一个worker处理
            //         todo    'log_file' => BASEPATH . '/logs/error.log',
            'max_conn'                 => 65535,//最大连接数
            'heartbeat_check_interval' => 5, //启用心跳检测，此选项表示每隔多久轮循一次，单位为秒
            'heartbeat_idle_time'      => 10, //与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间
            'task_worker_num'          => $this->config['task_worker_num'],
            'task_max_request'         => 0
        ]);
    }

    public function crawl()
    {
        Log::info(__METHOD__ . ' 开始运行');
        $this->udpServer->on('WorkerStart', function ($server, $work_id) {
            $this->onWorkStart($server, $work_id);
        });
        $this->udpServer->on('Packet', function ($a, $b, $c) {
            echo '11' . PHP_EOL;
        });
        $this->udpServer->on('task', function (Server $server, $taskId, $reactorId, $data) {
            echo '222' . PHP_EOL;
        });
        $this->udpServer->on('finish', function (Server $server, $task_id, $data) {
            echo "AsyncTask[$task_id] finished: {$data}" . PHP_EOL;
        });
        $this->udpServer->start();
    }

    protected function onWorkStart($server, $workId)
    {
        // swoole_timer_tick(3000, function ($timer_id) {
        //     if (count(self::$nodes) == 0) {
        //         // $this->joinDhtNet();
        //     }
        //     // $this->autoFindNode();
        // });
    }

    protected function sendMessage($msg, $ip, $port)
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        $data = Bencode::encode($msg);
        $this->udpServer->sendto($ip, $port, $data);
        return true;
    }
}
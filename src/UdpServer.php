<?php
/**
 * Desc: UDP服务器
 * User: baagee
 * Date: 2019/2/28
 * Time: 下午3:11
 */

namespace DHT;

use Swoole\Server;

/**
 * Class UdpServer
 * @package DHT
 */
class UdpServer
{
    /**
     * @var array
     */
    protected $config = [
        'ip'         => '0.0.0.0',
        'port'       => 7890,
        'worker_num' => 3,
        'daemonize'  => false,
    ];

    /**
     * @var null|Server
     */
    protected $udpServer = null;

    /**
     * UdpServer constructor.
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->config    = array_merge($this->config, $config);
        $this->udpServer = new Server($this->config['ip'], $this->config['port'], SWOOLE_PROCESS, SWOOLE_SOCK_UDP);
        $this->udpServer->set([
            'worker_num'               => $this->config['worker_num'],//设置启动的worker进程数
            'daemonize'                => $this->config['daemonize'],//是否后台守护进程
            'max_request'              => 10, //todo 防止 PHP 内存溢出, 一个工作进程处理 X 次任务后自动重启 (注: 0,不自动重启)
            'dispatch_mode'            => 2,//保证同一个连接发来的数据只会被同一个worker处理
            'max_conn'                 => 65535,//最大连接数
            'heartbeat_check_interval' => 5, //启用心跳检测，此选项表示每隔多久轮循一次，单位为秒
            'heartbeat_idle_time'      => 10, //与heartbeat_check_interval配合使用。表示连接最大允许空闲的时间
            // 'task_worker_num'          => $this->config['task_worker_num'],
            // 'task_max_request'         => 0
        ]);
    }

    /**
     * @param $name
     * @return mixed|null
     */
    public function __get($name)
    {
        if (isset($this->config[$name])) {
            return $this->config[$name];
        } else {
            return null;
        }
    }

    /**
     * @param $event
     * @param $callable
     */
    public function on($event, $callable)
    {
        $this->udpServer->on($event, $callable);
    }

    /**
     *
     */
    public function start()
    {
        $this->udpServer->start();
    }

    /**
     * @param string $ip
     * @param int    $port
     * @param        $data
     */
    public function sendTo(string $ip, int $port, $data)
    {
        $this->udpServer->sendto($ip, $port, $data);
    }
}
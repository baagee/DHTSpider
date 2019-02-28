<?php
/**
 * Desc: Log类
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午6:32
 */

namespace DHT;

use Swoole\Process;

/**
 * Class Log
 * @package Dht\Console
 */
class Log
{
    /**
     * 允许的Log级别
     */
    private const ALLOW_LEVEL = [
        'info', 'notice', 'warning', 'error', 'metadata'
    ];

    /**
     * @param string $level
     * @param string $msg
     */
    public static function __callStatic(string $level, $msg)
    {
        $msg = date('Y-m-d H:i:s') . '  ' . $msg[0] . PHP_EOL;
        if (in_array($level, self::ALLOW_LEVEL)) {
            // todo 添加file line信息
            // todo 彩色输出到控制台
            echo $msg;
            // 记录
            $process = new Process(function (Process $worker) use ($msg, $level) {
                $log_file = implode(DIRECTORY_SEPARATOR, [getcwd(), 'log', date('Y_m_d'), date('H') . '_' . strtoupper($level) . '.log']);
                $log_path = dirname($log_file);
                if (!is_dir($log_path)) {
                    exec('mkdir -p ' . $log_path);
                }
                file_put_contents($log_file, $msg, FILE_APPEND | LOCK_EX);
                $worker->exit(0);
            }, false);
            $process->start();
            // self::record($level, $msg);
        }
    }

    /**
     * @param string $level
     * @param string $msg
     */
    private static function record(string $level, string $msg)
    {
        $log_file = implode(DIRECTORY_SEPARATOR, [getcwd(), 'log', date('Y_m_d'), date('H') . '_' . strtoupper($level) . '.log']);
        $log_path = dirname($log_file);
        if (!is_dir($log_path)) {
            exec('mkdir -p ' . $log_path);
        }
        file_put_contents($log_file, $msg, FILE_APPEND | LOCK_EX);
    }
}
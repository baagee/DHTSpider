<?php
/**
 * Desc: Log
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午6:32
 */

namespace DhtSpider\Console;
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
        'info', 'notice', 'warning', 'error'
    ];

    /**
     * @param string $level
     * @param string $msg
     */
    public static function __callStatic(string $level, $msg)
    {
        $msg = $msg[0] . PHP_EOL;
        if (in_array($level, self::ALLOW_LEVEL)) {
            // todo 添加file line信息
            // todo 彩色输出到控制台
            echo $msg;
            // 记录
            self::record($level, $msg);
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
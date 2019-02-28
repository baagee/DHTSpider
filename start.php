<?php
/**
 * Desc: 开始运行
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午5:34
 */
date_default_timezone_set('PRC');

define('BIG_ENDIAN', pack('L', 1) === pack('N', 1));

include_once __DIR__ . '/vendor/autoload.php';

$spider = new \DHT\Spider(new \DHT\UdpServer());

$spider->crawl();
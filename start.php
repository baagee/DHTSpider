<?php
/**
 * Desc: 开始运行
 * User: baagee
 * Date: 2019/2/26
 * Time: 下午5:34
 */
include_once __DIR__ . '/vendor/autoload.php';

$spider = new \DhtSpider\Spider();

$spider->crawl();
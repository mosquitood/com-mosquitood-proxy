<?php 
require_once './vendor/autoload.php';
use Mosquitood\Proxy\Tcp;
$proxy = new Tcp();
$proxy->setHost("0.0.0.0")
    ->setPort(9494)
    ->setOptions([
        'worker_num' => 8, 
        'buffer_output_size' => 32*1024*1024,
        'daemonize'  => 0,
        'log_file'   => "/tmp/tcpproxy.txt"
    ])
    ->setDBConf([
        "host" => 'localhost',
        'port' => 3306,
        'user' => 'root',
        'password' => 'iZ25mrdfd69Z',
        'database' => 'admin',
        'charset'  => 'utf8',
    ]);
$proxy->start();

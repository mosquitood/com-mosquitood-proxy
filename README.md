# TCP 代理服务器
基于swoole开发的Tcp代理服务器

- 安装

  ```
  #php7+swoole2.1.3
  composer require mosquitood/swoole
  ```

- 向mysql数据库中导入数据：./example/mysql.sql。

- 使用

  ```
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
          'password' => 'password',
          'database' => 'name',
          'charset'  => 'utf8',
      ]);
  $proxy->start();
  ```

  




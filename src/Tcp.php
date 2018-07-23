<?php 
namespace Mosquitood\Proxy;
use swoole_server;
use swoole_client;
/**
 * TCP代理服务器
 * 
 * @package Proxy 
 * @author  mosquitood <mosquitood@gmail.com>
 */

class Tcp 
{

    /**
     * 代理服务器IP 
     * 
     * @var string $host 
     */

    private $host = '0.0.0.0';

    /**
     * 代理服务器端口 
     *
     * @var string $port 
     */ 

    private $port = 9292;

    /**
     * 数据库连接配置 
     *
     * @var array $dbconf
     */

    private $dbconf    = [];

    /**
     * 客户端连接
     *
     * @var array $frontends
     */

    private $frontends = []; 

    /**
     * 后端代理连接 
     *
     * @var array $backends 
     */ 

    private $backends = [];


    /**
     * 代理服务器(swoole_server)实例 
     *
     * @var object $server 
     */ 

    private $server = null;

    /**
     * 代理服务器运行模式 
     *
     * @var int $mode 
     */

    private $mode  = SWOOLE_PROCESS;

    /**
     * 代理服务器参数
     *
     * @var array $options
     */ 

    private $options = ['buffer_output_size' => 32 * 1024 *1024, 'worker_num' => 8];

    /**
     * 后端代理服务器缓存
     *
     * @var array $proxy
     */

    private $proxy = [];


    /**
     * 设置代理服务器IP
     *
     * @access public 
     * @param  string $host 
     * @return $this 
     **/

    public function setHost(string $host)
    {
        $this->host = trim($host); 
        return $this;
    }


    /**
     * 设置代理服务器端口 
     *
     * @access public 
     * @param  int $port 
     * @return $this 
     **/ 

    public function setPort(int $port)
    {
        $this->port = intval($port);
        return $this;
    }

    /**
     * 设置数据库连接配置 
     *
     * @access public 
     * @param  array $conf 
     * @return $this 
     **/ 

    public function setDBConf(array $conf)
    {
        $this->dbconf = $conf;
        return $this; 
    } 


    /**
     * 设置代理服务器运行模式
     * 
     * @access public 
     * @param  int $mode 
     * @return $this
     **/ 

    public function setMode(int $mode)
    {
        $this->mode = $mode;
        return $this; 
    }

    /**
     * 设置代理服务器运行参数
     *
     * @access public 
     * @param  array $options 
     * @return $this
     **/ 

    public function setOptions(array $options)
    {
        $this->options = $options;
        return $this;
    }

    /**
     * 取得代理服务器host 
     * 
     * @access public 
     * @param  void 
     * @return string 
     **/ 

    public function getHost()
    {
        return $this->host;
    }

    /**
     * 取得代理服务器端口
     *
     * @access public 
     * @param  void 
     * @return int 
     **/ 

    public function getPort()
    {
        return $this->port;
    }

    /**
     * 取得数据库连接配置
     *
     * @access public 
     * @param  void 
     * @return array 
     **/ 

    public function getDBConf()
    {
        return $this->dbconf;
    }


    /**
     * 取得代理服务器运行模式
     *
     * @access public 
     * @param  void 
     * @return int 
     **/ 

    public function getMode()
    {
        return $this->mode;
    }


    /**
     * 取得代理服务器运行参数
     *
     * @access public 
     * @param  void 
     * @return array 
     **/ 

    public function getOptions()
    {
        return $this->options;
    }


    /**
     * 启动代理服务器
     *
     * @access public 
     * @param  void 
     * @return void 
     **/ 

    public function start()
    {
        $server = new swoole_server($this->host, $this->port, $this->mode);
        $server->set($this->options);

        //设置回调函数
        $server->on('WorkerStart', [$this, 'onStart']);
        $server->on('Receive',     [$this, 'onReceive']);
        $server->on('Close',       [$this, 'onClose']);
        $server->on('WorkerStop',  [$this, 'onShutdown']);
        $server->start();
    }

    /**
     * swoole_server 启动
     * 回调函数
     *
     * @access public 
     * @param  swoole_server $server 
     * @return void 
     **/ 

    public function onStart(swoole_server $server)
    {
        $this->server = $server;
        $this->record("swoole server started! masterPId: {$server->master_pid}, managerPid: {$server->manager_pid}");
    }

    /**
     * swoole_server 关闭
     * 回调函数
     *
     * @access public 
     * @param  swoole_server $server 
     * @return void 
     **/ 

    public function onShutdown(swoole_server $server)
    {
        $this->record("swoole server shutdown! masterPId: {$server->master_pid}, managerPid: {$server->manager_pid}");
    }



    /**
     * 客户端连接关闭
     * 回调函数
     *
     * @access public 
     * @param  swoole_server $server 
     * @param  int $fd 客户端连接文件描述符 
     * @param  int $reactorId 线程ID 
     * @return void 
     **/ 

    public function onClose(swoole_server $server, int $fd, int $reactorId)
    {
        $this->record("client start close ! swoole_server masterPId: {$server->master_pid}, managerPid: {$server->manager_pid}, client id: {$fd}");

        //清理掉后端连接
        if (isset($this->frontends[$fd])){
            $backendSocket = $this->frontends[$fd];
            $backendSocket->closing = true;
            if($backendSocket->isConnected()){
                $backendSocket->close();
            }
            unset($this->backends[$backendSocket->sock]);
            unset($this->frontends[$fd]);
            unset($backendSocket);
        }
        $this->record("client end close ! swoole_server masterPId: {$server->master_pid}, managerPid: {$server->manager_pid}, client id: {$fd}");
    }


    /**
     * 接收请求回调函数
     *
     * @access public 
     * @param  
     * @return void
     **/ 

    public function onReceive(swoole_server $server, int $fd, int $reactorId, string $data)
    {
        $array = explode("\r\n", $data, 10);
        $login = [];
        //解析字节流，拿到http auth 用户名和密码
        if($array && is_array($array)){
            foreach($array as $key => $value){
                if(strpos($value, 'Authorization') !== false){
                    $auth = explode(" ", $value); 
                    if(isset($auth[2])){
                        $login = explode(':', base64_decode($auth[2]));
                    }
                } 
            } 
        }

        if(!isset($this->frontends[$fd])){
            if($login){
                list($username, $password) = $login;  
                $key = md5($username.$password);
                if(!(isset($this->proxy[$key]) && !empty($this->proxy[$key]) && $this->proxy[$key]['expire'] >= time())){
                    $mysql = new \Swoole\Coroutine\MySQL();
                    $mysql->connect($this->dbconf);
                    if(!$mysql->connected){
                        $mysql->connect($this->dbconf);
                    } 
                    if($mysql->connected){
                        $sql = "select password,ip,port,protocol from host where account = ?";
                        $smtp = $mysql->prepare($sql);        
                        $result = $smtp->execute([$username]);
                        $result = $result ? array_pop($result) : false;
                        if($result['password'] == trim($password)){
                            $this->proxy[$key]['host'] = $result['ip'];  
                            $this->proxy[$key]['port'] = $result['port'];
                            $this->proxy[$key]['expire'] = time() + 24*3600;
                        }
                    }else{
                        $this->record("mysql connect failed ! dbconf:" . json_encode($this->dbconf), "ERROR");
                        $this->server->send($fd, "mysql connected failed. please try later");
                        $this->server->close($fd);
                    }
                    if(empty($this->proxy)){
                        $this->record("http authorization failed ! login: {$username}@{$password}", "ERROR");
                        $this->server->send($fd, "basic authorization failed \r\n");
                        $this->server->close($fd);
                    }
                }

                if(isset($this->proxy[$key]) && $this->proxy[$key]){
                    $socket = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC); 
                    $socket->closing = false;
                    $socket->on('connect', function(swoole_client $socket) use ($data, $fd){
                        $socket->send($data); 
                    });
                    $currProxy = $this->proxy[$key];
                    $socket->on('error', function(swoole_client $socket) use ($fd, $currProxy){
                        $this->record("connect to backend server failed ! host:port " . json_encode($currProxy), "ERROR");
                        $this->server->send($fd, "backend server connect failed. please try later.\r\n");
                        $this->server->close($fd);
                    }); 

                    $socket->on('close', function (swoole_client $socket) use ($fd, $currProxy){
                        $this->record("close backend server ! backend[{$socket->sock}] host:port " . json_encode($currProxy), "INFO");
                        unset($this->backends[$socket->sock]);
                        unset($this->frontends[$fd]);
                        if (!$socket->closing)
                        {
                            $this->server->close($fd);
                        }
                    });
                    $socket->on('receive', function (swoole_client $socket, $_data) use ($fd, $server){
                        $this->server->send($fd, $_data);
                    });
                    if ($socket->connect($currProxy['host'], $currProxy['port'], 0.5))
                    {
                        $this->backends[$socket->sock] = $fd;
                        $this->frontends[$fd] = $socket;
                    }
                    else
                    {
                        $this->record("connect to backend server failed ! host:port " . json_encode($currProxy), "ERROR");
                        $this->server->send($fd, "backend server connect failed. please try it later.\r\n");
                        $this->server->close($fd);
                    }
                }
            }else{
                $content = <<<EOF
HTTP/1.1 407 Proxy Authentication Required 
Server: nginx
Content-Type: text/html; charset=UTF-8
Connection: keep-alive
X-Powered-By: Nginx 1.12.1 
Proxy-Authenticate: Basic realm="Test Authentication System"
EOF;
                $this->server->send($fd, $content);
                $this->server->close($fd);
            }
        }else{
            $socket = $this->frontends[$fd];
            $socket->send($data); 
        }  
    }

    public function record($msg, $level = 'INFO')
    {
        $content = "[{$level}] " . date('Y-m-d H:i:s') . " {$msg}\r\n"; 
        echo $content;
    }
}

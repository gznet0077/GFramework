<?php

namespace G;

use G\Error\Php;
use G\Exception\MethodNotAllowerd;
use G\Exception\NotFound;
use G\Middleware\IpAddress;
use G\Middleware\BodyParser;
use G\Middleware\OverrideMethod;
use G\Middleware\Powered;
use G\Util\Misc;
use G\Util\Sanitize;
use Swoole\Atomic;
use Swoole\Buffer;
use Swoole\Http\Server as HttpServer;
use Swoole\Websocket\Server as WebsocketServer;
use Swoole\Http\Request;
use Swoole\Http\Response;


class Application implements IMiddleware
{
    const MOD_WEB = 1 << 0;
    const MOD_CRON = 1 << 1;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var \ArrayObject
     */
    protected $settings;

    /**
     * @var \ArrayObject
     */
    protected $serverConfig;

    /**
     * @var Session
     */
    protected $sessions;

    /**
     * @var Router
     */
    protected $router;

    private $_error;

    private $_success;

    private $_tasks = [];

    private $_crontab = [];

    private $_action = [];

    private $mod = self::MOD_WEB;

    private $_task_time;
    private $_task_count;

    use TMiddleware;

    public function __construct(array $config = [], $serverConfig = [], $mod = self::MOD_WEB)
    {
        $this->settings = new \ArrayObject($config);
        $this->serverConfig = new \ArrayObject($serverConfig);

        $this->sessions = new Session();

        $this->mod = $mod;
        if ($this->mod & self::MOD_WEB) {
            $this->router = new Router();
        }
        $this->_task_time = new Atomic(0);
        $this->_task_count = new Atomic(0);
    }

    public function setError($error)
    {
        $this->_error = $error;
    }

    public function setSuccess($success)
    {
        $this->_success = $success;
    }

    /**
     * @return Server
     */
    public function getServer()
    {
        return $this->server;
    }

    public function onStart($handler)
    {
        if (!is_callable($handler)) {
            throw new \RuntimeException('onStart handler 不可运行');
        }

        $this->settings['onStart'] = $handler;
    }

    public function onWorkerStart($handler)
    {
        if (!is_callable($handler)) {
            throw new \RuntimeException('onWorkerStart handler 不可运行');
        }

        $this->settings['onWorkerStart'] = $handler;
    }

    public function onWorkerStop($handler)
    {
        if (!is_callable($handler)) {
            throw new \RuntimeException('onWorkerStop handler 不可运行');
        }

        $this->settings['onWorkerStop'] = $handler;
    }

    public function onTask($handler)
    {
        if (!is_callable($handler)) {
            throw new \RuntimeException('onTask handler 不可运行');
        }

        $this->settings['onTask'] = $handler;
    }

    public function onFinish($handler)
    {
        if (!is_callable($handler)) {
            throw new \RuntimeException('onFinish handler 不可运行');
        }

        $this->settings['onFinish'] = $handler;
    }

    public function setting($name, $value = null)
    {
        if (!is_null($value)) {
            $this->settings[$name] = $value;
            return;
        }

        return $this->settings[$name] ?? null;
    }

    public function mod()
    {
        return $this->mod;
    }

    public function getRouter()
    {
        return $this->router;
    }

    public function use (...$handler)
    {
        if (~$this->mod & self::MOD_WEB) {
            throw new \RuntimeException('只有 web 模式下才能添加中间件');
        }
        $this->router->use(...$handler);
        return $this;
    }

    public function get($path, ...$handlers)
    {
        $this->router->map('GET', $path, ...$handlers);
        return $this;
    }

    public function post($path, ...$handlers)
    {
        $this->router->map('POST', $path, ...$handlers);
        return $this;
    }

    public function put($path, ...$handlers)
    {
        $this->router->map('PUT', $path, ...$handlers);
        return $this;
    }

    public function delete($path, ...$handlers)
    {
        $this->router->map('DELETE', $path, ...$handlers);
        return $this;
    }

    public function any($path, ...$handlers)
    {
        $this->router->map('*', $path, ...$handlers);
        return $this;
    }

    public function map($method, $path, ...$handlers)
    {
        if (~$this->mod & self::MOD_WEB) {
            throw new \RuntimeException('只有 web 模式下才能添加路由');
        }
        $this->router->map($method, $path, ...$handlers);
        return $this;
    }

    public function task($name, $cb)
    {
        if (!is_callable($cb)) {
            throw new \RuntimeException("task {$name} 回调必须为 callable");
        }
        $this->_tasks[$name] = $cb;
    }

    public function cron($rule, $action, $triggerOnStart, $timeout)
    {
        if (~$this->mod & self::MOD_CRON) {
            throw new \RuntimeException('只有 cron 模式下才能添加定时器');
        }
        if (!is_callable($action)) {
            throw new \RuntimeException("cron {$rule} 回调必须为 callable");
        }
        $this->_crontab[] = ['rule' => $rule, 'action' => $action, 'triggerOnStart' => $triggerOnStart, 'timeout' => $timeout];
    }

    public function action($name, $handler)
    {
        if (!is_callable($handler)) {
            throw new \RuntimeException("action {$name} 回调必须为 callable");
        }
        $name = preg_replace('/:{2,}/', ':', $name);
        $this->_action[$name] = $handler;
    }

    /**
     * http request
     *
     * @param Request $request
     * @param Response $response
     */
    public function _onRequest(Request $request, Response $response)
    {
        $cxt = new Context($this->server, $request, $response);
        $cxt->setError($this->_error);
        $cxt->setSuccess($this->_success);

        $gzip = Sanitize::sanitize(($this->setting('gzip')), Sanitize::INT);
        $isDebug = Sanitize::bool($this->setting('debug'));

        if (!$isDebug && $gzip > 0) {
            $cxt->gzip($gzip);
        }
        try {
            $this->process($cxt);
        } catch (NotFound $e) {
            $cxt->notFound();
        } catch (MethodNotAllowerd $e) {
            $cxt->notAllowed();
        } catch (\Exception $e) {
            (new Php($cxt))($e);
        }
    }

    /**
     * websocket, 自定义握手, 只是为了改变Header 的 server 值..
     *
     * @param Request $request
     * @param Response $response
     * @return bool
     */
    public function _onHandShake(Request $request, Response $response)
    {
        if (
            !isset($request->header['sec-websocket-key'])
            || 0 === preg_match('#^[+/0-9A-Za-z]{21}[AQgw]==$#', $request->header['sec-websocket-key'])
            || 16 !== strlen(base64_decode($request->header['sec-websocket-key']))

        ) {
            $response->end();
            return false;
        }

        $key = base64_encode(sha1($request->header['sec-websocket-key']
            . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true));

        $headers = array(
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Accept' => $key,
            'Sec-WebSocket-Version' => '13',
            'KeepAlive' => 'off',
            'server' => 'DK server/1.0.21',
            'X-Powered-By' => 'DK Engine'
        );
        foreach ($headers as $key => $val) {
            $response->header($key, $val);
        }

        $fd = $request->fd;

        $this->sessions->set($fd, [
            'fd' => $request->fd,
            'uid' => '',
            'request' => $request,
            'buff' => new Buffer(512),
        ]);

        $response->status(101);
        $response->end();
        return true;
    }


    /**
     * websocket
     *
     * @param $server
     * @param $fd
     */
    public function _onClose($server, $fd)
    {
        $this->sessions->delete($fd);
    }

    /**
     * websocket
     *
     * @param $server
     * @param $frame
     */
    public function _onMessage($server, $frame)
    {
        $fd = $frame->fd;
        $session = $this->sessions->get($fd);
        $session['buff']->append($frame->data);
        if ($frame->finish) {
            $cxt = new Action($server, $session);

            $action = $cxt->getAction();

            if ($action && $this->_action[$action]) {
                try {
                    if ($this->_action[$action] instanceof IMiddleware) {
                        $this->_action[$action]->process($cxt);
                    } else if (is_callable($this->_action[$action])) {
                        call_user_func($this->_action[$action], $cxt);
                    }
                } catch (\Exception $e) {
                    (new Php($cxt))($e);
                }
            }

            $session['buff']->clear();
        }
        $this->sessions->set($fd, $session);
    }

    public function _onTask($server, $task_id, $from_id, $data)
    {
        $onTask = $this->setting('onTask');
        if (is_callable($onTask)) {
            call_user_func($onTask, $server, $task_id, $from_id, $data);
        }

        $server->taskCount++;
        $type = $data['type'];
        $type = str_replace('::', ':', $type);
        $data = $data['data'];
        array_unshift($data, $server);
        if (!isset($this->_tasks[$type])) {
            throw new \RuntimeException("task ${type} 不存在");
        }
        if ($cb = $this->_tasks[$type]) {
            try {
                $start = microtime(true);
                $rs = call_user_func_array($cb, $data);
                $time = microtime(true) - $start;
                $this->_task_time->add(intval($time * 1000));
                $this->_task_count->add(1);
                if ($time > 2) {
                    $out = "$type 执行时间过长 $time ";
                    foreach ($data as $index => $val) {
                        if (!is_array($val) && !is_object($val)) {
                            $out .= ' ' . $val;
                        }
                    }
                    echo $out . "\n";
                }
            } catch (\Exception $e) {
                (new Php(null))->cli($e);
                return false;
            }
            return $rs;
        }
        return false;
    }

    // 防止出错, task finish
    public function _onFinish($server, $task_id, $data)
    {
        $onFinish = $this->setting('onFinish');
        if (is_callable($onFinish)) {
            call_user_func($onFinish, $server, $task_id, $data);
        }
        return $data;
    }

    public function _onWorkerStart($server, $worker_id)
    {
        $onWorkerStart = $this->setting('onWorkerStart');
        if (is_callable($onWorkerStart)) {
            call_user_func($onWorkerStart, $server, $worker_id);
        }

        // 如果是 cron 模式, 将 crontab 的任务平均分配到每个 worker 执行,
        // 长时间的执行的任务 worker 可以投递到 task_worker 执行
        if (!$server->taskworker && $this->mod & self::MOD_CRON) {
            $worker_num = $server->setting['worker_num'];
            foreach ($this->_crontab as $index => $cron) {
                if ($index % $worker_num == $worker_id) {
                    (new Cron($this->server, $cron['rule'], $cron['action'], $cron['timeout']))->run($cron['triggerOnStart']);
                }
            }
        }

        // 在第一个进程定时清空过期的 session
        if ($this->serverConfig['websocket'] && !$server->taskworker && $worker_id == 0) {
            $server->tick(5 * 1000, function () use ($server) {
                $this->sessions->clear(5 * 60, function ($fd) use ($server) {
                    $server->close($fd);
                });
            });
        }

        $uname = php_uname('s');
        if ($uname != 'Darwin') {
            $processTitle = $this->serverConfig['process_title'] ?? $this->setting('process_title');
            if ($processTitle) {
                swoole_set_process_name($processTitle . ' ' . ($server->taskworker ? 'TaskWorker' : 'WebWorker'));
            }
        }
        if ($worker_id == 0) {
            $server->tick(1000, function () use ($server) {
                echo sprintf(
                        '任务统计: 当前任务数: %s 总任务数: %s 总用时: %s 平均: %s',
                    $server->stats()['tasking_num'],
                        $this->_task_count->get(),
                        $this->_task_time->get() / 1000,
                        $this->_task_time->get() / 1000 / ($this->_task_count->get() ?: 1)
                    ) . PHP_EOL;
            });
        }
    }

    public function _onWorkerStop($server, $worker_id)
    {
        $onWorkerStop = $this->setting('onWorkerStop');
        if (is_callable($onWorkerStop)) {
            call_user_func($onWorkerStop, $server, $worker_id);
        }

        if (Sanitize::bool($this->setting('debug'))) {
            $errno = $server->getLastError();
            $error = swoole_strerror($errno);
            echo date('Y-m-d H:i:s') . " Worker {$worker_id} 重启, 任务数: {$server->taskCount} 错误: {$errno} {$error}\n";
        }
    }

    public function _onStart($server)
    {
        $onStart = $this->setting('onStart');
        if (is_callable($onStart)) {
            call_user_func($onStart, $server);
        }
    }

    public function run()
    {
        $cpuNum = Misc::cpu_number();
        $uname = php_uname('s');

        $serverSettings = (array)$this->serverConfig ?? [];

        $host = $serverSettings['host'] ?? '127.0.0.1';
        $port = $serverSettings['port'] ?? '8000';

        if (Sanitize::bool($this->serverConfig['websocket'])) {
            $this->server = new WebsocketServer($host, $port);
            $this->server->on('handshake', [$this, '_onHandShake']);
            $this->server->on('close', [$this, '_onClose']);
            $this->server->on('message', [$this, '_onMessage']);

            // 不能设为1/3, 没有onClose事件, 详情看 https://wiki.swoole.com/wiki/page/277.html
            $serverSettings['dispatch_mode'] = 2;
        } else {
            $this->server = new HttpServer($host, $port);
            if (!isset($serverSettings['dispatch_mode'])) {
                $serverSettings['dispatch_mode'] = 3;
            }
        }


        if (!isset($serverSettings['task_ipc_mode'])) {
            $serverSettings['task_ipc_mode'] = 3;
        }

        if (!isset($serverSettings['message_queue_key'])) {
            $serverSettings['message_queue_key'] = uniqid();
        }
        unset($serverSettings['message_queue_key']);

        if (!isset($serverSettings['task_tmpdir'])) {
            $serverSettings['task_tmpdir'] = '/dev/shm';
        }

        if ($uname == 'Darwin') {
            unset($serverSettings['task_tmpdir']);
        }

        if (!isset($serverSettings['task_max_request'])) {
            $serverSettings['task_max_request'] = 4000;
        }
        if (!isset($serverSettings['backlog'])) {
            $serverSettings['backlog'] = 3000;
        }

        $serverSettings['max_request'] = 0;
        $serverSettings['open_tcp_nodelay'] = 1;

        // 关闭自动解析 post 数据, 自己写 middleware 实现
        $serverSettings['http_parse_post'] = 0;

        $this->server->on('ManagerStart', [$this, '_onStart']);
        $this->server->on('WorkerStart', [$this, '_onWorkerStart']);
        $this->server->on('WorkerStop', [$this, '_onWorkerStop']);

        if (!$serverSettings['worker_num']) {
            $serverSettings['worker_num'] = $cpuNum;
        }

        $this->server->on('Request', [$this, '_onRequest']);

        if (count($this->_tasks)) {
            if (!$serverSettings['task_worker_num']) {
                $serverSettings['task_worker_num'] = $cpuNum * 2;
            }
            //
            if ($this->mod == (self::MOD_WEB | self::MOD_CRON)) {
                $serverSettings['task_worker_num'] *= 2;
            }
            $this->server->on('Task', [$this, '_onTask']);
            $this->server->on('Finish', [$this, '_onFinish']);
        } else {
            unset($serverSettings['task_worker_num']);
        }

        $this->server->on('start', function () use ($host, $port, $uname) {
            if ($uname != 'Darwin') {
                $processTitle = $this->serverConfig['process_title'] ?? $this->setting('process_title');
                if ($processTitle) {
                    swoole_set_process_name($processTitle . ' Master');
                }
            }
            echo "服务已启动, 正在监听 {$host} 端口: {$port}, 请通过 http://{$host}:{$port} 访问网站 \n";
        });

        if ($this->mod & self::MOD_WEB) {
            $this->_initMiddleware();
        }

        $this->server->set($serverSettings);
        $this->server->debug = Sanitize::bool($this->settings['debug']);
        $this->server->settings = $this->settings;

        $this->server->start();
    }

    public function _initMiddleware()
    {
        if (Sanitize::bool($this->setting('determine_proxy_ip'))) {
            $this->chain(new IpAddress());
        }

        $this->chain(new Powered());

        if (Sanitize::bool($this->setting('parse_body'))) {
            $this->chain(new BodyParser());
        }

        if (Sanitize::bool($this->setting('method_override'))) {
            $this->chain(new OverrideMethod());
        }

    }

    public function handler($c)
    {
        if (is_null($c->settings)) {
            $c->settings = new \ArrayObject();
        }

        $c->app = $this;
        $c->settings = Misc::mergeObject($c->settings, $this->settings);

        yield;
    }

    public function done($c)
    {
        yield $this->router;
    }
}
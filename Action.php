<?php


namespace G;


use G\Util\Sanitize;
use Swoole\WebSocket\Server;

class Action implements IMiddleware
{
    use TMiddleware, TRequest, TContext {
        TRequest::data as RequestData;
    }

    protected $data;

    protected $fd;

    protected $session;

    protected $action;

    protected $callback;

    public function __construct(Server $server, $session)
    {
        $this->server = $server;
        $this->session = $session;
        $this->fd = $session['fd'];
        $this->request = $session['request'];
        $this->_collection = new \ArrayObject();
        $buff = $session['buff']->substr(0, -1, true);
        $session['buff']->clear();

        try {
            $data = json_decode($buff, true);
            $this->action = preg_replace('/:{2,}/', ':', $data['action']);
            $this->data = $data['data'];
            $this->callback = $data['callback'];
        } catch (\Exception $e) {

        }
    }

    public function getAction()
    {
        return $this->action;
    }

    public function getData()
    {
        return $this->data;
    }

    public function data($name = null, $default = null, $type = Sanitize::STRING)
    {
        $data = $this->data ?? [];
        return Sanitize::filter($data, $name, $type, $this->RequestData($name, $default, $type));
    }

    public function push($action, $data)
    {
        $action = preg_replace('/:{2}/', ':', $action);
        if (Sanitize::bool($this->settings['debug'])) {
            $data = json_encode(['action' => $action, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            $data = json_encode(['action' => $action, 'data' => $data]);
        }

        $this->server->push($this->fd, $data);
    }

    public function halt($action, $msg)
    {
        $this->push($action, ['msg' => $msg]);
    }

    public function response($data) {
        if ($this->callback) {
            $this->push($this->callback, $data);
        }
    }
}
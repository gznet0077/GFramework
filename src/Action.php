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

    protected $uuid;

    /**
     * @var Session
     */
    protected $sessions;
    protected $session;

    protected $action;

    protected $callback;

    /**
     * @var IMiddleware
     */
    protected static $_middleware = null;

    public function __construct(Server $server, $sessions, $uuid = '')
    {
        $this->server = $server;
        $this->sessions = $sessions;
        if (!$uuid) {
            return;
        }
        $session = $sessions->get($uuid);
        $this->session = $session;
        $this->fd = $session['fd'];
        $this->uuid = $session['uuid'];
        $this->request = $session['request'];
        $this->_collection = new \ArrayObject();
        $buff = $session['buff']->substr(0, -1, true);
        $session['buff']->clear();

        if ($buff == 'ping') {
            return $this->action = 'ping';
        }

        try {
            $data = json_decode($buff, true);
            $this->action = preg_replace('/:{2,}/', ':', $data['action']);
            $this->data = $data['data'];
            $this->callback = $data['callback'];
        } catch (\Exception $e) {

        }
    }

    public static function use(...$handlers)
    {
        if (is_null(self::$_middleware)) {
            self::$_middleware = new Middleware($handlers);
        } else {
            foreach ($handlers as $handler) {
                self::$_middleware->chain($handler);
            }
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

    public function push($action, $data = null)
    {
        if (!$this->fd) {
            throw new \RuntimeException('Action->fd 为空, 请使用 pushTo 发送');
        }
        $data = $this->pack($action, $data);
        $this->server->push($this->fd, $data);
    }

    public function pushTo($fd, $action, $data)
    {
        $data = $this->pack($action, $data);
        $this->server->push($fd, $data);
    }

    public function broadcast($room, $action, $data, $filter = null)
    {
        $members = $this->sessions->members($room);
        $rs = $this->pack($action, $data);
        foreach ($members as $member) {
            if (is_callable($filter) && !call_user_func($filter, $member)) {
                continue;
            }
            $this->server->push($member['fd'], $rs);
        }
    }

    public function broadcastToMyRooms($action, $data, $filter = null)
    {
        if (!$this->uuid) {
            throw new \RuntimeException('Action->uuid 为空, 请使用 broadcast 发送');
        }
        $rooms = $this->sessions->getRooms($this->uuid);
        foreach ($rooms as $room) {
            $this->broadcast($room, $action, $data, $filter);
        }
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

    protected function pack($action, $data)
    {
        $action = preg_replace('/:{2}/', ':', $action);
        if (Sanitize::bool($this->settings['debug'])) {
            $rs = json_encode(['action' => $action, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } else {
            $rs = json_encode(['action' => $action, 'data' => $data]);
        }
        return $rs;
    }

    public function __get($name)
    {
        return $this->server->{$name};
    }
}
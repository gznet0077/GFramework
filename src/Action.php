<?php


namespace G;


use G\Util\Sanitize;
use Swoole\WebSocket\Server;

class Action implements IMiddleware
{
    use TMiddleware;

    protected $data;

    protected $fd;


    /**
     * @var ISession
     */
    protected $session;

    protected $action;

    protected $callback;

    /**
     * @var IMiddleware
     */
    protected static $_middleware = null;

    public function __construct(Server $server, $frame = null, $session = null)
    {
        $this->server = $server;

        if ($session) {
            $this->session = $session;
        }

        if (!$frame) {
            return;
        }

        $this->fd = $frame->fd;

        $buff = $server->buffs[$this->fd]->substr(0, -1, true);
        $server->buffs[$this->fd]->clear();

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

    public function getUUID()
    {
        return $this->uuid;
    }

    public function getFd()
    {
        return $this->fd;
    }

    public function setSession(ISession $session)
    {
        $this->session = $session;
    }

    public function getSession()
    {
        return $this->session;
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
        $members = $this->session->members($room);
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
        if (!$this->fd) {
            throw new \RuntimeException('Action->fd 为空, 请使用 broadcast 发送');
        }
        $rooms = $this->session->rooms($this->fd);
        foreach ($rooms as $room) {
            $this->broadcast($room, $action, $data, $filter);
        }
    }

    public function halt($action, $msg)
    {
        $this->push($action, ['msg' => $msg]);
    }

    public function response($data)
    {
        if ($this->callback) {
            $this->push($this->callback, $data);
        }
    }

    protected function pack($action, $data)
    {
        $action = preg_replace('/:{2}/', ':', $action);

        $rs = json_encode(['action' => $action, 'data' => $data], JSON_UNESCAPED_UNICODE);

        return $rs;
    }

    public function __get($name)
    {
        return $this->server->{$name};
    }
}
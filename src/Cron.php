<?php


namespace G;


use Swoole\Http\Server;

class Cron
{
    /**
     * @var Server
     */
    private $server;

    private $rule;
    /**
     * @var callable
     */
    private $cron;

    private $running = false;
    private $runningTask = 0;

    private $timer;

    private $timeout = 10;

    /**
     * @var callable
     */
    private $s, $m, $h, $d, $M, $w;

    public function __construct($server, $rule, $cron, $timeout)
    {
        $this->server = $server;
        $this->rule = $rule;
        $this->cron = $cron;
        if (is_numeric($timeout)) {
            $this->timeout = $timeout;
        }

        $this->_init();
    }

    public function run($triggerOnStart = false)
    {
        if ($triggerOnStart) {
            $this->trigger(true);
        }

        $this->server->tick(1000, function ($id) {
            // 同一时间只执行一次
            if ($this->running) {
                return;
            }
            if ($this->_isTimeUp()) {
                $this->trigger(false);
            }
        });
    }

    public function trigger($isOnStart)
    {
        $this->running = true;
//        if ($this->timeout > 0) {
//            // 如果在回调中没有调用 Cron->task() 方法就必须调用 Cron->free() 来释放锁
//            $this->timer = $this->server->after($this->timeout * 1000, function () {
//                $this->free();
//                if ($this->server->debug) {
//                    throw new \RuntimeException("cron {$this->cron} 调用超时,
//                    可能在回调中没有调用 Cron->task() 方法或没有调用 Cron->free() 来释放锁");
//                } else {
//                    echo "cron {$this->cron} 调用超时,
//                    可能在回调中没有调用 Cron->task() 方法或没有调用 Cron->free() 来释放锁\n";
//                }
//            });
//        }

        try {
            call_user_func($this->cron, $this, $isOnStart);
        } catch (\Exception $e) {

        }
    }

    public function task($name, ...$data)
    {
        $fn = end($data);
        if (is_callable($fn)) {
            array_pop($data);
            $data = [
                'type' => $name,
                'data' => $data,
            ];
        } else {
            $fn = function () {
            };
            $data = [
                'type' => $name,
                'data' => $data,
            ];
        }

        ++$this->runningTask;
        $this->server->task($data, -1, function ($serv, $task_id, $data) use ($fn) {
            call_user_func($fn, $serv, $task_id, $data);
            --$this->runningTask;
            $this->free();
        });
    }

    public function free()
    {
        if ($this->runningTask > 0) {
            return;
        }

        if (!is_null($this->timer)) {
            $this->server->clearTimer($this->timer);
            $this->timer = null;
        }

        $this->running = false;
    }

    /**
     * @return Server
     */
    public function getServer()
    {
        return $this->server;
    }

    private function _init()
    {
        $rules = preg_split('/\s+/', $this->rule);
        if (count($rules) != 6) {
            throw new \InvalidArgumentException('cron rule 必须为 6 项, 格式 "秒 分 时 日 月 星期"');
        }

//        list($this->s, $this->m, $this->h, $this->d, $this->M, $this->w) = array_map([$this, '_parse'], $rules);
        list($s, $m, $h, $d, $M, $w) = array_map([$this, '_parse'], $rules);
        $this->s = $s;
        $this->m = $m;
        $this->h = $h;
        $this->d = $d;
        $this->M = $M;
        $this->w = $w;
    }

    private function _parse($s)
    {
        if ($s == '*') {
            return function ($n) {
                return true;
            };
        }

        // */2 格式
        if (preg_match('~^\*/\d+$~', $s)) {
            $p = explode('/', $s);
            $m = intval($p[1]);
            return function ($n) use ($m) {
                return $n % $m == 0;
            };
        }

        // 2-3 这样的格式
        if (preg_match('~^\d+\-\d+$~', $s)) {
            $p = explode('-', $s);
            $p = array_map('intval', $p);
            $m = min($p);
            $m1 = max($p);

            return function ($n) use ($m, $m1) {
                return $m <= $n && $n <= $m1;
            };
        }

        // 2,3,4 这样的格式
        if (preg_match('~^\d+(,\d+)*$~', $s)) {
            $p = explode(',', $s);
            $p = array_map('intval', $p);
            return function ($n) use ($p) {
                return in_array($n, $p);
            };
        }

        return function ($n) {
            return false;
        };
    }

    private function _isTimeUp()
    {
        $ps = date('s,i,H,d,m,w');
        $ps = explode(',', $ps);
        list($s, $m, $h, $d, $M, $w) = array_map('intval', $ps);

        return
            $this->s($s) && $this->m($m)
            && $this->h($h) && $this->d($d)
            && $this->M($M) && $this->w($w);
    }

    public function __call($name, $arguments)
    {
        if (property_exists(__CLASS__, $name) && is_callable($this->$name)) {
            return call_user_func_array($this->$name, $arguments);
        }
        return false;
    }
}
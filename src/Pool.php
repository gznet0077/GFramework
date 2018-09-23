<?php


namespace G;


use G\Exception\PoolMaxWait;
use G\Exception\PoolTimeout;
use Swoole\Atomic;

class Pool
{
    protected $_min = 4;
    protected $_max = 10;
    protected $_idle = 30 * 60;
    protected $_maxWait = 4;

    /**
     * @var Atomic;
     */
    protected $_count;
    protected $_waitingCount;

    /**
     * @var \Closure
     */
    protected $_createFn;
    protected $_destroyFn;

    /**
     * @var \SplQueue
     */
    protected $_queue;

    /**
     * @var \Generator
     */
    protected $_gen;

    public function __construct($config)
    {
        if (isset($config['min'])) {
            $this->_min = $config['min'];
        }
        if (isset($config['max'])) {
            $this->_max = $config['max'];
        }
        if (isset($config['idle'])) {
            $this->_idle = $config['idle'];
        }
        if (isset($config['maxWait'])) {
            $this->_maxWait = $config['maxWait'];
        }
        if (!is_callable($config['create'])) {
            throw \Exception('create 必须是 callable ');
        }
        $this->_createFn = $config['create'];

        if (!is_callable($config['destroy'])) {
            throw \Exception('destroy 必须是 callable ');
        }
        $this->_destroyFn = $config['destroy'];

        $this->_queue = new \SplQueue();
        $this->_count = new Atomic(0);
        $this->_waitingCount = new Atomic(0);
        // 初始化, 添加连接
        for ($i = 0; $i < $this->_min; $i++) {
            $client = $this->createClient();
            $this->_queue->enqueue($client);
        }

        $this->_gen = $this->gen();
        $this->_gen->valid(); // 执行一次, 让生成器开始工作, 要不数值不对
    }

    protected function createClient()
    {
        if (!is_callable($this->_createFn)) {
            throw new \Exception('必须定义 createFn ');
        }

        $client = call_user_func($this->_createFn);
        $client->__ts = time();
        $this->_count->add(1);
        return $client;
    }


    protected function gen()
    {
        while (true) {
            $len = $this->_queue->count();
            $total = $this->_count->get();
            if ($len > 0) {
                yield $this->_queue->dequeue();
            } else if ($total < $this->_max) {
                yield $this->createClient();
            } else {
                $client = yield;
                $this->_queue->enqueue($client);
            }
        }
    }

    public function acquire($timeout = 3)
    {
        $this->_waitingCount->add(1);
        if ($this->_waitingCount > $this->_maxWait) {
            throw new PoolMaxWait(sprintf('连接池等待的客户端数量已超过 %s', $this->_maxWait));
        }
        $ts = time();
        while ($this->_gen->valid()) {

            if ($timeout > 0 && (time() - $ts > $timeout)) {
                throw new PoolTimeout();
            }
            $client = $this->_gen->current();
            if ($client) {
                $this->_waitingCount->sub(1);
                $this->_gen->next();
                return $client;
            }
            usleep(200);
        }
    }

    public function release($client)
    {
        if ($client->__ts + $this->_idle < time()) {
            if (is_callable($this->_destroyFn)) {
                call_user_func($this->_destroyFn, $client);
            } else {
                unset($client);
            }
            $this->_count->sub(1);
        } else {
            $this->_gen->send($client);
        }
    }

    public function len()
    {
        return $this->_queue->count() + 1;
    }

    public function total()
    {
        return $this->_count->get();
    }
}
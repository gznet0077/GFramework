<?php


namespace G;


use Swoole\Lock;

class Locker
{
    private $_lockers = [];

    public function __construct($names = [])
    {
        foreach ($names as $name) {
            $this->_lockers[$name] = new Lock(SWOOLE_MUTEX);
        }
    }

    public function trylock($name = 'default') {
        return $this->_lockers[$name]->trylock();
    }

    public function lock($name = 'default')
    {
        if (!$this->_lockers[$name]) {
            $this->_lockers[$name] = new Lock(SWOOLE_MUTEX);
        }
        $this->_lockers[$name]->lock();
    }

    public function unlock($name = 'default')
    {
        $this->_lockers[$name]->unlock();
    }

    public function clear($name = 'default')
    {
        unset($this->_lockers[$name]);
    }

    public function add($name)
    {
        $this->_lockers[$name] = new Lock(SWOOLE_MUTEX);
    }
}
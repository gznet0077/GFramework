<?php


namespace G;


use Swoole\Lock;

class Locker
{
    private static $_lockers = [];

    public function lock($name = 'default')
    {
        if (!self::$_lockers[$name]) {
            self::$_lockers[$name] = new Lock(SWOOLE_MUTEX);
        }
        self::$_lockers[$name]->lock();
    }

    public function unlock($name = 'default')
    {
        self::$_lockers[$name]->unlock();
    }

    public function clear($name = 'default')
    {
        unset(self::$_lockers[$name]);
    }
}
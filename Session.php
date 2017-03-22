<?php


namespace G;


class Session
{
    /**
     * @var Locker
     */
    protected $_lock;

    /**
     * @var array
     */
    protected $_sessions = [];

    public function __construct()
    {
        $this->_lock = new Locker();
    }

    public function set($key, $value)
    {
        $name = $this->getName($key);
        $this->_lock->lock($name);
        $this->_sessions[$key] = $value;
        $this->_lock->unlock($name);
    }

    public function get($key)
    {
        $name = $this->getName($key);
        $this->_lock->lock($name);
        $value = $this->_sessions[$key];
        $this->_lock->unlock($name);
        return $value;
    }

    public function delete($key)
    {
        $name = $this->getName($key);
        $this->_lock->lock($name);
        unset($this->_sessions[$key]);
        $this->_lock->unlock($name);
        $this->_lock->clear($name);
    }

    public function exist($key)
    {
        $name = $this->getName($key);
        $this->_lock->lock($name);
        $exits = isset($this->_sessions[$key]);
        $this->_lock->unlock($name);
        return $exits;
    }

    protected function getName($key)
    {
        return 'sessions:'.$key;;
    }
}
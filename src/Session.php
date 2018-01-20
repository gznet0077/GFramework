<?php


namespace G;


class Session
{
    /**
     * @var array
     */
    protected $_sessions = [];
    protected $_rooms = [];

    public function __construct()
    {

    }

    public function set($key, $value, $replace = false)
    {
        if (!isset($this->_sessions[$key])) {
            $this->_sessions[$key] = [];
        }
        if ($replace || !is_array($value) || empty($this->_sessions[$key])) {
            $this->_sessions[$key]['value'] = $value;
        } else if (is_array($value)) {
            foreach ($value as $k => $v) {
                $this->_sessions[$key]['value'][$k] = $v;
            }
        }
        $this->_sessions[$key]['ts'] = time();
    }

    public function get($key)
    {
        if (!isset($this->_sessions[$key]) || $this->_sessions[$key]['suspend']) {
            return null;
        }
        $value = $this->_sessions[$key]['value'];
        $this->_sessions[$key]['ts'] = time();
        return $value;
    }

    public function active($key)
    {
        $this->_sessions[$key]['ts'] = time();
    }

    /**
     * 获取加入的所有房间名
     *
     * @param $key
     * @return array
     */
    public function getRooms($key)
    {
        if (!isset($this->_sessions[$key]) || $this->_sessions[$key]['suspend']) {
            return [];
        }

        $this->_sessions[$key]['ts'] = time();
        return $this->_sessions[$key]['rooms'] ? array_keys($this->_sessions[$key]['rooms']) : [];
    }

    /**
     * 加入房间
     *
     * @param $key
     * @param $room
     *
     */
    public function join($room, $key)
    {
        if (!isset($this->_rooms[$room])) {
            $this->_rooms[$room] = [];
        }
        if (!isset($this->_rooms[$room][$key])) {
            $this->_rooms[$room][$key] = true;
            $this->_sessions[$key]['rooms'][$room] = true;
        }
    }

    /**
     * 退出房间
     *
     * @param $key
     * @param $room
     *
     * 是否退出房间, 如果没有加入任何房间返回false
     * @return bool
     */
    public function leave($room, $key)
    {
        if (!isset($this->_rooms[$room][$key])) {
            return false;
        }
        unset(
            $this->_rooms[$room][$key],
            $this->_sessions[$key]['rooms'][$room]
        );
        return true;
    }

    public function inRoom($room, $key)
    {
        return $this->_rooms[$room][$key] && $this->_sessions[$key]['rooms'][$room];
    }

    /**
     * 获取房间成员
     *
     * @param $room
     * @return \Generator
     */
    public function members($room)
    {
        foreach (array_keys($this->_rooms[$room]) as $key) {
            $member = $this->get($key);
            if ($member) {
                yield $member;
            }
        }
    }

    public function suspend($key)
    {
        $this->_sessions[$key]['suspend'] = true;
    }

    public function restore($key)
    {
        $this->_sessions[$key]['suspend'] = false;
    }

    public function delete($key)
    {
        $rooms = $this->getRooms($key);
        foreach ($rooms as $room) {
            $this->leave($room, $key);
        }
        unset($this->_sessions[$key]);
    }

    public function exist($key)
    {
        $exits = isset($this->_sessions[$key]);
        return $exits;
    }

    public function clear($second, $callback)
    {
        $ts = time();
        foreach ($this->_sessions as $key => $session) {
            if ($session['ts'] + $second < $ts) {
                $this->delete($key);
                if (is_callable($callback)) {
                    call_user_func($callback, $key, $session);
                }
            }
        }
    }
}
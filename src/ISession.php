<?php


namespace G;


interface ISession
{
    public function set($key, $value);

    /**
     * 设置
     * @param $values
     * @param bool $replace
     * @return mixed
     */
    public function mSet($values, $replace = false);

    /**
     * 获取
     * @param $key
     * @return mixed
     */
    public function get($key);

    /**
     * 激活, 过期会清除
     * @return mixed
     */
    public function active();

    /**
     * 加入房间
     * @param $room
     * @return mixed
     */
    public function join($room);

    /**
     * 离开房间
     * @param $room
     * @return mixed
     */
    public function leave($room);

    /**
     * 是否在房间内
     * @param $room
     * @return mixed
     */
    public function inRoom($room);

    /**
     * 获取所在的全部房间
     * @return mixed
     */
    public function rooms();

    /**
     * 获取房间里的所有成员
     * @param $room
     * @return mixed
     */
    public function members($room);

    /**
     * 暂停发言
     * @return mixed
     */
    public function suspend();

    /**
     * 恢复发言
     * @return mixed
     */
    public function restore();

    /**
     * 删除
     * @return mixed
     */
    public function delete();

    /**
     * 是否存在
     * @return mixed
     */
    public function exists();
}
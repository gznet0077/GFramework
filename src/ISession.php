<?php


namespace G;


interface ISession
{
    /**
     * 设置
     * @param $key
     * @param $value
     * @param bool $replace
     * @return mixed
     */
    public function set($key, $value, $replace = false);

    /**
     * 获取
     * @param $key
     * @return mixed
     */
    public function get($key);

    /**
     * 激活, 过期会清除
     * @param $key
     * @return mixed
     */
    public function active($key);

    /**
     * 加入房间
     * @param $room
     * @param $key
     * @return mixed
     */
    public function join($room, $key);

    /**
     * 离开房间
     * @param $room
     * @param $key
     * @return mixed
     */
    public function leave($room, $key);

    /**
     * 是否在房间内
     * @param $room
     * @param $key
     * @return mixed
     */
    public function inRoom($room, $key);

    /**
     * 获取所在的全部房间
     * @param $key
     * @return mixed
     */
    public function rooms($key);

    /**
     * 获取房间里的所有成员
     * @param $room
     * @return mixed
     */
    public function members($room);

    /**
     * 暂停发言
     * @param $key
     * @return mixed
     */
    public function suspend($key);

    /**
     * 恢复发言
     * @param $key
     * @return mixed
     */
    public function restore($key);

    /**
     * 删除
     * @param $key
     * @return mixed
     */
    public function delete($key);

    /**
     * 是否存在
     * @param $key
     * @return mixed
     */
    public function exists($key);
}
<?php


namespace G;

use G\Util\Sanitize;

trait TContext
{
    /**
     * @var Server
     */
    protected $server;

    /**
     * @var \ArrayObject
     */
    protected $_collection;

    protected $_error;

    protected $_success;

    public function getServer()
    {
        return $this->server;
    }

    public function isDebug()
    {
        return Sanitize::bool($this->settings['debug']);
    }

    public function setError($error)
    {
        $this->_error = $error;
    }

    public function setSuccess($success)
    {
        $this->_success = $success;
    }

    public function error($code, $args, $data = [])
    {
        if ($this->_error) {
            $msg = $this->_error->msg($code, $args);
        } else {
            $msg = '';
        }

        $this->json(array_merge(['code' => $code, 'msg' => $msg], $data), 400);
    }

    public function success($code, ...$args)
    {
        if ($this->_success) {
            $msg = $this->_success->msg($code, $args);
        } else {
            $msg = '';
        }

        $this->json(['code' => $code, 'msg' => $msg], 200);
    }

    public function notFound()
    {
        $this->writeStatus(404);
        $this->end(HttpConst::status(404));
    }

    public function notAllowed()
    {
        $this->writeStatus(405);
        $this->end(HttpConst::status(405));
    }

    public function getError()
    {
        return $this->_error;
    }

    public function getSuccess()
    {
        return $this->_success;
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
            $this->server->task($data, -1, $fn);
        } else {
            $data = [
                'type' => $name,
                'data' => $data,
            ];
            $this->server->task($data);
        }
    }

    public function taskWait($name, $timeout = 1, ...$data)
    {
        $data = [
            'type' => $name,
            'data' => $data,
        ];
        return $this->server->taskwait($data, $timeout);
    }

    public function taskWaitMulti($tasks, $timeout = 10)
    {
        return $this->server->taskWaitMulti($tasks, $timeout);
    }


    public function __call($name, $arguments)
    {
        if (is_callable($this->_collection[$name])) {
            return call_user_func_array($this->_collection[$name], $arguments);
        }
    }

    public function __get($name)
    {   // TODO: Action里会直接调用$this->>server的值,要修改
        return $this->_collection[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $this->_collection[$name] = $value;
        return $this;
    }
}
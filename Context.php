<?php


namespace G;


use G\Exception\AbortChain;
use G\Util\Sanitize;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class Context implements IMiddleware
{
    use TMiddleware, TRequest, TConext;

    /**
     * @var Response
     */
    protected $response;

    public function __construct(Server $server, Request $request, Response $response)
    {
        $this->server = $server;
        $this->request = $request;
        $this->response = $response;
        $this->_collection = new \ArrayObject();
    }

    public function writeHeader($key, $value)
    {
        $this->response->header($key, $value);
    }

    public function writeCookie($key, $value = '', $expire = 0, $path = '/', $domain = '', $secure = '', $httponly = false)
    {
        $this->response->cookie($key, $value, $expire, $path, $domain, $secure, $httponly);
    }

    public function writeStatus($status_code)
    {
        $this->response->status($status_code);
        return $this;
    }

    public function gzip($level)
    {
        $this->response->gzip($level);
        return $this;
    }

    public function write($data)
    {
        $this->response->write($data);
        return $this;
    }

    public function end($data = '')
    {
        $this->response->end($data);
        return $this;
    }

    public function json($data, $status = 200)
    {
        if (is_array($data) || is_object($data)) {
            if (Sanitize::bool($this->settings['debug'])) {
                $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            } else {
                $data = json_encode($data);
            }
        }

        $this->writeStatus($status);
        $this->writeHeader('Content-Type', 'application/json; charset=utf-8');
        $this->end($data);
    }

    public function html($data, $status = 200)
    {
        $this->writeStatus($status);
        $this->writeHeader('Content-Type', 'text/html; charset=utf-8');
        $this->end($data);
    }

    public function sendFile($filename)
    {
        $this->response->sendfile($filename);
    }

    public function halt($code, ...$args)
    {
        if ($this->_error) {
            $msg = $this->_error->msg($code, $args);
        } else {
            $msg = '';
        }


        $this->json(['code' => $code, 'msg' => $msg], 400);
    }

}
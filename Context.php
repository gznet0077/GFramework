<?php


namespace G;


use G\Exception\AbortChain;
use G\Util\Sanitize;
use G\Util\Misc;
use Swoole\Http\Request;
use Swoole\Http\Response;
use Swoole\Http\Server;

class Context implements IMiddleware
{
    use TMiddleware;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var Response
     */
    private $response;

    /**
     * @var Server
     */
    private $server;

    /**
     * @var \ArrayObject
     */
    private $_collection;

    private $_error;

    private $_success;

    public function __construct(Server $server, Request $request, Response $response)
    {
        $this->server = $server;
        $this->request = $request;
        $this->response = $response;
        $this->_collection = new \ArrayObject();
    }

    public function getServer() {
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

    /** request method */

    public function header($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->request->header;
        }
        return $this->request->header[$key] ?? $default;
    }

    public function server($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->request->server;
        }
        $val = $this->request->server[$key] ?? $default;
        if ($key == 'request_uri') {
            $val = urldecode($val);
        }
        return $val;
    }

    public function getUri()
    {
        return $this->server('request_uri', '/');
    }

    public function cookie($key = null, $default = null)
    {
        if (is_null($key)) {
            return $this->request->cookie;
        }
        return $this->request->cookie[$key] ?? $default;
    }

    public function files($name = null)
    {
        if ($name) {
            return $this->request->files[$name];
        }
        return $this->request->files;
    }

    public function rawBody()
    {
        return $this->request->rawContent() ?? '';
    }


    public function contentType()
    {
        return $this->header('content-type') ?: null;
    }

    public function getMediaType()
    {
        $contentType = $this->contentType();
        if ($contentType) {
            $contentTypeParts = preg_split('/\s*[;,]\s*/', $contentType);

            return strtolower($contentTypeParts[0]);
        }

        return null;
    }

    public function getHost()
    {
        if (!$this->host) {
            $http_host = $this->header('host', $this->server('server_name'));
            if (strpos($http_host, ':') !== false) {
                $this->host = explode(':', $http_host)[0];
            } else {
                $this->host = $http_host;
            }
        }

        return $this->host;
    }

    public function clientIP()
    {
        if (!$this->client_ip) {
            if ($val = $this->header('http_x_forwarded_for')) {
                $this->client_ip = trim(explode(',', $val)[0]);
            } else if ($val = $this->server('remote_addr')) {
                $this->client_ip = $val;
            } else {
                $this->client_ip = 'unknown';
            }
        }

        return $this->client_ip;
    }

    public function getMethod()
    {
        if (!isset($this->_collection['method'])) {
            $this->_collection['method'] = strtoupper($this->request->server['request_method']);
        }

        return $this->_collection['method'];
    }

    public function withMethod($method)
    {
        $this->_collection['method'] = strtoupper($method);
    }

    public function withBody($body)
    {
        $this->_collection['body'] = $body;
    }

    public function isMethod($method)
    {
        return $this->_collection['method'] == strtoupper($method);
    }

    public function isGet()
    {
        return $this->isMethod('GET');
    }

    public function isPost()
    {
        return $this->isMethod('POST');
    }

    public function isPut()
    {
        return $this->isMethod('PUT');
    }

    public function isDelete()
    {
        return $this->isMethod('DELETE');
    }

    public function isHead()
    {
        return $this->isMethod('HEAD');
    }

    public function isOptions()
    {
        return $this->isMethod('OPTIONS');
    }

    public function isXhr()
    {
        return $this->request->header['x-requested-with'] == 'XMLHttpRequest';
    }

    public function isMobile()
    {
        $user_agent = $this->request->header['user-agent'];
        return Misc::isMobile($user_agent);
    }

    public function param($name = null, $default = null, $type = Sanitize::STRING)
    {
        $params = $this->params ?? [];

        return Sanitize::filter($params, $name, $type, $default);
    }

    public function query($name = null, $default = null, $type = Sanitize::STRING)
    {
        return Sanitize::filter($this->request->get, $name, $type, $default);
    }

    public function post($name = null, $default = null, $type = Sanitize::STRING)
    {
        return Sanitize::filter($this->request->post, $name, $type, $default);
    }

    public function data($name = null, $default = null, $type = Sanitize::STRING)
    {
        $body = $this->body ?? [];

        return Sanitize::filter($body, $name, $type, $default);
    }

    public function all($name = null, $default = null, $type = Sanitize::STRING)
    {
        if (is_null($name)) {
            $params = $this->params ?? [];
            $body = $this->body ?? [];
            return array_merge($params, $this->request->get, $body);
        }

        return $this->data($name, null, $type) ?? $this->query($name, null, $type) ?? $this->post($name, $default, $type);
    }


    /** response method **/

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

    public function taskWait($name, ...$data)
    {
        $data = [
            'type' => $name,
            'data' => $data,
        ];
        return $this->server->task($data);
    }

    public function taskWaitMulti($tasks, $timeout = 10)
    {
        return $this->server->taskWaitMulti($tasks, $timeout);
    }

    /** 魔术方法 */

    public function __call($name, $arguments)
    {
        if (is_callable($this->_collection[$name])) {
            return call_user_func_array($this->_collection[$name], $arguments);
        }
    }

    public function __get($name)
    {
        return $this->_collection[$name] ?? null;
    }

    public function __set($name, $value)
    {
        $this->_collection[$name] = $value;
        return $this;
    }
}
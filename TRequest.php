<?php


namespace G;

use G\Util\Misc;

trait TRequest
{
    /**
     * @var Request
     */
    protected $request;

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

}
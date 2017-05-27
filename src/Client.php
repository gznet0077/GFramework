<?php


namespace G;


class Client
{
    /**
     * @var \Swoole\Http\Client
     */
    private $_client;

    public function __construct($host, $port)
    {
        $this->_client = new \Swoole\Http\Client($host, $port);
    }

    public function get($uri, $params = [], $options = [], $cb = null)
    {
        if (is_callable($params)) {
            $cb = $params;
            $params = [];
        }

        if (is_callable($options)) {
            $cb = $options;
            $options = [];
        }

        $options = array_merge($options, ['params' => $params]);
        $this->query('GET', $uri, $options, $cb);
    }

    public function post($uri, $data = [], $options = [], $cb = null)
    {
        if (is_callable($data)) {
            $cb = $data;
            $data = [];
        }

        if (is_callable($options)) {
            $cb = $options;
            $options = [];
        }

        $options = array_merge($options, ['body' => $data]);
        $this->query('POST', $uri, $options, $cb);
    }

    public function put($uri, $data = [], $options = [], $cb = null)
    {
        if (is_callable($data)) {
            $cb = $data;
            $data = [];
        }

        if (is_callable($options)) {
            $cb = $options;
            $options = [];
        }

        $options = array_merge($options, ['body' => $data]);
        $this->query('PUT', $uri, $options, $cb);
    }

    public function delete($uri, $params, $options = [], $cb = null)
    {
        if (is_callable($params)) {
            $cb = $params;
            $params = [];
        }

        if (is_callable($options)) {
            $cb = $options;
            $options = [];
        }

        $options = array_merge($options, ['params' => $params]);
        $this->query('DELETE', $uri, $options, $cb);
    }

    public function query($method, $uri, $options = [], $cb = null)
    {
        $method = strtoupper($method);

        $this->_client->setMethod($method);

        if (isset($options['body'])) {
            $this->_client->setData($options['body']);
        }

        if (isset($options['headers'])) {
            $this->_client->setHeaders($options['headers']);
        }


        if (isset($options['params'])) {
            $params = http_build_query($options['params']);
            $uri .= (strpos($uri, '?') === false ? '?' : '&') . $params;
        }

        $this->_client->execute($uri, $cb);
    }

    public function close()
    {
        $this->_client->close();
    }
}
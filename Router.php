<?php


namespace G;

use G\Exception\NotFound;

class Router implements IMiddleware
{

    private $_tree;

    use TMiddleware;


    public function __construct()
    {
        $this->_tree = new \SplObjectStorage();
    }

    public function map($methods, $path, callable ...$handlers)
    {
        $res = $this->find($path);
        if (!$res) {
            $pattern = new Pattern($path, true);
            $route = new Route();
        } else {
            list($pattern, $route) = $res;
        }

        $route->set($methods, $handlers);
        $this->_tree[$pattern] = $route;

        return $this;
    }

    public function use ($handler)
    {
        $args = func_get_args();
        $path = '';
        if (count($args) == 2) {
            $path = $args[0];
            $handler = $args[1];
        }

        if ($path == '') {
            $this->chain($handler);
        } else {
            if ($handler instanceof Application) {
                $handler = $handler->getRouter();
            }

            if (!($handler instanceof Router)) {
                throw new \InvalidArgumentException('设置路径后只能添加 Router 实例');
            }

            $res = $this->find($path);
            if (!$res) {
                $pattern = new Pattern($path);
            } else {
                $pattern = $res[0];
            }

            // 一条路径只能保持一个子 Router
            // 重复设置会被覆盖
            $this->_tree[$pattern] = $handler;
        }

        return $this;
    }

    public function find($path)
    {
        foreach ($this->_tree as $key) {
            if ($key->is($path)) {
                $value = $this->_tree[$key];
                return [$key, $value];
            }
        }

        return false;
    }

    public function match($path)
    {
        foreach ($this->_tree as $key) {
            if (($params = $key->match($path)) !== false) {
                $value = $this->_tree[$key];
                return [$key, $value, $params];
            }
        }

        return false;
    }

    /**
     * @param $c Context
     * @return \Generator
     * @throws NotFound
     */
    public function done($c)
    {
        $uri = $c->uri ?? $c->getUri();

        $ret = $this->match($uri);
        if (!$ret) {
            throw new NotFound();
        }

        list($pattern, $handler, $params) = $ret;
        if ($handler instanceof Route) {
            $c->params = $params;
            yield $handler;
        } else {
            $c->uri = str_replace($pattern->getPath(), '', $uri);
            yield $handler;
        }
    }
}

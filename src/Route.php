<?php


namespace G;

use G\Exception\NotFound;

class Route implements IMiddleware
{

    use TMiddleware;

    private $_methods = [];

    public function __construct($methods = null, $handlers = null)
    {
        if ($methods && $handlers) {
            $this->set($methods, $handlers);
        }
    }

    public function get($method)
    {
        $method = strtoupper($method);
        return $this->_methods[$method] ?? false;
    }

    public function set($methods, $handlers)
    {
        $allowMethods = HttpConst::listMethods();
        if ($methods == '*') {
            $methods = $allowMethods;
        }
        $middleware = new Middleware();
        foreach ($handlers as $handler) {
            if (is_callable($handler)) {
                $middleware->chain($handler);
            }
        }
        foreach ((array)$methods as $method) {
            $method = strtoupper($method);

            if (!in_array($method, $allowMethods)) {
                continue;
            }

            $this->_methods[$method] = $middleware;
        }
    }

    public function done($c)
    {
        $method = $c->getMethod();

        $handler = $this->get($method);

        if (!$handler) {
            throw new NotFound();
        }

        yield $handler;
    }
}
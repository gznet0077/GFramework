<?php


namespace G;


class Middleware implements IMiddleware
{
    use TMiddleware;

    public function __construct(array $handlers = [])
    {
        $this->_stack = $handlers;
    }
}
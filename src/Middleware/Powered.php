<?php


namespace G\Middleware;


use G\Context;

class Powered
{
    public function __invoke(Context $cxt)
    {
        $cxt->writeHeader('Server', 'DK server/1.0.21');
        $cxt->writeHeader('X-Powered-By', 'DK Engine');
        yield;
    }
}
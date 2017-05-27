<?php


namespace G\Middleware;

use G\Util\Sanitize;

class OverrideMethod
{
    public function __invoke($cxt)
    {
        $method = $cxt->header('x-http-method-override');
        if (!$method) {
            $method = $cxt->data('_METHOD_', '', Sanitize::STRING);
        }

        if ($method) {
            $cxt->withMethod(strtoupper($method));
        }

        yield;
    }
}
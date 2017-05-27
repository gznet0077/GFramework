<?php


namespace G;


interface IMiddleware
{
    public function handler($c);

    public function done($c);

    public function process($c = null, callable $done = null);

    public function chain($handler);
}
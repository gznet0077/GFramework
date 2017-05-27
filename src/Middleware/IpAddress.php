<?php


namespace G\Middleware;


use G\Context;
use G\Util\Sanitize;

class IpAddress
{
    private $_headers = [
        'x-forwarded-for',
        'x-forwarded',
        'x-cluster-client-ip',
        'client-ip',
    ];

    public function __invoke(Context $cxt)
    {
        $client_ip = '';
        foreach ($this->_headers as $header) {
            $ip = trim(current(explode(',', $cxt->header($header))));
            if ($ip) {
                $client_ip = $ip;
                break;
            }
        }

        if (!$client_ip) {
            $client_ip = $cxt->server('remote_addr', 'unknown');
        }

        $cxt->client_ip = $client_ip;

        yield;
    }
}
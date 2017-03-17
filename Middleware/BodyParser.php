<?php


namespace G\Middleware;


class BodyParser
{
    public function __invoke($cxt)
    {
        $media = $cxt->getMediaType();
        switch ($media) {
            case 'application/json':
                try {
                    $cxt->body = json_decode($cxt->rawBody(), true);
                } catch (\Exception $e) {
                    $cxt->body = [];
                }
                break;
            case 'application/x-www-form-urlencoded':
                try {
                    parse_str($cxt->rawBody(), $data);
                    $cxt->body = $data;
                } catch (\Exception $e) {
                    $cxt->body = [];
                }
                break;
            default:
                $cxt->body = [];
        }

        yield;
    }
}
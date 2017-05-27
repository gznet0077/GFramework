<?php


namespace G\Error;


use G\Context;
use G\Util\Sanitize;

class Php
{
    /**
     * @var Context
     */
    private $cxt;

    public function __construct($cxt)
    {
        $this->cxt = $cxt;
    }

    public function cli(\Exception $error)
    {
        $json = [
            'message' => 'Application Error',
        ];

        $json['error'] = [];

        do {
            $json['error'][] = [
                'type' => get_class($error),
                'code' => $error->getCode(),
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
                'trace' => explode("\n", $error->getTraceAsString()),
            ];
        } while ($error = $error->getPrevious());

        echo json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function json(\Exception $error)
    {
        $json = [
            'message' => 'Application Error',
        ];

        $isDebug = Sanitize::bool($this->cxt->settings['debug'] ?? false);
        if ($isDebug) {
            $json['error'] = [];

            do {
                $json['error'][] = [
                    'type' => get_class($error),
                    'code' => $error->getCode(),
                    'message' => $error->getMessage(),
                    'file' => $error->getFile(),
                    'line' => $error->getLine(),
                    'trace' => explode("\n", $error->getTraceAsString()),
                ];
            } while ($error = $error->getPrevious());
        }

        $this->cxt->writeStatus(500);
        $this->cxt->writeHeader('content-type', 'application/json');
        $this->cxt->end(json_encode($json, JSON_PRETTY_PRINT));
    }

    public function html(\Exception $error)
    {
        $title = "Application Error";

        $isDebug = Sanitize::bool($this->cxt->settings['debug'] ?? false);
        if ($isDebug) {
            $html = sprintf('<div><strong>Type:</strong> %s</div>', get_class($error));

            if (($code = $error->getCode())) {
                $html .= sprintf('<div><strong>Code:</strong> %s</div>', $code);
            }

            if (($message = $error->getMessage())) {
                $html .= sprintf('<div><strong>Message:</strong> %s</div>', htmlentities($message));
            }

            if (($file = $error->getFile())) {
                $html .= sprintf('<div><strong>File:</strong> %s</div>', $file);
            }

            if (($line = $error->getLine())) {
                $html .= sprintf('<div><strong>Line:</strong> %s</div>', $line);
            }

            if (($trace = $error->getTraceAsString())) {
                $html .= '<h2>Trace</h2>';
                $html .= sprintf('<pre>%s</pre>', htmlentities($trace));
            }

            while ($error = $error->getPrevious()) {
                $html .= '<h2>Previous error</h2>';
                $html .= $this->renderHtmlError($error);
            }
        } else {
            $html = '<p>A website error has occurred. Sorry for the temporary inconvenience.</p>';
        }

        $output = sprintf(
            "<html><head><meta http-equiv='Content-Type' content='test/html; charset=utf-8'>" .
            "<title>%s</title><style>body{margin:0;padding:30px;font:12px/1.5 Helvetica,Arial,Verdana," .
            "sans-serif;}h1{margin:0;font-size:48px;font-weight:normal;line-height:48px;}strong{" .
            "display:inline-block;width:65px;}</style></head><body><h1>%s</h1>%s</body></html>",
            $title,
            $title,
            $html
        );

        $this->cxt->writeStatus(500);
        $this->cxt->writeHeader('Content-Type', 'test/html');
        $this->cxt->end($output);
    }

    public function __invoke(\Exception $error)
    {
        $contentType = $this->cxt->getMediaType();
        switch ($contentType) {
            case 'application/json':
                $this->json($error);
                break;
            default:
                $this->html($error);
        }
    }
}
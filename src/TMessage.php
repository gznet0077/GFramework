<?php


namespace G;


trait TMessage
{
    public function msg($code, $args)
    {
        $msg = $this->_messages[$code] ?? $this->_messages['000'];
        $msg = vsprintf($msg, $args);
        return $msg;
    }
}
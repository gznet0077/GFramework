<?php


namespace G\Util;


class Sanitize
{
    const INT = 1;
    const STRING = 2;
    const FLOAT = 3;
    const BOOL = 4;
    const XSS = 5;
    const ARRAY = 6;
    const MONGOID = 7;
    const HTML = 8;

    static public function filter($data, $key = null, $type = self::STRING, $default = null)
    {
        if (is_null($key)) {
            return $data;
        }

        $keys = explode('.', $key);

        foreach ($keys as $key) {
            if (!isset($data[$key]) || $data[$key] === '') {
                return $default;
            }
            $data = $data[$key];
        }

        return self::sanitize($data, $type);
    }

    static public function sanitize($d, $type)
    {
        switch ($type) {
            case self::INT:
                return intval($d);
            case self::STRING:
                return strval($d);
            case self::FLOAT:
                return floatval($d);
            case self::BOOL:
                if (is_string($d) && in_array($d, ['true', 'on', '1'])) {
                    return true;
                } else if (is_string($d) && in_array($d, ['false', 'off', '0'])) {
                    return false;
                }
                return boolval($d);
            case self::XSS:
                return Misc::xss($d);
            case self::ARRAY:
                return (array)$d;
            case Sanitize::MONGOID:
                return Misc::realID($d);
            case self::HTML:
                return Misc::decodeHtml($d);
            default:
                return $d;
        }
    }

    static public function bool($d)
    {
        return self::sanitize($d, self::BOOL);
    }

    static public function xss($d)
    {
        return self::sanitize($d, self::XSS);
    }

    static public function html($d)
    {
        return self::sanitize($d, self::HTML);
    }
}
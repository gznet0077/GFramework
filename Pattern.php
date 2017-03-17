<?php


namespace G;


class Pattern
{
    private $_path;

    private $_regex;

    public function __construct(string $path, bool $end = false)
    {
        $this->_path = $this->_fixPath($path);
        $this->_regex = $this->_path2Reg($this->_path, $end);
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->_path;
    }

    /**
     * @return string
     */
    public function getRegex(): string
    {
        return $this->_regex;
    }

    public function match($path)
    {
        $path = $this->_fixPath($path);
        if (preg_match($this->_regex, $path, $matches)) {
            return array_filter($matches, function ($key) {
                return !is_numeric($key);
            }, ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    public function is($path)
    {
        $path = $this->_fixPath($path);
        return $this->_path == $path;
    }

    private function _fixPath($path)
    {
        $path = preg_replace('~/{2,}~', '/', $path);
        return '/' . trim($path, '/');
    }

    private function _path2Reg($path, $end = false)
    {
        $path = str_replace(['.', '[', ']', '*'], ['\\.', '(?:', ')?', '(.*)'], $path);

        $path = preg_replace_callback('~\{([^}]+)\}~', function ($match) {
            $parts = explode(':', $match[1]);
            if (!preg_match('~^[a-z]\w*$~i', $parts[0])) {
                throw new \InvalidArgumentException("路由设置占位符{$parts[0]}不正确, 必须符合php变量命名规范");
            }
            return "(?<{$parts[0]}>" . (isset($parts[1]) ? $parts[1] : '[^/]+') . ')';
        }, $path);

        $path = '^' . $path;

        if ($end) {
            $path = $path . '$';
        } else {
            $path = $path . '.*$';
        }

        return '~' . $path . '~';
    }
}

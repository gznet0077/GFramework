<?php


namespace G;

class Loader
{
    private $_namespace = '';
    private $_middlewareNamespace = '';

    private $_restPrefix = '';

    private $_mountReg = '~@mount\s+(.+)~';
    private $_httpMethodReg = '~@method\s+(.+)~';
    private $_routeReg = '~@route\s+(.+)~';
    private $_versionReg = '~@version\s+(.+)~';
    private $_middlewareReg = '~@middleware\s+(.+)~';
    private $_publicReg = '~@public\s+(true|false)~';
    private $_taskReg = '~@task\s+(true|false)~';
    private $_cronReg = '~@cron\s(.+)~';
    private $_timeoutReg = '~@timeout\s(.+)~';
    private $_triggerOnStartReg = '~@triggerOnStart\s+(true|false)~';
    private $_indexReg = '~@index\s(.+)~';
    private $_websocketReg = '~@websocket\s+(true|false)~';

    private $_resourceDir;

    private $_resources = [];
    private $_tasks = [];
    private $_crontab = [];

    private $_mod = Application::MOD_WEB;

    public function __construct($dir, $namespace = null, $middlewareNamespace = null, $prefix = null)
    {
        $this->_namespace = $namespace;
        $this->_middlewareNamespace = $middlewareNamespace;

        $this->_restPrefix = $prefix;

        if (!file_exists($dir)) {
            throw new \RuntimeException('Resource 目录不存在');
        }

        $this->_resourceDir = realpath($dir);
    }

    public function load($mod = Application::MOD_WEB)
    {
        $this->_mod = $mod;
        $this->_loadResource();
    }

    public function register(Application $app)
    {
        foreach ($this->_tasks as $name => $task) {
            $app->task($name, $task);
        }

        if ($this->_mod & Application::MOD_WEB) {
            foreach ($this->_resources as $mount => $resource) {
                $app->use($this->_restPrefix . $mount, $resource);
            }
        }

        if ($this->_mod & Application::MOD_CRON) {
            foreach ($this->_crontab as $cron) {
                list($rule, $action, $triggerOnStart, $timeout) = $cron;
                $app->cron($rule, $action, $triggerOnStart, $timeout);
            }
        }
    }

    private function _loadResource()
    {
        $iterator = new \DirectoryIterator($this->_resourceDir);

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->isDot()) {
                continue;
            } else if ($file->isFile() && $file->getExtension() == 'php') {
                $className = $this->_namespace
                    . \preg_replace('~/+~', '\\',
                        \substr($file->getRealPath(), strlen($this->_resourceDir), -4)
                    );

                $this->_resourceToRoute($className);
            }
        }
    }

    private function _resourceToRoute($className)
    {
        $ref = new \ReflectionClass($className);

        $doc = $ref->getDocComment();

        //如果类定义@public false，则跳过不处理
//        if (preg_match('~@public\s+false~', $doc)) {
//            return;
//        }

        if ($this->_mod & Application::MOD_WEB) {
            if (preg_match($this->_mountReg, $doc, $m)) {
                $mount = strtolower(trim($m[1]));
            } else {
                $mount = strtolower($ref->getShortName());
            }

            $mount = '/' . trim($mount, '/');

            $app = new Application();

            if (preg_match($this->_middlewareReg, $doc, $m)) {
                $info['middleware'] = new Middleware();
                $middleware = array_map(function ($item) {
                    return ucfirst(trim($item));
                }, explode(',', $m[1]));
                foreach ($middleware as $item) {
                    if ($this->_middlewareNamespace) {
                        $item = $this->_middlewareNamespace . '\\' . ucfirst($item);
                    }
                    try {
                        $handler = new $item;
                        $app->use($handler);
                    } catch (\Exception $e) {
                        continue;
                    }
                }
            }
        }

        $methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);

        //添加到路由
        $routesInfo = [];
        foreach ($methods as $method) {
            $routeInfo = $this->_parseMethod($method);
            if (!$routeInfo) {
                continue;
            }
            $routesInfo[] = $routeInfo;
        }

        usort($routesInfo, function ($a, $b) {
            return ($b['index'] - $a['index']);
        });

        foreach ($routesInfo as $routeInfo) {
            if ($routeInfo['task']) {
                $this->_tasks["{$ref->getShortName()}:{$routeInfo['method']}"] = "{$ref->getName()}::{$routeInfo['method']}";
            } else if ($routeInfo['cron'] && ($this->_mod & Application::MOD_CRON)) {
                $this->_crontab[] = [$routeInfo['cron'], "{$ref->getName()}::{$routeInfo['method']}", $routeInfo['triggerOnStart'], $routeInfo['timeout']];
            } else if ($routeInfo['websocket'] && $this->_mod & Application::MOD_WEB) {

            } else if ($routeInfo['public'] && $this->_mod & Application::MOD_WEB) {
                $routeInfo['middleware'][] = function ($c) use ($routeInfo) {
                    $obj = new $routeInfo['class']($c);
                    // 把 api 实例注入到 $context, 方便 task 使用
                    $c->api = $obj;
                    call_user_func_array([$obj, $routeInfo['method']], array_values($c->params));
                };
                $app->map($routeInfo['httpMethod'], $routeInfo['route'], new Middleware($routeInfo['middleware']));
            }
        }

        return $this->_resources[$mount] = $app;
    }

    private function _parseMethodDoc(\ReflectionMethod $method)
    {
        $info = [
            'class' => $method->class,
            'method' => $method->getName(),
            'httpMethod' => 'GET', //http method
            'route' => '', //自定义route
            'version' => '', //版本
            'middleware' => [], //是否使用中间件
            'public' => false, //是否可通过api访问
            'index' => 0, //路由顺序
            'task' => false,
            'cron' => '',
            'timeout' => null,
            'triggerOnStart' => false,
            'websocket' => false,
        ];

        $doc = $method->getDocComment();
        if (preg_match($this->_httpMethodReg, $doc, $m)) {
            $info['httpMethod'] = strtoupper(trim($m[1]));
        }

        if (preg_match($this->_routeReg, $doc, $m)) {
            $info['route'] = trim($m[1]);
        }

        if (preg_match($this->_versionReg, $doc, $m)) {
            $info['version'] = trim($m[1]);
        }

        if (preg_match($this->_middlewareReg, $doc, $m)) {
            $info['middleware'] = [];
            $middleware = array_map(function ($item) {
                return ucfirst(trim($item));
            }, explode(',', $m[1]));
            foreach ($middleware as $item) {
                if ($this->_middlewareNamespace) {
                    $item = $this->_middlewareNamespace . '\\' . ucfirst($item);
                }
                try {
                    $handler = new $item;
                } catch (\Exception $e) {
                    continue;
                }
                $info['middleware'][] = $handler;
            }
        }

        if (preg_match($this->_publicReg, $doc, $m)) {
            $public = strtolower(trim($m[1]));
            if ($public == 'true') {
                $info['public'] = true;
            }
        }

        if (preg_match($this->_taskReg, $doc, $m)) {
            $task = strtolower(trim($m[1]));
            if ($task == 'true' && $method->isStatic()) {
                $info['task'] = true;
            }
        }

        if (preg_match($this->_cronReg, $doc, $m)) {
            $cron = strtolower(trim($m[1]));
            if ($method->isStatic()) {
                // 因为在注释里不能使用 */2 这样的写样, 所以在 定义时 */2 改成 *|2 , 这里要替换回来
                $info['cron'] = str_replace('|', '/', $cron);
            }
        }

        if (preg_match($this->_timeoutReg, $doc, $m)) {
            $timeout = strtolower(trim($m[1]));
            if ($method->isStatic()) {
                $info['timeout'] = intval($timeout);
            }
        }

        if (preg_match($this->_triggerOnStartReg, $doc, $m)) {
            $triggerOnStart = strtolower(trim($m[1]));
            if ($triggerOnStart == 'true' && $method->isStatic()) {
                $info['triggerOnStart'] = true;
            }
        }

        if (preg_match($this->_indexReg, $doc, $m)) {
            $index = intval(trim($m[1]));
            $info['index'] = $index;
        }

        if (preg_match($this->_websocketReg, $doc, $m)) {
            $websocket = strtolower(trim($m[1]));
            if ($websocket == 'true') {
                $info['websocket'] = true;
            }
        }

        return $info;
    }

    private function _parseMethod(\ReflectionMethod $method)
    {
        $methodName = $method->getName();

        //魔术方法，不执行
        if (strpos($methodName, '__') === 0) {
            return;
        }

        $routeInfo = $this->_parseMethodDoc($method);

        if ($routeInfo['task']) {
            return $routeInfo;
        }

        if ($routeInfo['cron']) {
            return $routeInfo;
        }

        if (!$routeInfo['public']) {
            return;
        }

        $routeParts = [];

        if ($routeInfo['route']) {
            $parts = explode('/', trim($routeInfo['route'], '/'));
            foreach ($parts as $part) {
                $routeParts[] = $part;
            }
        } else {
            array_push($routeParts, $methodName);
            $parameters = $method->getParameters();
            foreach ($parameters as $parameter) {
                array_push($routeParts, '{' . $parameter->getName() . '}');
            }
        }

        $allowMethods = HttpConst::listMethods();
        if (!in_array($routeInfo['httpMethod'], $allowMethods)) {
            $routeInfo['httpMethod'] = 'GET';
        }

        if ($routeInfo['version']) {
            array_unshift($routeInfo, 'v' . $routeInfo['version']);
        }

        $route = '/' . implode('/', $routeParts);
        $routeInfo['route'] = $route;

        return $routeInfo;
    }
}
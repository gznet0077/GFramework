<?php


namespace G;

/**
 * 多次修改终于用生成器搞定,
 * 生成器每次调用都是返回一个新生成器,
 * 可以解决在 swoole 下的 stack 引用问题,
 * 在多线程里用迭代器等解决方法都会改变当前 stack 指针,
 * 而在多线程下 stack 指针是共享的,
 * 导致不能按预定的顺序执行 stack 里的 handler
 * yield 调用下个 中间件
 * 可以用 yield $nextHandler 动态添加中间件
 *
 * @package G
 */
trait TMiddleware
{
    /**
     * @var callable[]
     */
    protected $_stack = [];

    /**
     * 如果子类没有实现此方法, 直接运行chain里的第一个中间件
     * 定义这个方法, 主要是为了方便执行一些自身的操作
     *
     * @param $c
     * @return \Generator
     */
    public function handler($c)
    {
        yield;
    }

    /**
     * 如果子类没有实现此方法, 直接运行chain里的最后中间件
     * 定义这个方法, 主要是为了方便执行一些自身结束的操作
     *
     * @param $c
     * @return \Generator
     */
    public function done($c)
    {
        yield;
    }

    private function _process($i, $c, $done = null)
    {
        if (!isset($this->_stack[$i])) {
            if ($i != -1) {
                $gen = call_user_func([$this, 'done'], $c);
                if ($gen instanceof \Generator) {
                    $this->_loopGen($gen, -1, $c, $done);
                } else if (is_callable($done)) {
                    call_user_func($done, $c);
                }
            } else if (is_callable($done)) {
                call_user_func($done, $c);
            }
            return;
        }

        $handler = $this->_stack[$i];
        if ($handler instanceof IMiddleware) {
            $done = function ($c) use ($i, $done) {
                $this->_process(++$i, $c, $done);
            };
            call_user_func([$handler, 'process'], $c, $done->bindTo($this));
            return;
        }

        if (is_callable($handler)) {
            $gen = call_user_func($handler, $c);
            if (!($gen instanceof \Generator)) {
                if (is_callable($done)) {
                    call_user_func($done, $c);
                }
                return;
            }
        }

        $this->_loopGen($gen, ++$i, $c, $done);
    }

    /**
     * @param \Generator $gen
     * @param int $i
     * @param $c
     * @param callable|null $done
     */
    private function _loopGen(\Generator $gen, int $i, $c, callable $done = null)
    {
        while ($gen->valid()) {

            $val = $gen->current();

            // 动态添加链式操作
            if ($val instanceof IMiddleware) {
                $done1 = function ($c) use ($i, $done) {
                    $this->_process($i, $c, $done);
                };
                call_user_func([$val, 'process'], $c, $done1->bindTo($this));
            } // 执行函数
            else if (is_callable($val)) {
                $ret = call_user_func($val, $c);
                $gen->send($ret);
            } // 只执行第一个 yield 链式操作, 其余的直接执行而不会调用其他的 handler
            else if ($gen->key() == 0) {
                $this->_process($i, $c, $done);
            }

            $gen->next();
        }
    }

    public function process($c = null, callable $done = null)
    {
        if (is_null($c)) {
            $c = $this;
        }
        // 先调用定义的handler
        $gen = call_user_func([$this, 'handler'], $c);
        if ($gen instanceof \Generator) {
            $this->_loopGen($gen, 0, $c, $done);
        } else {
            $this->_process(0, $c, $done);
        }
    }

    /**
     * @param $handler callable|IMiddleware
     * @return IMiddleware
     */
    public function chain($handler)
    {
        if (!is_callable($handler) && !($handler instanceof IMiddleware)) {
            throw new \InvalidArgumentException('IMiddleware chain 参数只能是 callable 或 IMiddleware');
        }

        $this->_stack[] = $handler;
        return $this;
    }

    public function __invoke($c)
    {
        $this->handler($c);
    }
}
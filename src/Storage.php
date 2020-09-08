<?php
namespace Think;

class Storage
{

    /**
     * 操作句柄
     *
     * @var
     */
    protected static $handler;

    /**
     * 分布式文件操作
     *
     * @param string $type
     * @param array  $options
     */
    public static function connect($type = 'File', $options = [])
    {
        if (!self::$handler) {
            $class = 'Think\\Storage\\Handler\\' . ucwords($type);
            self::$handler = new $class($options);
        }
    }

    /**
     * 静态代理
     *
     * @param $method
     * @param $args
     * @return mixed
     */
    public static function __callstatic($method, $args)
    {
        self::connect();
        
        if (method_exists(self::$handler, $method)) {
            return call_user_func_array(array(
                self::$handler,
                $method
            ), $args);
        }
    }
}

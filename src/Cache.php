<?php
namespace Think;

use Think\Cache\AbstractCache;

class Cache
{

    /**
     * 缓存单例
     *
     * @var array
     */
    protected static $instance = [];

    /**
     * 取得缓存实例
     *
     * @param string $type
     * @param array  $options
     * @return AbstractCache
     * @throws Exception
     */
    public static function getInstance($type = 'Redis', $options = []): AbstractCache
    {
        $type = ucwords(strtolower($type));
        if (!isset(static::$instance[$type])) {
            $class = 'Think\\Cache\\Handler\\' . $type;
            if (!class_exists($class)) {
                E('Cache handle invalid: ' . $type);
            }
            static::$instance[$type] = new $class($options);
        }
        return static::$instance[$type];
    }
}
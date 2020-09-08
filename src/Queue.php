<?php
namespace Think;

use Think\Queue\AbstractQueue;

class Queue
{

    /**
     * 队列实例
     *
     * @var array
     */
    private static $instance = [];

    /**
     * 获取队列实例
     *
     * @param null  $type
     * @param array $options
     * @return AbstractQueue
     * @throws Exception
     */
    public static function getInstance($type = 'Redis', $options = []): AbstractQueue
    {
        $type = ucwords(strtolower($type));
        if (!isset(static::$instance[$type])) {
            $class = 'Think\\Queue\\Handler\\' . $type;
            if (!class_exists($class)) {
                E('Queue handle invalid: ' . $type);
            }
            static::$instance[$type] = new $class($options);
        }
        return static::$instance[$type];
    }
}
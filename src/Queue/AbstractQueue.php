<?php
namespace Think\Queue;

abstract class AbstractQueue
{

    /**
     * 入队
     *
     * @param string $queueName
     * @param mixed $data
     * @param bool   $right
     * @param string $expire
     */
    abstract function push(string $queueName, $data, $right = false, $expire = null);

    /**
     * 出队
     *
     * @param string $queueName
     */
    abstract function pop(string $queueName);

    /**
     * 队列长度
     *
     * @param string $queueName
     */
    abstract function len(string $queueName);
}
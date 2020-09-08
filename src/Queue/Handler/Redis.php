<?php
namespace Think\Queue\Handler;

use Think\Queue\AbstractQueue;
use Think\Manager\Redis as RedisManager;

class Redis extends AbstractQueue
{

    /**
     * Redis handle
     *
     * @var mixed|null
     */
    private $handler = null;

    /**
     * Redis constructor.
     *
     * @param $options
     * @throws \Think\Exception
     */
    public function __construct($options)
    {
        $this->handler = RedisManager::getInstance();
    }

    /**
     * 写入队列
     *
     * @param string $queueName
     * @param string $data
     * @param bool   $right
     * @param null   $expire
     * @return mixed
     */
    public function push(string $queueName, $data, $right = false, $expire = null)
    {
        // 使用通用的JSON格式
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }
        if ($right) {
            $res = $this->handler->rPush($queueName, $data);
        } else {
            $res = $this->handler->lPush($queueName, $data);
        }
        is_numeric($expire) && $this->handler->expire($queueName, $expire);
        return $res;
    }

    /**
     * 弹出队列
     *
     * @param string $queueName
     * @return mixed
     */
    public function pop(string $queueName)
    {
        $data = $this->handler->rPop($queueName);
        $jsonData = json_decode($data, true);
        return is_null($jsonData) || false === $jsonData ? $data : $jsonData;
    }

    /**
     * 队列长度
     *
     * @param string $queueName
     * @return mixed
     */
    public function len(string $queueName)
    {
        return $this->handler->lLen($queueName);
    }

    /**
     * 魔术方法
     *
     * @param $method
     * @param $args
     * @return mixed|void
     * @throws \Think\Exception
     */
    public function __call($method, $args)
    {
        // 调用缓存类型自己的方法
        if (method_exists($this->handler, $method)) {
            return call_user_func_array([
                $this->handler,
                $method
            ], $args);
        } else {
            E('Method not exist: ' . __CLASS__ . ':' . $method);
            return;
        }
    }
}
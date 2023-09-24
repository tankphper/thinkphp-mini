<?php
namespace Think\Cache\Handler;

use Think\Cache\AbstractCache;
use Think\Manager\Redis as RedisManager;

class Redis extends AbstractCache
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
     * @param array $options
     * @throws \Think\Exception
     */
    public function __construct($options = [])
    {
        $this->options = array_merge($this->options, $options);
        $this->handler = RedisManager::getInstance();
    }

    /**
     * 读取缓存
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        $key = $this->options['prefix'] . $key;
        $value = $this->handler->get($key);
        $json = json_decode($value, true);
        return ($json === null) ? $value : $json;
    }

    /**
     * 写入缓存
     *
     * @param string $key
     * @param        $value
     * @param null   $expire
     * @return mixed
     */
    public function set(string $key, $value, $expire = null)
    {
        is_null($expire) && $expire = $this->options['expire'];
        $key = $this->options['prefix'] . $key;
        $value = (is_object($value) || is_array($value)) ? json_encode($value) : $value;
        if ($expire > 0) {
            $result = $this->handler->setex($key, $expire, $value);
        } else {
            $result = $this->handler->set($key, $value);
        }
        return $result;
    }

    /**
     * 删除缓存
     *
     * @param string $key
     * @return mixed
     */
    public function del(string $key)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->del($key);
    }

    /**
     * 原子自增
     *
     * @param string $key
     * @param float  $step
     * @param null   $expire
     * @return mixed
     */
    public function incr(string $key, $step, $expire = null)
    {
        $key = $this->options['prefix'] . $key;
        $res = $this->handler->incrByFloat($key, $step);
        is_numeric($expire) && $this->handler->expire($key, $expire);
        return $res;
    }

    /**
     * 魔术方法
     *
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * 魔术方法
     *
     * @param $key
     * @return mixed
     */
    public function __set($key, $value)
    {
        return $this->set($key, $value);
    }

    /**
     * 魔术方法
     *
     * @param $key
     * @return mixed
     */
    public function __unset($key)
    {
        $this->del($key);
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

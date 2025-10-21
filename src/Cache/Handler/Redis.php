<?php
namespace Think\Cache\Handler;

use RedisException;
use Think\Cache\AbstractCache;
use Think\Exception;
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
     * @throws Exception
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
        $this->handler = RedisManager::getInstance();
    }

    /**
     * 读取缓存
     *
     * @param string $key
     * @return mixed
     * @throws RedisException
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
     * @return bool|\Redis
     * @throws RedisException
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
     * @return false|int|\Redis
     * @throws RedisException
     */
    public function del(string $key)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->del($key);
    }

    /**
     * 是否存在
     *
     * @param string $key
     * @return bool|int|\Redis
     */
    public function exists(string $key)
    {
        $key = $this->options['prefix'] . $key;
        return $this->handler->exists($key);
    }

    /**
     * 原子自增
     *
     * @param string $key
     * @param float  $step
     * @param null   $expire
     * @return false|float|\Redis
     * @throws RedisException
     */
    public function incr(string $key, float $step, $expire = null)
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
     * @throws RedisException
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * 魔术方法
     *
     * @param $key
     * @param $value
     * @return bool|\Redis
     * @throws RedisException
     */
    public function __set($key, $value)
    {
        return $this->set($key, $value);
    }

    /**
     * 魔术方法
     *
     * @param $key
     * @return void
     * @throws RedisException
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
     * @throws Exception
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
        }
    }
}

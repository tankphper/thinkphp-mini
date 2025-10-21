<?php
namespace Think\Cache;

abstract class AbstractCache
{

    /**
     * 缓存配置
     *
     * @var array
     */
    protected $options = [
        'prefix' => '',
        'expire' => 600
    ];

    /**
     * 获取缓存
     *
     * @param string $key
     * @return mixed
     */
    abstract function get(string $key);

    /**
     * 设置缓存
     *
     * @param string $key
     * @param        $value
     * @param null   $expire
     * @return mixed
     */
    abstract function set(string $key, $value, $expire = null);

    /**
     * 删除缓存
     *
     * @param string $key
     * @return mixed
     */
    abstract function del(string $key);

    /**
     * 是否存在
     *
     * @param string $key
     * @return mixed
     */
    abstract function exists(string $key);

    /**
     * 原子自增
     *
     * @param string $key
     * @param float  $step
     * @param null   $expire
     */
    abstract function incr(string $key, float $step, $expire = null);
}

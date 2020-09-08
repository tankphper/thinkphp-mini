<?php
namespace Think\Manager;

/**
 * Redis管理
 *
 * @Author  Tank
 * @Version 2018/11/27
 */
class Redis
{
    /**
     * Redis实例
     *
     * @var array
     */
    private static $instance = [];

    /**
     * Redis实例配置
     *
     * @var array
     */
    private static $configs = [];

    /**
     * 获取实例
     *
     * @param array $options
     * @return mixed
     * @throws \Think\Exception
     */
    public static function getInstance($options = [])
    {
        if (!extension_loaded('redis')) {
            E('Redis extension not supported');
        }
        $options = array_merge([
            'host'       => C('REDIS_HOST') ?: '127.0.0.1',
            'port'       => C('REDIS_PORT') ?: 6379,
            'pass'       => C('REDIS_PWD') ?: false,
            'select'     => C('REDIS_SELECT') ?: 0,
            'timeout'    => C('REDIS_TIMEOUT') ?: false,
            'persistent' => false
        ], $options);
        $name = $options['host'] . PATH_SEPARATOR . $options['port'];
        if (!isset(self::$instance[$name])) {
            $redis = new \Redis();
            $func = $options['persistent'] ? 'pconnect' : 'connect';
            $options['timeout'] ? $redis->$func($options['host'], $options['port'], $options['timeout']) : $redis->$func($options['host'], $options['port']);
            // 密码
            $options['pass'] && $redis->auth($options['pass']);
            // 库
            $options['select'] && $redis->select($options['select']);

            self::$instance[$name] = $redis;
            self::$configs[$name] = $options;
        }
        return self::$instance[$name];
    }
}
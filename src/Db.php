<?php
namespace Think;

class Db
{

    /**
     * 数据库连接实例
     *
     * @var array
     */
    private static $instance = [];

    /**
     * 当前数据库连接实例
     *
     * @var null
     */
    private static $_instance = null;


    /**
     * 取得数据库类实例
     *
     * @param array $config
     * @return mixed|null
     * @throws Exception
     */
    public static function getInstance($config = [])
    {
        $md5 = md5(serialize($config));
        if (!isset(self::$instance[$md5])) {
            // 解析连接参数 支持数组和字符串
            $options = self::parseConfig($config);
            $class = 'Think\\Db\\Handle\\' . ucwords(strtolower($options['type']));
            if (class_exists($class)) {
                self::$instance[$md5] = new $class($options);
            } else {
                E('Db handle not found: ' . $class);
            }
        }
        self::$_instance = self::$instance[$md5];
        return self::$_instance;
    }

    /**
     * 数据库连接参数解析
     *
     * @param $config
     * @return array
     */
    private static function parseConfig($config)
    {
        if (!empty($config)) {
            if (is_string($config)) {
                return self::parseDsn($config);
            }
            $config = array_change_key_case($config, CASE_LOWER);
            $config = [
                'type'        => $config['db_type'],
                'username'    => $config['db_user'],
                'password'    => $config['db_pwd'],
                'hostname'    => $config['db_host'],
                'hostport'    => $config['db_port'],
                'database'    => $config['db_name'],
                'charset'     => $config['db_charset'] ?? 'utf8',
                'debug'       => $config['db_debug'] ?? C('APP_DEBUG')
            ];
        } else {
            $config = [
                'type'        => C('DB_TYPE'),
                'username'    => C('DB_USER'),
                'password'    => C('DB_PWD'),
                'hostname'    => C('DB_HOST'),
                'hostport'    => C('DB_PORT'),
                'database'    => C('DB_NAME'),
                'charset'     => C('DB_CHARSET'),
                'debug'       => C('DB_DEBUG', null, C('APP_DEBUG'))
            ];
        }
        return $config;
    }

    /**
     * DSN 解析
     * 格式： mysql://username:passwd@localhost:3306/DbName?param1=val1&param2=val2#utf8
     *
     * @param $dsnStr
     * @return array|bool
     */
    private static function parseDsn($dsnStr)
    {
        if (empty($dsnStr)) {
            return false;
        }
        $info = parse_url($dsnStr);
        if (!$info) {
            return false;
        }
        $dsn = [
            'type'     => $info['scheme'],
            'username' => $info['user'] ?? '',
            'password' => $info['pass'] ?? '',
            'hostname' => $info['host'] ?? '',
            'hostport' => $info['port'] ?? '',
            'database' => isset($info['path']) ? substr($info['path'], 1) : '',
            'charset'  => $info['fragment'] ?? 'utf8'
        ];
        if (isset($info['query'])) {
            parse_str($info['query'], $dsn['params']);
        } else {
            $dsn['params'] = [];
        }
        return $dsn;
    }

    /**
     * 调用驱动类的方法
     *
     * @param $method
     * @param $params
     * @return mixed
     */
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([
            self::$_instance,
            $method
        ], $params);
    }
}

<?php
namespace Think;

class Event
{

    /**
     * 所有标签
     *
     * @var array
     */
    private static $events = [];

    /**
     * 动态添加事件行为到某个事件
     *
     * @param $eventName
     * @param $className
     */
    public static function add($eventName, $className)
    {
        if (!isset(self::$events[$eventName])) {
            self::$events[$eventName] = [];
        }
        if (is_array($className)) {
            self::$events[$eventName] = array_merge(self::$events[$eventName], $className);
        } else {
            self::$events[$eventName][] = $className;
        }
    }

    /**
     * 批量注册插件
     *
     * @param      $events
     * @param bool $recursive
     */
    public static function register($events, $recursive = true)
    {
        if (!$recursive) {
            // 覆盖导入
            self::$events = array_merge(self::$events, $events);
        } else {
            // 合并导入
            foreach ($events as $eventName => $value) {
                if (!isset(self::$events[$eventName])) {
                    self::$events[$eventName] = [];
                }
                self::$events[$eventName] = array_merge(self::$events[$eventName], $value);
            }
        }
    }

    /**
     * 获取事件信息
     *
     * @param string $eventName
     * @return array|mixed
     */
    public static function get($eventName = '')
    {
        if (empty($eventName)) {
            return self::$events;
        } else {
            return self::$events[$eventName];
        }
    }

    /**
     * 触发某个事件
     *
     * @param      $eventName
     * @param null $params
     */
    public static function trigger($eventName, &$params = null)
    {
        if (isset(self::$events[$eventName])) {
            foreach (self::$events[$eventName] as $eventClass) {
                $result = self::exec($eventClass, $params);
                if (false === $result) {
                    return;
                }
            }
        }
        return;
    }

    /**
     * 执行某个事件行为
     *
     * @param      $class
     * @param null $params
     * @return mixed
     */
    public static function exec($class, &$params = null)
    {
        $event = new $class();
        return $event->run($params);
    }
}

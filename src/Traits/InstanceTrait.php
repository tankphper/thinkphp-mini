<?php
namespace Think\Traits;

trait InstanceTrait
{

    /**
     * 模型实例
     *
     * @return static
     * @throws \Think\Exception
     */
    public static function instance()
    {
        static $_instance = [];
        $className = get_called_class();
        if (!isset($_instance[$className])) {
            if (!class_exists($className)) {
                E('Class not found: ' . $className);
            }
            $_instance[$className] = new static();
        }
        return $_instance[$className];
    }
}
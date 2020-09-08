<?php
namespace Think\Event;

interface EventInterface
{

    /**
     * 事件入口
     *
     * @param $params
     * @return mixed
     */
    public function run(&$params);
}
<?php
namespace Think;

use Think\Reporter\ErrorReporter;
use Think\Traits\ErrorTrait;

abstract class Controller implements ErrorReporter
{

    use ErrorTrait;

    /**
     * 视图实例对象
     *
     * @var null|View
     */
    protected $view = null;

    /**
     * 控制器参数
     *
     * @var array
     */
    protected $config = [];

    /**
     * Controller constructor.
     */
    public function __construct()
    {
        // 实例化视图类
        $this->view = new View;
    }

    /**
     * 模板显示 调用内置的模板引擎显示方法
     *
     * @param string $templateFile
     * @param string $charset
     * @param string $contentType
     * @param string $content
     * @throws Exception
     */
    protected function display($templateFile = '', $charset = 'utf-8', $contentType = 'text/html', $content = '')
    {
        $this->view->display($templateFile, $charset, $contentType, $content);
    }

    /**
     * 输出内容文本可以包括 Html 并支持内容解析
     *
     * @param        $content
     * @param string $charset
     * @param string $contentType
     * @throws Exception
     */
    protected function show($content, $charset = 'utf-8', $contentType = 'text/html')
    {
        $this->view->display('', $charset, $contentType, $content);
    }

    /**
     * 获取输出页面内容
     * 调用内置的模板引擎fetch方法
     *
     * @param string $templateFile
     * @param string $content
     * @return false|string
     * @throws Exception
     */
    protected function fetch($templateFile = '', $content = '')
    {
        return $this->view->fetch($templateFile, $content);
    }

    /**
     * 模板变量赋值
     *
     * @param        $name
     * @param string $value
     * @return $this
     */
    protected function assign($name, $value = '')
    {
        $this->view->assign($name, $value);
        return $this;
    }

    /**
     * 魔术方法
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        $this->assign($name, $value);
    }

    /**
     * 取得模板显示变量的值
     *
     * @param string $name
     * @return mixed
     */
    public function get($name = '')
    {
        return $this->view->get($name);
    }

    /**
     * 魔术方法
     *
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * 检测模板变量的值
     *
     * @param $name
     * @return mixed
     */
    public function __isset($name)
    {
        return $this->get($name);
    }

    /**
     * 魔术方法
     *
     * @param $method
     * @param $args
     * @throws Exception
     */
    public function __call($method, $args)
    {
        if (method_exists($this, '_empty')) {
            // 如果定义了 _empty 操作 则调用
            $this->_empty($method, $args);
        } elseif (is_file($this->view->parseTemplate())) {
            // 检查是否存在默认模版 如果有直接输出模版
            $this->display();
        } else {
            E('Action not exists: ' . $method);
        }
    }

    /**
     * Json 格式返回数据到客户端
     *
     * @param array $data
     * @param int   $option
     */
    protected function jsonReturn(array $data, $option = 0)
    {
        header('Content-Type:application/json; charset=utf-8');
        exit(json_encode($data, $option));
    }

    /**
     * Action跳转(URL重定向） 支持指定模块和延时跳转
     *
     * @param        $url
     * @param array  $params
     * @param int    $delay
     * @param string $msg
     */
    protected function redirect($url, $params = [], $delay = 0, $msg = '')
    {
        $url = U($url, $params);
        redirect($url, $delay, $msg);
    }
}

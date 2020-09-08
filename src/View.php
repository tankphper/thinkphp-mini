<?php
namespace Think;

class View
{

    /**
     * 模板变量
     *
     * @var array
     */
    protected $tVar = [];

    /**
     * 模板变量赋值
     *
     * @param        $name
     * @param string $value
     */
    public function assign($name, $value = '')
    {
        if (is_array($name)) {
            $this->tVar = array_merge($this->tVar, $name);
        } else {
            $this->tVar[$name] = $value;
        }
    }

    /**
     * 取得模板变量的值
     *
     * @param string $name
     * @return array|bool|mixed
     */
    public function get($name = '')
    {
        if ('' === $name) {
            return $this->tVar;
        }
        return isset($this->tVar[$name]) ? $this->tVar[$name] : false;
    }

    /**
     * 页面输出
     *
     * @param string $templateFile
     * @param string $charset
     * @param string $contentType
     * @param string $content
     * @throws Exception
     */
    public function display($templateFile = '', $charset = 'utf-8', $contentType = 'text/html', $content = '')
    {
        $content = $this->fetch($templateFile, $content);
        // 渲染模板
        $this->render($content, $charset, $contentType);
    }

    /**
     * 输出内容文本
     *
     * @param string $content
     * @param string $charset
     * @param string $contentType
     */
    private function render(string $content, $charset = 'utf-8', $contentType = 'text/html')
    {
        if (!headers_sent()) {
            header('Content-Type:' . $contentType . '; charset=' . $charset);
            header('Cache-control: private');
            header('X-Powered-By: Sunday');
        }
        // 输出模板文件
        echo $content;
    }

    /**
     * 解析和获取模板内容
     *
     * @param string $templateFile
     * @param string $content
     * @return false|string
     * @throws Exception
     */
    public function fetch($templateFile = '', $content = '')
    {
        if (empty($content)) {
            $templateFile = $this->parseTemplate($templateFile);
            // 模板文件不存在直接返回
            if (!is_file($templateFile)) {
                E('Template not exist: ' . $templateFile);
            }
        } else {
            defined('VIEW_PATH') or define('VIEW_PATH', $this->getViewPath());
        }
        // 页面内容
        ob_start();
        ob_implicit_flush(0);
        // 视图解析标签
        $params = [
            'var'     => $this->tVar,
            'file'    => $templateFile,
            'content' => $content
        ];
        Event::trigger('view_parse', $params);
        $content = ob_get_clean();
        return $content;
    }

    /**
     * 自动定位模板文件
     *
     * @param string $template
     * @return mixed|string
     */
    public function parseTemplate($template = '')
    {
        if (is_file($template)) {
            return $template;
        }
        $template = str_replace(':', '/', $template);
        // 获取当前模块
        $module = MODULE_NAME;
        // 跨模块调用模版文件
        if (strpos($template, '@')) {
            list ($module, $template) = explode('@', $template);
        }
        // 获取当前模版路径
        defined('VIEW_PATH') or define('VIEW_PATH', $this->getViewPath($module));
        // 分析模板文件规则
        if ('' == $template) {
            $template = CONTROLLER_NAME . '/' . ACTION_NAME;
        } elseif (false === strpos($template, '/')) {
            $template = CONTROLLER_NAME . '/' . $template;
        }
        $file = VIEW_PATH . $template . C('TMPL_FILE_SUFFIX');
        return $file;
    }

    /**
     * 获取当前的视图路径
     *
     * @param mixed|string $module
     * @return string
     */
    protected function getViewPath($module = MODULE_NAME)
    {
        $tmplPath = APP_PATH . '/' . $module . '/' . C('DEFAULT_V_LAYER') . '/';
        return $tmplPath;
    }
}
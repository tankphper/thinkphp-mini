<?php
namespace Think\Event;

class DetectLang implements EventInterface
{

    /**
     * 执行入口
     *
     * @param $params
     * @return mixed|void
     */
    public function run(&$params)
    {
        $this->checkLanguage();
    }

    /**
     * 语言检查
     * 检查浏览器支持语言，并自动加载语言包
     *
     * @access private
     * @return void
     */
    private function checkLanguage()
    {
        $appLang = C('DEFAULT_LANG', null, 'zh-cn');
        $langVar = C('LANG_VAR', null, 'lang');
        $langList = C('LANG_LIST', null, 'zh-cn');
        $langDetect = C('LANG_DETECT', null, true);
        // 启用了语言包功能
        // 根据是否启用自动侦测设置获取语言选择
        if ($langDetect) {
            if (isset($_GET[$langVar])) {
                $appLang = $_GET[$langVar];
            } elseif (cookie('language')) {
                // 获取上次用户的选择
                $appLang = cookie('language');
            } elseif (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                // 自动侦测浏览器语言
                preg_match('/^([a-z\d\-]+)/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches);
                $appLang = strtolower($matches[1]);
            }
            if (false === stripos($langList, $appLang)) {
                // 非法语言参数
                $appLang = C('DEFAULT_LANG');
            }
            cookie('language', $appLang, 3600);
        }
        // 定义当前语言
        define('APP_LANG', strtolower($appLang));
        // 读取公共语言包
        $file = APP_PATH . '/' . COMMON_MODULE . '/Lang/' . APP_LANG . '.php';
        if (is_file($file)) {
            L(include $file);
        }
        // 读取模块语言包
        $file = MODULE_PATH . 'Lang/' . APP_LANG . '.php';
        if (is_file($file)) {
            L(include $file);
        }
    }
}
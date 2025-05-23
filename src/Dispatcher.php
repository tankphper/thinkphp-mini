<?php
namespace Think;

/**
 * URL解析、路由和调度
 *
 * @Author  Tank
 * @Version 2018/11/22
 */
class Dispatcher
{

    /**
     * URL映射到控制器
     *
     * @throws Exception
     */
    public static function dispatch()
    {
        $temp = explode('.php', $_SERVER['PHP_SELF']);
        define('__PHP__', rtrim(str_replace($_SERVER['HTTP_HOST'], '', $temp[0] . '.php'), '/'));
        $url = strip_tags(dirname(__PHP__));
        ($url == '/' || $url == '\\') && $url = '';
        define('__APP__', $url);
        define('__ROOT__', $url);

        // 获取PATH_INFO
        $varPath = 's';
        $pathInfo = $_GET[$varPath] ?? '/';
        $_SERVER['PATH_INFO'] = $pathInfo == '/' ? '/' . C('DEFAULT_MODULE') : $pathInfo;
        unset($_GET[$varPath]);
        // URL后缀
        define('__EXT__', strtolower(pathinfo($_SERVER['PATH_INFO'], PATHINFO_EXTENSION)));
        // 检查禁止访问的URL后缀
        $denySuffix = C('URL_DENY_SUFFIX');
        if ($denySuffix && in_array(__EXT__, explode('|', strtolower(str_replace('.', '', $denySuffix))))) {
            send_http_status(404);
            exit;
        }
        define('__INFO__', trim($_SERVER['PATH_INFO'], '/'));
        // 去除URL后缀
        $_SERVER['PATH_INFO'] = preg_replace('/\.html$/i', '', __INFO__);
        // 解析模块
        $paths = explode('/', $_SERVER['PATH_INFO'], 2);
        $moduleName = ucfirst(strip_tags($paths[0]));
        $modulePath = APP_PATH . '/' . $moduleName;
        //$_GET['m'] = $moduleName;
        // 检测模块
        define('COMMON_MODULE', 'Common');
        if ($moduleName == 'Favicon' || $moduleName == COMMON_MODULE) {
            send_http_status(404);
            exit();
        } elseif (!is_dir($modulePath)) {
            $_GET['r'] = $moduleName;
            unset($paths);
            $moduleName = C('DEFAULT_MODULE');
        }
        define('MODULE_NAME', $moduleName);
        define('MODULE_PATH', APP_PATH . '/' . MODULE_NAME);
        // 模块配置
        is_file(APP_PATH . '/' . COMMON_MODULE . '/Conf/config.php') && C(load_config(APP_PATH . '/' . COMMON_MODULE . '/Conf/config.php'));
        is_file(MODULE_PATH . '/Conf/config.php') && C(load_config(MODULE_PATH . '/Conf/config.php'));
        // 环境配置
        is_file(MODULE_PATH . '/Conf/config.' . C('APP_ENV') . '.php') && C(load_config(MODULE_PATH . '/Conf/config.' . C('APP_ENV') . '.php'));
        // 模块事件
        is_file(APP_PATH . '/' . COMMON_MODULE . '/Conf/event.php') && Event::register(include APP_PATH . '/' . COMMON_MODULE . '/Conf/event.php');
        is_file(MODULE_PATH . '/Conf/event.php') && Event::register(include MODULE_PATH . '/Conf/event.php');
        // 模版缓存
        C('TMPL_CACHE_PATH', CACHE_PATH . '/' . MODULE_NAME . '/');
        // 模块地址
        define('__MODULE__', __APP__ . '/' . strtolower(MODULE_NAME));


        // 找控制器
        $pathInfo = $paths[1] ?? '';
        // 模块路由
        if (C('URL_ROUTER_ON') && $routerMap = C('URL_ROUTER_MAP')) {
            $pathInfo = $routerMap[$pathInfo] ?? $pathInfo;
        }
        $paths = explode('/', trim($pathInfo, '/'));
        $controllerName = strip_tags(parse_name(array_shift($paths), 1)) ?: C('DEFAULT_CONTROLLER');
        //$_GET['c'] = $controllerName;
        define('CONTROLLER_NAME', $controllerName);
        define('__CONTROLLER__', __MODULE__ . '/' . parse_name($controllerName));


        // 获取操作
        $actionName = array_shift($paths) ?: C('DEFAULT_ACTION');
        //$_GET['a'] = $actionName;
        define('ACTION_NAME', $actionName);
        define('__ACTION__', __CONTROLLER__ . '/' . ACTION_NAME . '.html');
        // 解析剩余的URL参数
        $var = [];
        preg_replace_callback('/(\w+)\/([^\/]+)/', function ($match) use (&$var) {
            $var[$match[1]] = strip_tags($match[2]);
        }, implode('/', $paths));
        $_GET = array_merge($var, $_GET);
        // 取消AJAX的随机数
        unset($_GET['_']);
        // 为了取 SESSION_ID，加了COOKIE
        $_REQUEST = array_merge($_POST, $_GET, $_COOKIE);
    }
}

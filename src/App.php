<?php
namespace Think;

use Think\Logger\Logger;

class App
{

    /**
     * 日志实例
     *
     * @var array
     */
    protected static $logger = [];

    /**
     * 运行应用实例
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function run()
    {
        // 初始化
        static::init();
        // 加载配置
        static::loadConfig();
        // 注册事件
        static::registerEvent();
        // URL调度分发
        Dispatcher::dispatch();
        // 执行事件
        Event::trigger('app_begin');
        // 执行控制器
        static::exec();
    }

    /**
     * 应用程序初始化
     */
    public static function init()
    {
        // 错误级别
        ini_set('display_errors', 'On');
        ini_set('error_reporting', 'E_ALL');
        // 致命错误
        register_shutdown_function('Think\App::handleFatal');
        // 应用错误
        set_error_handler('Think\App::handleError');
        // 异常处理
        set_exception_handler('Think\App::handleException');
        // 设置时区
        date_default_timezone_set('PRC');
        // 请求常量
        define('REQUEST_ID', uniqid('R-'));
        define('REQUEST_URI', $_SERVER['REQUEST_URI'] ?? '/');
        define('REQUEST_TIME', $_SERVER['REQUEST_TIME'] ?? time());
        define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD'] ?? 'NONE');
        define('IS_GET', REQUEST_METHOD == 'GET' ? true : false);
        define('IS_POST', REQUEST_METHOD == 'POST' ? true : false);
        define('IS_PUT', REQUEST_METHOD == 'PUT' ? true : false);
        define('IS_DELETE', REQUEST_METHOD == 'DELETE' ? true : false);
        define('IS_OPTIONS', REQUEST_METHOD == 'OPTIONS' ? true : false);
        define('IS_AJAX', strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') == 'xmlhttprequest') ? true : false;
    }

    /**
     * 执行应用程序
     *
     * @throws Exception
     * @throws \ReflectionException
     */
    public static function exec()
    {
        if (!preg_match('/^[A-Za-z](\/|\w)*$/', CONTROLLER_NAME)) {
            E('Controller is illegal: ' . CONTROLLER_NAME);
        } else {
            $controller = controller(CONTROLLER_NAME);
            if (!$controller) {
                E('Controller not exist: ' . MODULE_NAME . '/' . CONTROLLER_NAME);
            }
        }
        $action = ACTION_NAME;
        if (!preg_match('/^[A-Za-z](\w)*$/', $action)) {
            E('Action is illegal: ' . $action);
        }
        try {
            // 执行当前操作
            $method = new \ReflectionMethod($controller, $action);
            if ($method->isPublic() && !$method->isStatic()) {
                $method->invoke($controller);
            } else {
                throw new \ReflectionException('Method not public or static');
            }
        } catch (\ReflectionException $e) {
            // __call 处理错误方法
            $method = new \ReflectionMethod($controller, '__call');
            $method->invokeArgs($controller, [
                $action,
                ''
            ]);
        }
        return;
    }

    /**
     * 加载配置文件
     *
     * @throws Exception
     */
    public static function loadConfig()
    {
        $configDir = dirname(__DIR__);
        C(load_config($configDir . '/config/web.php'));
    }

    /**
     * 注册事件
     */
    public static function registerEvent()
    {
        $configDir = dirname(__DIR__);
        Event::register(include $configDir . '/config/event.php');
    }

    /**
     * 获取日志
     *
     * @param string $logFileName
     * @return Logger
     */
    public static function getLogger(string $logFileName = 'app'): Logger
    {
        if (!isset(static::$logger[$logFileName])) {
            static::$logger[$logFileName] = new Logger(['logFileName' => $logFileName]);
        }
        return static::$logger[$logFileName];
    }

    /**
     * 信息日志
     *
     * @param        $info
     * @param string $logFileName
     * @param array  $context
     * @return bool
     */
    public static function info($info, string $logFileName = '', array $context = [])
    {
        if (is_array($info) || is_object($info)) {
            $info = json_encode($info, JSON_UNESCAPED_UNICODE);
        }
        return static::getLogger($logFileName)->info($info, $context);
    }

    /**
     * 调试日志
     *
     * @param        $info
     * @param string $logFileName
     * @param array  $context
     * @return bool
     */
    public static function debug($info, string $logFileName = '', array $context = [])
    {
        if (is_array($info) || is_object($info)) {
            $info = json_encode($info, JSON_UNESCAPED_UNICODE);
        }
        return static::getLogger($logFileName)->debug($info, $context);
    }

    /**
     * 通知日志
     *
     * @param        $info
     * @param string $logFileName
     * @param array  $context
     * @return bool
     */
    public static function notice($info, string $logFileName = '', array $context = [])
    {
        if (is_array($info) || is_object($info)) {
            $info = json_encode($info, JSON_UNESCAPED_UNICODE);
        }
        return static::getLogger($logFileName)->notice($info, $context);
    }

    /**
     * 错误日志
     *
     * @param        $info
     * @param string $logFileName
     * @param array  $context
     * @return bool
     */
    public static function error($info, string $logFileName = '', array $context = [])
    {
        if (is_array($info) || is_object($info)) {
            $info = json_encode($info, JSON_UNESCAPED_UNICODE);
        }
        return static::getLogger($logFileName)->error($info, $context);
    }

    /**
     * 自定义异常处理
     *
     * @param \Throwable $e
     */
    public static function handleException(\Throwable $e)
    {
        $error = [];
        $error['message'] = $e->getMessage();
        $trace = $e->getTrace();
        $function = $trace[0]['function'] ?? '';
        if ('E' == $function) {
            $error['file'] = $trace[0]['file'] ?? '';
            $error['line'] = $trace[0]['line'] ?? '';
        } else {
            $error['file'] = $e->getFile();
            $error['line'] = $e->getLine();
        }
        $error['trace'] = $e->getTraceAsString();
        self::halt($error);
    }

    /**
     * 自定义错误处理
     *
     * @param $errno
     * @param $errstr
     * @param $errfile
     * @param $errline
     */
    public static function handleError($errorNo, $errorStr, $errorFile, $errorLine)
    {
        switch ($errno) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                ob_end_clean();
                $errorFormat = "$errorStr " . $errorFile . " 第 $errorLine 行.";
                static::error("[$errorNo] " . $errorFormat);
                self::halt($errorFormat);
                break;
            default:
                $errorFormat = "$errorStr " . $errorFile . " 第 $errorLine 行.";
                C('APP_DEBUG') && static::notice("[$errorNo] " . $errorFormat);
                break;
        }
    }

    /**
     * 致命错误捕获
     */
    public static function handleFatal()
    {
        $error = error_get_last();
        if ($error) {
            switch ($error['type']) {
                case E_ERROR:
                case E_PARSE:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                    ob_end_clean();
                    static::halt($error);
                    break;
            }
        }
    }

    /**
     * 错误信息重定向
     *
     * @param $error
     */
    private static function halt($error)
    {
        // 刷新日志到文件
        //static::getLogger()->forceFlush();
        // 发送错误代码
        send_http_status(500);
        // 错误日志输出
        $haltError = [];
        if (C('APP_DEBUG')) {
            if (is_array($error)) {
                // file,line,message,trace
                $haltError = $error;
                static::error($haltError['message'] . ',' . $haltError['file'] . ':' . $haltError['line']);
            } else {
                $trace = debug_backtrace();
                $haltError['message'] = $error;
                $haltError['file'] = $trace[0]['file'] ?? '';
                $haltError['line'] = $trace[0]['line'] ?? '';
                ob_start();
                debug_print_backtrace();
                $haltError['trace'] = ob_get_clean();
            }
            exit(dump($haltError, false));
        } else {
            // 否则定向到错误页面
            $errorPage = C('ERROR_PAGE');
            if (!empty($errorPage)) {
                redirect($errorPage);
            } else {
                $haltError['message'] = is_array($error) ? $error['message'] . ',' . $error['file'] . ':' . $error['line'] : $error;
            }
            static::error($haltError['message']);
        }
    }
}
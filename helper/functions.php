<?php

/**
 * dotenv
 *
 * @param      $key
 * @param null $default
 * @return array|bool|false|string|void
 */
function env($key, $default = null)
{
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    switch (strtolower($value)) {
        case 'true':
        case '(true)':
            return true;

        case 'false':
        case '(false)':
            return false;

        case 'empty':
        case '(empty)':
            return '';

        case 'null':
        case '(null)':
            return;
    }
    if (startsWith($value, '"') && endsWith($value, '"')) {
        return substr($value, 1, -1);
    }
    return $value;
}

/**
 * Start with
 *
 * @param $haystack
 * @param $needles
 * @return bool
 */
function startsWith($haystack, $needles)
{
    foreach ((array) $needles as $needle) {
        if ($needle !== '' && substr($haystack, 0, strlen($needle)) === (string) $needle) {
            return true;
        }
    }
    return false;
}

/**
 * End with
 *
 * @param $haystack
 * @param $needles
 * @return bool
 */
function endsWith($haystack, $needles)
{
    foreach ((array) $needles as $needle) {
        if (substr($haystack, -strlen($needle)) === (string) $needle) {
            return true;
        }
    }
    return false;
}

/**
 * 加载配置文件 支持格式转换 仅支持一级配置
 *
 * @param        $file
 * @return array|bool|mixed
 * @throws Think\Exception
 */
function load_config($file)
{
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    switch ($ext) {
        case 'php':
            return include $file;
        case 'ini':
            return parse_ini_file($file);
        case 'xml':
            return (array) simplexml_load_file($file);
        case 'json':
            return json_decode(file_get_contents($file), true);
        default:
            E('Not support extention: ' . $ext);
    }
}

/**
 * 编译文件
 *
 * @param $filename
 * @return string
 */
function compile($filename)
{
    $content = php_strip_whitespace($filename);
    $content = trim(substr($content, 5));
    // 替换预编译指令
    $content = preg_replace('/\/\/\[RUNTIME\](.*?)\/\/\[\/RUNTIME\]/s', '', $content);
    if (0 === strpos($content, 'namespace')) {
        $content = preg_replace('/namespace\s(.*?);/', 'namespace \\1{', $content, 1);
    } else {
        $content = 'namespace {' . $content;
    }
    if ('?>' == substr($content, -2))
        $content = substr($content, 0, -2);
    return $content . '}';
}

/**
 * 递归过滤
 *
 * @param $filter
 * @param $data
 * @return array
 */
function array_map_recursive($filter, $data)
{
    $result = [];
    foreach ($data as $key => $val) {
        $result[$key] = is_array($val) ? array_map_recursive($filter, $val) : call_user_func($filter, $val);
    }
    return $result;
}

/**
 * 字符串命名风格转换
 * type 0 将Java风格转换为C的风格 1 将C风格转换为Java的风格
 *
 * @param     $name
 * @param int $type
 * @return string
 */
function parse_name($name, $type = 0)
{
    if ($type) {
        return ucfirst(preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name));
    } else {
        return strtolower(trim(preg_replace('/[A-Z]/', "_\\0", $name), '_'));
    }
}

/**
 * 优化的require_once
 *
 * @param $filename
 * @return mixed
 */
function require_cache($filename)
{
    static $importFiles = [];
    if (!isset($importFiles[$filename])) {
        if (is_file($filename)) {
            require $filename;
            $importFiles[$filename] = true;
        } else {
            $importFiles[$filename] = false;
        }
    }
    return $importFiles[$filename];
}

/**
 * 实例化访问控制器
 *
 * @param $name
 * @return bool
 */
function controller($name)
{
    $layer = C('DEFAULT_C_LAYER');
    $class = 'App\\' . MODULE_NAME . '\\Controller';
    $array = explode('/', $name);
    foreach ($array as $name) {
        $class .= '\\' . parse_name($name, 1);
    }
    $class .= $layer;
    if (class_exists($class)) {
        return new $class();
    } else {
        return false;
    }
}

/**
 * 去除代码中的空白和注释
 *
 * @param $content
 * @return string
 */
function strip_whitespace($content)
{
    $stripStr = '';
    // 分析php源码
    $tokens = token_get_all($content);
    $last_space = false;
    for ($i = 0, $j = count($tokens); $i < $j; $i++) {
        if (is_string($tokens[$i])) {
            $last_space = false;
            $stripStr .= $tokens[$i];
        } else {
            switch ($tokens[$i][0]) {
                // 过滤各种PHP注释
                case T_COMMENT:
                case T_DOC_COMMENT:
                    break;
                // 过滤空格
                case T_WHITESPACE:
                    if (!$last_space) {
                        $stripStr .= ' ';
                        $last_space = true;
                    }
                    break;
                case T_START_HEREDOC:
                    $stripStr .= "<<<THINK\n";
                    break;
                case T_END_HEREDOC:
                    $stripStr .= "THINK;\n";
                    for ($k = $i + 1; $k < $j; $k++) {
                        if (is_string($tokens[$k]) && $tokens[$k] == ';') {
                            $i = $k;
                            break;
                        } elseif ($tokens[$k][0] == T_CLOSE_TAG) {
                            break;
                        }
                    }
                    break;
                default:
                    $last_space = false;
                    $stripStr .= $tokens[$i][1];
            }
        }
    }
    return $stripStr;
}

/**
 * 浏览器友好的变量输出
 *
 * @param      $var
 * @param bool $echo
 * @param null $label
 * @param bool $strict
 * @return mixed|null|string|string[]
 */
function dump($var, $echo = true, $label = null, $strict = true)
{
    $label = ($label === null) ? '' : rtrim($label) . ' ';
    if (!$strict) {
        if (ini_get('html_errors')) {
            $output = print_r($var, true);
            $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
        } else {
            $output = $label . print_r($var, true);
        }
    } else {
        ob_start();
        var_dump($var);
        $output = ob_get_clean();
        if (!extension_loaded('xdebug')) {
            $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
            $output = '<pre>' . $label . htmlspecialchars($output, ENT_QUOTES) . '</pre>';
        }
    }
    if ($echo) {
        echo($output);
        return null;
    } else {
        return $output;
    }
}

/**
 * 判断是否SSL协议
 *
 * @return bool
 */
function is_ssl()
{
    if (isset($_SERVER['HTTPS']) && ('1' == $_SERVER['HTTPS'] || 'on' == strtolower($_SERVER['HTTPS']))) {
        return true;
    } elseif (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
        return true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
        return true;
    }
    return false;
}

/**
 * URL重定向
 *
 * @param        $url
 * @param int    $time
 * @param string $msg
 */
function redirect($url, $time = 0, $msg = '')
{
    // 多行URL地址支持
    $url = str_replace([
        "\n",
        "\r"
    ], '', $url);
    if (empty($msg)) {
        $msg = "系统将在{$time}秒之后自动跳转到{$url}";
    }
    if (!headers_sent()) {
        // redirect
        if (0 === $time) {
            header('Location: ' . $url);
        } else {
            header("refresh:{$time};url={$url}");
            echo($msg);
        }
        exit();
    } else {
        $str = "<meta http-equiv='Refresh' content='{$time};URL={$url}'>";
        if ($time != 0) {
            $str .= $msg;
        }
        exit($str);
    }
}

/**
 * Session 管理
 *
 * @param string $name
 * @param string $value
 * @return null
 */
function session($name = '', $value = '')
{
    $prefix = C('SESSION_PREFIX');
    if ($name == '[init]') {
        if (C('SESSION_VAR') && isset($_REQUEST[C('SESSION_VAR')])) {
            session_id($_REQUEST[C('SESSION_VAR')]);
        }
        C('COOKIE_HTTPONLY') && ini_set('session.cookie_httponly', 1);
        C('COOKIE_DOMAIN') && ini_set('session.cookie_domain', C('COOKIE_DOMAIN'));
        C('COOKIE_PATH') && ini_set('session.cookie_path', C('COOKIE_PATH'));
        if (C('SESSION_TYPE')) {
            $type = C('SESSION_TYPE');
            $class = 'Think\\Session\\Handler\\' . ucwords(strtolower($type));
            $hander = new $class();
            session_set_save_handler($hander, true);
        }
        if (C('SESSION_AUTO_START')) {
            session_start();
        }
    } elseif ('' === $value) {
        if ('' === $name) {
            return $prefix ? $_SESSION[$prefix] : $_SESSION;
        } elseif (0 === strpos($name, '[')) {
            if ('[pause]' == $name) {
                session_write_close();
            } elseif ('[start]' == $name) {
                session_start();
            } elseif ('[destroy]' == $name) {
                $_SESSION = [];
                session_unset();
                session_destroy();
            } elseif ('[regenerate]' == $name) {
                session_regenerate_id();
            }
        } elseif (is_null($name)) {
            if ($prefix) {
                unset($_SESSION[$prefix]);
            } else {
                $_SESSION = [];
            }
        } elseif ($prefix) {
            if (strpos($name, '.')) {
                list ($name1, $name2) = explode('.', $name);
                return isset($_SESSION[$prefix][$name1][$name2]) ? $_SESSION[$prefix][$name1][$name2] : null;
            } else {
                return isset($_SESSION[$prefix][$name]) ? $_SESSION[$prefix][$name] : null;
            }
        } else {
            if (strpos($name, '.')) {
                list ($name1, $name2) = explode('.', $name);
                return isset($_SESSION[$name1][$name2]) ? $_SESSION[$name1][$name2] : null;
            } else {
                return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
            }
        }
    } elseif (is_null($value)) {
        if (strpos($name, '.')) {
            list ($name1, $name2) = explode('.', $name);
            if ($prefix) {
                unset($_SESSION[$prefix][$name1][$name2]);
            } else {
                unset($_SESSION[$name1][$name2]);
            }
        } else {
            if ($prefix) {
                unset($_SESSION[$prefix][$name]);
            } else {
                unset($_SESSION[$name]);
            }
        }
    } else {
        if (strpos($name, '.')) {
            list ($name1, $name2) = explode('.', $name);
            if ($prefix) {
                $_SESSION[$prefix][$name1][$name2] = $value;
            } else {
                $_SESSION[$name1][$name2] = $value;
            }
        } else {
            if ($prefix) {
                $_SESSION[$prefix][$name] = $value;
            } else {
                $_SESSION[$name] = $value;
            }
        }
    }
    return null;
}

/**
 * Cookie 管理
 *
 * @param string $name
 * @param string $value
 * @param null   $option
 * @return null
 */
function cookie($name = '', $value = '', $option = null)
{
    // 默认设置
    $config = [
        // cookie 名称前缀
        'prefix'   => C('COOKIE_PREFIX'),
        // cookie 保存时间
        'expire'   => C('COOKIE_EXPIRE'),
        // cookie 保存路径
        'path'     => C('COOKIE_PATH'),
        // cookie 有效域名
        'domain'   => C('COOKIE_DOMAIN'),
        // cookie 启用安全传输
        'secure'   => C('COOKIE_SECURE'),
        // httponly 设置
        'httponly' => C('COOKIE_HTTPONLY')
    ];
    // 参数设置(会覆盖黙认设置)
    if (!is_null($option)) {
        if (is_numeric($option)) {
            $option = [
                'expire' => $option
            ];
        } elseif (is_string($option)) {
            parse_str($option, $option);
        }
        $config = array_merge($config, array_change_key_case($option, CASE_LOWER));
    }
    // 清除指定前缀的所有cookie
    if (is_null($name)) {
        if (empty($_COOKIE)) {
            return null;
        }
        // 要删除的cookie前缀，不指定则删除 config 设置的指定前缀
        $prefix = empty($value) ? $config['prefix'] : $value;
        if (!empty($prefix)) { // 如果前缀为空字符串将不作处理直接返回
            foreach ($_COOKIE as $key => $val) {
                if (0 === stripos($key, $prefix)) {
                    setcookie($key, '', time() - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
                    unset($_COOKIE[$key]);
                }
            }
        }
        return null;
    } elseif ('' === $name) {
        // 获取全部的cookie
        return $_COOKIE;
    }
    $name = $config['prefix'] . str_replace('.', '_', $name);
    if ('' === $value) {
        if (isset($_COOKIE[$name])) {
            $value = $_COOKIE[$name];
            return $value;
        } else {
            return null;
        }
    } else {
        if (is_null($value)) {
            setcookie($name, '', time() - 3600, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            unset($_COOKIE[$name]); // 删除指定cookie
        } else {
            // 设置cookie
            if (is_array($value)) {
                $value = 'think:' . json_encode(array_map('urlencode', $value));
            }
            $expire = !empty($config['expire']) ? time() + intval($config['expire']) : 0;
            setcookie($name, $value, $expire, $config['path'], $config['domain'], $config['secure'], $config['httponly']);
            $_COOKIE[$name] = $value;
        }
    }
    return null;
}

/**
 * 获取客户端IP地址
 *
 * @param bool $long
 * @return mixed
 */
function get_client_ip()
{
    static $ip = null;
    if ($ip !== null) {
        return $ip;
    }
    if (isset($_SERVER)) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } else {
        if (getenv('HTTP_X_FORWARDED_FOR')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif (getenv('HTTP_CLIENT_IP')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } else {
            $ip = getenv('REMOTE_ADDR');
        }
    }
    // IP地址合法验证
    $ip2long = sprintf("%u", ip2long($ip));
    $ip = $ip2long ? $ip : '0.0.0.0';
    return $ip;
}

/**
 * 发送HTTP状态
 *
 * @param $code
 * @return mixed|string
 */
function send_http_status($code)
{
    static $status = [
        // Informational 1xx
        100 => 'Continue',
        101 => 'Switching Protocols',
        // Success 2xx
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        // Redirection 3xx
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Moved Temporarily ',
        // 1.1
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        // 306 is deprecated but reserved
        307 => 'Temporary Redirect',
        // Client Error 4xx
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        // Server Error 5xx
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        509 => 'Bandwidth Limit Exceeded'
    ];
    if (!isset($status[$code])) {
        return '';
    }
    header('HTTP/1.1 ' . $code . ' ' . $status[$code]);
    // 确保 FastCGI 模式下正常
    header('Status:' . $code . ' ' . $status[$code]);
    return $status[$code];
}

/**
 * 安全过滤
 *
 * @param $value
 */
function think_filter(&$value)
{
    // 过滤查询特殊字符
    if (preg_match('/^(EXP|NEQ|GT|EGT|LT|ELT|OR|XOR|LIKE|NOTLIKE|NOT BETWEEN|NOTBETWEEN|BETWEEN|NOTIN|NOT IN|IN|BIND)$/i', $value)) {
        $value .= ' ';
    }
}

/**
 * 不区分大小写的in_array实现
 *
 * @param $value
 * @param $array
 * @return bool
 */
function in_array_case($value, $array)
{
    return in_array(strtolower($value), array_map('strtolower', $array));
}

/**
 * 获取和设置配置参数 支持批量定义
 *
 * @param null $name
 * @param null $value
 * @param null $default
 * @return array|mixed|null
 */
function C($name = null, $value = null, $default = null)
{
    static $_config = [];
    // 无参数时获取所有
    if (empty($name)) {
        return $_config;
    }
    // 优先执行设置获取或赋值
    if (is_string($name)) {
        if (!strpos($name, '.')) {
            $name = strtoupper($name);
            if (is_null($value)) {
                return $_config[$name] ?? $default;
            }
            $_config[$name] = $value;
            return null;
        }
        // 二维数组设置和获取支持
        $name = explode('.', $name);
        $name[0] = strtoupper($name[0]);
        if (is_null($value)) {
            return $_config[$name[0]][$name[1]] ?? $default;
        }
        $_config[$name[0]][$name[1]] = $value;
        return null;
    }
    // 批量设置
    if (is_array($name)) {
        $_config = array_merge($_config, array_change_key_case($name, CASE_UPPER));
        return null;
    }
    return null;
}

/**
 * 语言包
 *
 * @param null $name
 * @param null $value
 * @return array|mixed|null|string
 */
function L($name = null, $value = null)
{
    static $_lang = array();
    // 空参数返回所有定义
    if (empty($name)) {
        return $_lang;
    }
    // 判断语言获取(或设置)
    // 若不存在,直接返回全大写$name
    if (is_string($name)) {
        $name = strtoupper($name);
        if (is_null($value)) {
            return $_lang[$name] ?? $name;
        } elseif (is_array($value)) {
            // 支持变量
            $replace = array_keys($value);
            foreach ($replace as &$v) {
                $v = '{$' . $v . '}';
            }
            return str_replace($replace, $value, $_lang[$name] ?? $name);
        }
        // 语言定义
        $_lang[$name] = $value;
        return null;
    }
    // 批量定义
    if (is_array($name)) {
        $_lang = array_merge($_lang, array_change_key_case($name, CASE_UPPER));
    }
    return null;
}

/**
 * 获取输入参数 支持过滤和默认值
 * 使用方法:
 * <code>
 * I('id',0); 获取id参数 自动判断get或者post
 * I('post.name','','htmlspecialchars'); 获取$_POST['name']
 * I('get.id|int',0);
 * I('get.'); 获取$_GET
 * </code>
 *
 * @param string $name
 * @param string $default
 * @param null   $filter
 * @return array|int|mixed|null
 */
function I($name = '', $default = '', $filter = null)
{
    static $_PUT = null;
    if (strpos($name, '|')) {
        // 指定修饰符
        list ($name, $type) = explode('|', $name, 2);
    }
    if (strpos($name, '.')) {
        // 指定参数来源
        list ($method, $name) = explode('.', $name, 2);
    } else {
        // 默认为自动判断
        $method = 'param';
    }
    switch (strtolower($method)) {
        case 'get':
            $input = &$_GET;
            break;
        case 'post':
            $input = &$_POST;
            break;
        case 'put':
            if (is_null($_PUT)) {
                parse_str(file_get_contents('php://input'), $_PUT);
            }
            $input = $_PUT;
            break;
        case 'param':
            switch ($_SERVER['REQUEST_METHOD']) {
                case 'POST':
                    $input = $_POST;
                    break;
                case 'PUT':
                    if (is_null($_PUT)) {
                        parse_str(file_get_contents('php://input'), $_PUT);
                    }
                    $input = $_PUT;
                    break;
                default:
                    $input = $_GET;
            }
            break;
        case 'request':
            $input = &$_REQUEST;
            break;
        default:
            return null;
    }
    if ('' == $name) {
        // 获取全部变量
        $data = $input;
        $filters = isset($filter) ? $filter : C('DEFAULT_FILTER');
        if ($filters) {
            if (is_string($filters)) {
                $filters = explode(',', $filters);
            }
            foreach ($filters as $filter) {
                // 参数过滤
                $data = array_map_recursive($filter, $data);
            }
        }
    } elseif (isset($input[$name])) {
        // 取值操作
        $data = $input[$name];
        $filters = isset($filter) ? $filter : C('DEFAULT_FILTER');
        if ($filters) {
            if (is_string($filters)) {
                if (0 === strpos($filters, '/')) {
                    // 支持正则验证
                    if (1 !== preg_match($filters, (string) $data)) {
                        return isset($default) ? $default : null;
                    }
                } else {
                    $filters = explode(',', $filters);
                }
            } elseif (is_int($filters)) {
                $filters = [
                    $filters
                ];
            }
            if (is_array($filters)) {
                foreach ($filters as $filter) {
                    if (function_exists($filter)) {
                        // 参数过滤
                        $data = is_array($data) ? array_map_recursive($filter, $data) : $filter($data);
                    } else {
                        $data = filter_var($data, is_int($filter) ? $filter : filter_id($filter));
                        if (false === $data) {
                            return isset($default) ? $default : null;
                        }
                    }
                }
            }
        }
        if (!empty($type)) {
            switch (strtolower($type)) {
                case 'array': // 数组
                    $data = (array) $data;
                    break;
                case 'digit': // 数字
                    $data = (int) $data;
                    break;
                case 'float': // 浮点
                    $data = (float) $data;
                    break;
                case 'bool': // 布尔
                    $data = (boolean) $data;
                    break;
                case 'string': // 字符串
                default:
                    $data = (string) $data;
            }
        }
    } else {
        // 变量默认值
        $data = isset($default) ? $default : null;
    }
    is_array($data) && array_walk_recursive($data, 'think_filter');
    return $data;
}

/**
 * URL组装 支持不同URL模式
 *
 * @param string $url
 * @param string $vars
 * @param bool   $suffix
 * @param bool   $domain
 * @return bool|mixed|string
 */
function U($url = '', $vars = '', $suffix = true, $domain = false)
{
    // 解析URL
    $info = parse_url($url);
    $url = $info['path'] ?? ACTION_NAME;
    if (empty($url)) {
        return '';
    }
    // 解析参数，aaa=1&bbb=2 转换成数组
    if (is_string($vars)) {
        parse_str($vars, $vars);
    } elseif (!is_array($vars)) {
        $vars = [];
    }
    // 解析模块、控制器、操作
    $url = trim($url, '/');
    $path = explode('/', $url);
    $action = array_pop($path);
    $controller = empty($path) ? CONTROLLER_NAME : array_pop($path);
    $controller = parse_name($controller);
    $module = empty($path) ? MODULE_NAME : array_pop($path);
    // PATHINFO模式
    $url = strtolower(__APP__ . '/' . $module . '/' . $controller . '/') . $action;
    // 添加参数
    if (!empty($vars)) {
        foreach ($vars as $var => $val) {
            if ('' !== trim($val)) {
                $url .= '/' . $var . '/' . urlencode($val);
            }
        }
    }
    $suffix && $url .= '.html';
    if ($domain) {
        $url = (is_ssl() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $url;
    }
    return $url;
}

/**
 * 缓存管理
 *
 * @param        $name
 * @param string $value
 * @param null   $expire
 * @return mixed|string
 * @throws Think\Exception
 */
function S($name, $value = '', $expire = null)
{
    $cacheType = C('DATA_CACHE_TYPE');
    $options = [
        'prefix' => C('DATA_CACHE_PREFIX'),
        'expire' => C('DATA_CACHE_EXPIRE')
    ];
    $cache = Think\Cache::getInstance($cacheType, $options);
    if ('' === $value) {
        return $cache->get($name);
    } elseif (is_null($value)) {
        return $cache->del($name);
    } else {
        $expire = is_numeric($expire) ? $expire : ($expire['expire'] ?? null);
        return $cache->set($name, $value, $expire);
    }
}

/**
 * 快速文件数据读取和保存 针对简单类型数据 字符串、数组
 *
 * @param        $name
 * @param string $value
 * @param string $path
 * @return bool|mixed|null|string
 */
function F($name, $value = '', $path = DATA_PATH)
{
    static $_cache = [];
    $filename = rtrim($path, '/') . '/' . $name . '.php';
    if ('' !== $value) {
        if (is_null($value)) {
            // 删除缓存
            if (false !== strpos($name, '*')) {
                return false; // TODO
            } else {
                unset($_cache[$name]);
                return Think\Storage::unlink($filename, 'F');
            }
        } else {
            Think\Storage::put($filename, serialize($value), 'F');
            // 缓存数据
            $_cache[$name] = $value;
            return null;
        }
    }
    // 获取缓存数据
    if (isset($_cache[$name])) {
        return $_cache[$name];
    }
    if (Think\Storage::has($filename, 'F')) {
        $value = unserialize(Think\Storage::read($filename, 'F'));
        $_cache[$name] = $value;
    } else {
        $value = false;
    }
    return $value;
}

/**
 * 获取模版文件 格式 资源://模块@控制器/操作
 *
 * @param string $template
 * @param string $layer
 * @return string
 */
function T($template = '', $layer = '')
{
    // 解析模版资源地址
    if (false === strpos($template, '://')) {
        $template = 'http://' . str_replace(':', '/', $template);
    }
    // http://module@controller/action
    $info = parse_url($template);
    // user=module, host=controller, path=/action
    $file = $info['host'] . ($info['path'] ?? '');
    $module = ($info['user'] ?? MODULE_NAME) . '/';
    $layer = $layer ? $layer : C('DEFAULT_V_LAYER');
    $baseUrl = APP_PATH . '/' . $module . $layer . '/';
    // 分析模板文件规则
    if ('' == $file) {
        // 如果模板文件名为空 按照默认规则定位
        $file = CONTROLLER_NAME . '/' . ACTION_NAME;
    } elseif (false === strpos($file, '/')) {
        $file = CONTROLLER_NAME . '/' . $file;
    }
    return $baseUrl . $file . C('TMPL_FILE_SUFFIX');
}

/**
 * 抛出异常处理
 *
 * @param     $msg
 * @param int $code
 * @throws Think\Exception
 */
function E($msg, $code = 0)
{
    throw new Think\Exception($msg, $code);
}

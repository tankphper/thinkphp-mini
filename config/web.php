<?php
return [
    'APP_DEBUG'          => env('APP_DEBUG', false),
    // 默认值配置
    'DEFAULT_THEME'      => env('DEFAULT_THEME', ''),
    'DEFAULT_M_LAYER'    => env('DEFAULT_M_LAYER', 'Model'),
    'DEFAULT_C_LAYER'    => env('DEFAULT_C_LAYER', 'Controller'),
    'DEFAULT_V_LAYER'    => env('DEFAULT_V_LAYER', 'View'),
    'DEFAULT_MODULE'     => env('DEFAULT_MODULE', 'Home'),
    'DEFAULT_CONTROLLER' => env('DEFAULT_CONTROLLER', 'Index'),
    'DEFAULT_ACTION'     => env('DEFAULT_ACTION', 'index'),
    'DEFAULT_FILTER'     => env('DEFAULT_FILTER', 'trim,htmlspecialchars'),
    // 数据库配置
    'DB_TYPE'            => env('DB_TYPE', 'mysql'),
    'DB_HOST'            => env('DB_HOST', '127.0.0.1'),
    'DB_NAME'            => env('DB_NAME', 'test'),
    'DB_USER'            => env('DB_USER', 'root'),
    'DB_PWD'             => env('DB_PWD', '123456'),
    'DB_PORT'            => env('DB_PORT', '3306'),
    'DB_PREFIX'          => env('DB_PREFIX', ''),
    'DB_CHARSET'         => env('DB_CHARSET', 'utf8mb4'),
    'DB_DEBUG'           => env('DB_DEBUG', true),
    'DB_FIELDS_CACHE'    => env('DB_FIELDS_CACHE', true),
    // Redis配置
    'REDIS_HOST'         => env('REDIS_HOST', '127.0.0.1'),
    'REDIS_PORT'         => env('REDIS_PORT', 6379),
    'REDIS_PWD'          => env('REDIS_PWD', '123456'),
    'REDIS_SELECT'       => env('REDIS_SELECT', 0),
    // 缓存配置
    'DATA_CACHE_EXPIRE'  => env('DATA_CACHE_EXPIRE', 600),
    'DATA_CACHE_PREFIX'  => env('DATA_CACHE_PREFIX', ''),
    'DATA_CACHE_TYPE'    => env('DATA_CACHE_TYPE', 'Redis'),
    // 错误设置
    'ERROR_PAGE'         => env('ERROR_PAGE', ''),
    // 日志配置
    'LOG_NAME'           => env('LOG_NAME', 'APP'),
    'LOG_FILE'           => env('LOG_FILE', LOG_PATH . '/app.log'),
    'LOG_FILE_KEEP_DAY'  => env('LOG_FILE_KEEP_DAY', 7),
    'LOG_FLUSH_SIZE'     => env('LOG_FLUSH_SIZE', 1),
    'LOG_ROTATE_SIZE'    => env('LOG_ROTATE_SIZE', 20971520),
    'LOG_FORMAT'         => env('LOG_FORMAT', "%datetime% [%channel%.%level_name%.%request_id%] %remote_ip% %request_method% %request_uri% => %message%"),
    // Session 配置
    'SESSION_AUTO_START' => env('SESSION_AUTO_START', true),
    'SESSION_TYPE'       => env('SESSION_TYPE', 'Redis'),
    'SESSION_PREFIX'     => env('SESSION_PREFIX', 'session:'),
    'SESSION_EXPIRE'     => env('SESSION_EXPIRE', 3600),
    'SESSION_VAR'        => env('SESSION_VAR', 'PHPSESSID'),
    // Cookie 配置
    'COOKIE_EXPIRE'      => env('COOKIE_EXPIRE', 3600),
    'COOKIE_DOMAIN'      => env('COOKIE_DOMAIN', ''),
    'COOKIE_PATH'        => env('COOKIE_PATH', '/'),
    'COOKIE_PREFIX'      => env('COOKIE_PREFIX', 'tk:'),
    'COOKIE_SECURE'      => env('COOKIE_SECURE', false),
    'COOKIE_HTTPONLY'    => env('COOKIE_HTTPONLY', true),
    // 模板配置
    'TMPL_FILE_SUFFIX'   => env('TMPL_FILE_SUFFIX', '.html'),
    'TMPL_CACHE_SUFFIX'  => env('TMPL_CACHE_SUFFIX', '.php'),
    'TMPL_CACHE_EXPIRE'  => env('TMPL_CACHE_EXPIRE', 0),
    'TMPL_BEGIN_DELIM'   => env('TMPL_BEGIN_DELIM', '{'),
    'TMPL_END_DELIM'     => env('TMPL_END_DELIM', '}'),
    // 标签库配置
    'TAGLIB_BEGIN'       => env('TAGLIB_BEGIN', '<'),
    'TAGLIB_END'         => env('TAGLIB_END', '>'),
    'TAGLIB_LOAD'        => env('TAGLIB_LOAD', true),
    'TAGLIB_BUILD_IN'    => env('TAGLIB_BUILD_IN', 'cx'),
    // 系统变量配置
    'VAR_MODULE'         => env('VAR_MODULE', 'm'),
    'VAR_CONTROLLER'     => env('VAR_CONTROLLER', 'c'),
    'VAR_ACTION'         => env('VAR_ACTION', 'a'),
];



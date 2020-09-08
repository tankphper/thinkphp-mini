<?php
namespace Think\Db;

use PDO;
use Think\App;

abstract class AbstractDb
{
    // PDO操作实例
    protected $PDOStatement = null;

    // 当前操作所属的模型名
    protected $model = '_think_';

    // 当前SQL指令
    protected $queryStr = '';

    // sql 信息
    protected $modelSql = [];

    // 最后插入ID
    protected $lastInsID = null;

    // 返回或者影响记录数
    protected $numRows = 0;

    // 事务指令数
    protected $transTimes = 0;

    // 错误信息
    protected $errorInfo = '';

    // 数据库连接ID 支持多个连接
    protected $linkPool = [];

    // 当前连接ID
    protected $linkId = null;

    // 数据库连接参数配置
    protected $config = [
        // 数据库类型
        'type'     => '',
        // 服务器地址
        'hostname' => '127.0.0.1',
        // 数据库名
        'database' => '',
        // 用户名
        'username' => '',
        // 密码
        'password' => '',
        // 端口
        'hostport' => '',
        // 数据库编码默认采用utf8
        'charset'  => 'utf8',
        // 数据库表前缀
        'prefix'   => '',
        // 数据库调试模式
        'debug'    => false
    ];

    // 数据库表达式
    protected $exp = [
        'eq'          => '=',
        'neq'         => '<>',
        'gt'          => '>',
        'egt'         => '>=',
        'lt'          => '<',
        'elt'         => '<=',
        'notlike'     => 'NOT LIKE',
        'like'        => 'LIKE',
        'in'          => 'IN',
        'notin'       => 'NOT IN',
        'not in'      => 'NOT IN',
        'between'     => 'BETWEEN',
        'not between' => 'NOT BETWEEN',
        'notbetween'  => 'NOT BETWEEN'
    ];

    // 查询表达式
    protected $selectSql = 'SELECT%DISTINCT% %FIELD% FROM %TABLE%%FORCE%%JOIN%%WHERE%%GROUP%%HAVING%%ORDER%%LIMIT% %UNION%%LOCK%%COMMENT%';

    // 查询次数
    protected $queryTimes = 0;

    // 执行次数
    protected $executeTimes = 0;

    // PDO连接参数
    protected $options = [
        PDO::ATTR_CASE              => PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false
    ];

    // 参数绑定
    protected $bind = [];

    /**
     * AbstractDb constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }
    }

    /**
     * 连接数据库方法
     *
     * @param string $config
     * @param int    $linkNum
     * @param bool   $autoConnection
     * @return mixed
     * @throws \Think\Exception
     */
    public function connect($config = '', $linkNum = 0, $autoConnection = false)
    {
        if (!isset($this->linkPool[$linkNum])) {
            if (empty($config)) {
                $config = $this->config;
            }
            try {
                $pdoDsn = $this->buildDsn($config);
                $this->linkPool[$linkNum] = new PDO($pdoDsn, $config['username'], $config['password'], $this->options);
            } catch (\PDOException $e) {
                if ($autoConnection) {
                    App::error($e->getMessage());
                    return $this->connect($autoConnection, $linkNum);
                } elseif ($config['debug']) {
                    E($e->getMessage());
                }
            }
        }
        return $this->linkPool[$linkNum];
    }

    /**
     * 组装 pdo 连接的 dsn 信息
     *
     * @param $config
     */
    protected function buildDsn($config)
    {
        // TODO
    }

    /**
     * 释放查询结果
     */
    public function free()
    {
        $this->PDOStatement = null;
    }

    /**
     * 执行查询 返回数据集
     *
     * @param      $str
     * @param bool $fetchSql
     * @return bool|mixed|string
     * @throws \Think\Exception
     */
    public function query($str, $fetchSql = false)
    {
        $this->initConnect(false);
        if (!$this->linkId) {
            return false;
        }
        $this->queryStr = $str;
        if (!empty($this->bind)) {
            $that = $this;
            $this->queryStr = strtr($this->queryStr, array_map(function ($val) use ($that) {
                return '\'' . $that->escapeString($val) . '\'';
            }, $this->bind));
        }
        if ($fetchSql) {
            return $this->queryStr;
        }
        // 释放前次的查询结果
        if (!empty($this->PDOStatement)) {
            $this->free();
        }
        $this->queryTimes++;
        // 调试开始
        $this->debug(true);
        $this->PDOStatement = $this->linkId->prepare($str);
        if (false === $this->PDOStatement) {
            $this->parseError();
            return false;
        }
        foreach ($this->bind as $key => $val) {
            if (is_array($val)) {
                $this->PDOStatement->bindValue($key, $val[0], $val[1]);
            } else {
                $this->PDOStatement->bindValue($key, $val);
            }
        }
        $this->bind = [];
        $result = $this->PDOStatement->execute();
        // 调试结束
        $this->debug(false);
        if (false === $result) {
            $this->parseError();
            return false;
        } else {
            return $this->getResult();
        }
    }

    /**
     * 执行语句
     *
     * @param      $str
     * @param bool $fetchSql
     * @return bool|int|string
     * @throws \Think\Exception
     */
    public function execute($str, $fetchSql = false)
    {
        $this->initConnect(true);
        if (!$this->linkId) {
            return false;
        }
        $this->queryStr = $str;
        if (!empty($this->bind)) {
            $that = $this;
            $this->queryStr = strtr($this->queryStr, array_map(function ($val) use ($that) {
                return '\'' . $that->escapeString($val) . '\'';
            }, $this->bind));
        }
        if ($fetchSql) {
            return $this->queryStr;
        }
        // 释放前次的查询结果
        if (!empty($this->PDOStatement)) {
            $this->free();
        }
        $this->executeTimes++;
        // 记录开始执行时间
        $this->debug(true);
        $this->PDOStatement = $this->linkId->prepare($str);
        if (false === $this->PDOStatement) {
            $this->parseError();
            return false;
        }
        foreach ($this->bind as $key => $val) {
            if (is_array($val)) {
                $this->PDOStatement->bindValue($key, $val[0], $val[1]);
            } else {
                $this->PDOStatement->bindValue($key, $val);
            }
        }
        $this->bind = [];
        $result = $this->PDOStatement->execute();
        $this->debug(false);
        if (false === $result) {
            $this->parseError();
            return false;
        } else {
            $this->numRows = $this->PDOStatement->rowCount();
            if (preg_match("/^\s*(INSERT\s+INTO|REPLACE\s+INTO)\s+/i", $str)) {
                $this->lastInsID = $this->linkId->lastInsertId();
            }
            return $this->numRows;
        }
    }

    /**
     * 启动事务
     *
     * @return bool|void
     * @throws \Think\Exception
     */
    public function startTrans()
    {
        $this->initConnect(true);
        if (!$this->linkId) {
            return false;
        }
        ++$this->transTimes;
        // 支持嵌套事务，但不支持内嵌并行事务
        // 如：A->B->C支持，A->B + A-C 不支持
        if (1 == $this->transTimes) {
            $this->linkId->beginTransaction();
        }
    }

    /**
     * 提交事务
     *
     * @return bool
     * @throws \Think\Exception
     */
    public function commit()
    {
        // 由嵌套事物的最外层进行提交
        if (1 == $this->transTimes) {
            $result = $this->linkId->commit();
            if (!$result) {
                $this->parseError();
            }
        }
        --$this->transTimes;
    }

    /**
     * 事务回滚
     *
     * @return bool
     * @throws \Think\Exception
     */
    public function rollback()
    {
        if (1 == $this->transTimes) {
            $result = $this->linkId->rollback();
            if (!$result) {
                $this->parseError();
            }
        }
        $this->transTimes = max(0, $this->transTimes - 1);
    }

    /**
     * 获得所有的查询数据
     *
     * @return mixed
     */
    private function getResult()
    {
        // 返回数据集
        $result = $this->PDOStatement->fetchAll(PDO::FETCH_ASSOC);
        $this->numRows = count($result);
        return $result;
    }

    /**
     * 获得查询次数
     *
     * @param bool $execute
     * @return int
     */
    public function getQueryTimes($execute = false)
    {
        return $execute ? $this->queryTimes + $this->executeTimes : $this->queryTimes;
    }

    /**
     * 获得执行次数
     *
     * @return int
     */
    public function getExecuteTimes()
    {
        return $this->executeTimes;
    }

    /**
     * 关闭数据库
     */
    public function close()
    {
        $this->linkId = null;
    }

    /**
     * 数据库错误信息
     * 并显示当前的SQL语句
     *
     * @throws \Think\Exception
     */
    public function parseError()
    {
        if ($this->PDOStatement) {
            $errorInfo = $this->PDOStatement->errorInfo();
            $this->errorInfo = $errorInfo[1] . ':' . $errorInfo[2];
        } else {
            $this->errorInfo = '';
        }
        if ('' != $this->queryStr) {
            $this->errorInfo .= "\n [ SQL语句 ] : " . $this->queryStr;
        }
        // 记录错误日志
        App::error($this->errorInfo);
        // 调试模式输出
        if ($this->config['debug']) {
            E($this->errorInfo);
        }
    }

    /**
     * 设置锁机制
     *
     * @param bool $lock
     * @return string
     */
    protected function parseLock($lock = false)
    {
        return $lock ? ' FOR UPDATE ' : '';
    }

    /**
     * set 分析
     *
     * @param $data
     * @return string
     */
    protected function parseSet($data)
    {
        foreach ($data as $key => $val) {
            if (is_array($val) && 'exp' == $val[0]) {
                $set[] = $this->parseKey($key) . '=' . $val[1];
            } elseif (is_null($val)) {
                $set[] = $this->parseKey($key) . '=NULL';
            } elseif (is_scalar($val)) {
                // 过滤非标量数据
                if (0 === strpos($val, ':') && in_array($val, array_keys($this->bind))) {
                    $set[] = $this->parseKey($key) . '=' . $this->escapeString($val);
                } else {
                    $name = count($this->bind);
                    $set[] = $this->parseKey($key) . '=:' . $name;
                    $this->bindParam($name, $val);
                }
            }
        }
        return ' SET ' . implode(',', $set);
    }

    /**
     * 参数绑定
     *
     * @param $name
     * @param $value
     */
    protected function bindParam($name, $value)
    {
        $this->bind[':' . $name] = $value;
    }

    /**
     * 字段和表名处理
     *
     * @param      $key
     * @param bool $strict
     * @return mixed
     */
    public function parseKey($key, $strict = false)
    {
        return $key;
    }

    /**
     * value 分析
     *
     * @param $value
     * @return array|string
     */
    protected function parseValue($value)
    {
        if (is_string($value)) {
            $value = strpos($value, ':') === 0 && in_array($value, array_keys($this->bind)) ? $this->escapeString($value) : '\'' . $this->escapeString($value) . '\'';
        } elseif (isset($value[0]) && is_string($value[0]) && strtolower($value[0]) == 'exp') {
            $value = $this->escapeString($value[1]);
        } elseif (is_array($value)) {
            $value = array_map([
                $this,
                'parseValue'
            ], $value);
        } elseif (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_null($value)) {
            $value = 'null';
        }
        return $value;
    }

    /**
     * field 分析
     *
     * @param $fields
     * @return string
     */
    protected function parseField($fields)
    {
        if (is_string($fields) && '' !== $fields) {
            $fields = explode(',', $fields);
        }
        if (is_array($fields)) {
            // 完善数组方式传字段名的支持
            // 支持 'field1'=>'field2' 这样的字段别名定义
            $array = [];
            foreach ($fields as $key => $field) {
                if (!is_numeric($key)) {
                    $array[] = $this->parseKey($key) . ' AS ' . $this->parseKey($field);
                } else {
                    $array[] = $this->parseKey($field);
                }
            }
            $fieldsStr = implode(',', $array);
        } else {
            $fieldsStr = '*';
        }
        // TODO 如果是查询全部字段，并且是join的方式，那么就把要查的表加个别名，以免字段被覆盖
        return $fieldsStr;
    }

    /**
     * table 分析
     *
     * @param $tables
     * @return string
     */
    protected function parseTable($tables)
    {
        // 支持别名定义
        if (is_array($tables)) {
            $array = [];
            foreach ($tables as $table => $alias) {
                if (!is_numeric($table)) {
                    $array[] = $this->parseKey($table) . ' ' . $this->parseKey($alias);
                } else {
                    $array[] = $this->parseKey($alias);
                }
            }
            $tables = $array;
        } elseif (is_string($tables)) {
            $tables = explode(',', $tables);
            array_walk($tables, [
                &$this,
                'parseKey'
            ]);
        }
        return implode(',', $tables);
    }

    /**
     * where 分析
     *
     * @param $where
     * @return string
     * @throws \Think\Exception
     */
    protected function parseWhere($where)
    {
        $whereStr = '';
        if (is_string($where)) {
            // 直接使用字符串条件
            $whereStr = $where;
        } else {
            // 使用数组表达式
            $operate = isset($where['_logic']) ? strtoupper($where['_logic']) : '';
            if (in_array($operate, [
                'AND',
                'OR',
                'XOR'
            ])) {
                // 定义逻辑运算规则 例如 OR XOR AND NOT
                $operate = ' ' . $operate . ' ';
                unset($where['_logic']);
            } else {
                // 默认进行 AND 运算
                $operate = ' AND ';
            }
            foreach ($where as $key => $val) {
                if (is_numeric($key)) {
                    $key = '_complex';
                }
                if (0 === strpos($key, '_')) {
                    // 解析特殊条件表达式
                    $whereStr .= $this->parseThinkWhere($key, $val);
                } else {
                    // 多条件支持
                    $multi = is_array($val) && isset($val['_multi']);
                    $key = trim($key);
                    if (strpos($key, '|')) {
                        // 支持 name|title|nickname 方式定义查询字段
                        $array = explode('|', $key);
                        $str = [];
                        foreach ($array as $m => $k) {
                            $v = $multi ? $val[$m] : $val;
                            $str[] = $this->parseWhereItem($this->parseKey($k), $v);
                        }
                        $whereStr .= '( ' . implode(' OR ', $str) . ' )';
                    } elseif (strpos($key, '&')) {
                        $array = explode('&', $key);
                        $str = [];
                        foreach ($array as $m => $k) {
                            $v = $multi ? $val[$m] : $val;
                            $str[] = '(' . $this->parseWhereItem($this->parseKey($k), $v) . ')';
                        }
                        $whereStr .= '( ' . implode(' AND ', $str) . ' )';
                    } else {
                        $whereStr .= $this->parseWhereItem($this->parseKey($key), $val);
                    }
                }
                $whereStr .= $operate;
            }
            $whereStr = substr($whereStr, 0, -strlen($operate));
        }
        return empty($whereStr) ? '' : ' WHERE ' . $whereStr;
    }

    /**
     * where 子单元分析
     *
     * @param $key
     * @param $val
     * @return string
     * @throws \Think\Exception
     */
    protected function parseWhereItem($key, $val)
    {
        $whereStr = '';
        if (is_array($val)) {
            if (is_string($val[0])) {
                $exp = strtolower($val[0]);
                // 比较运算
                if (preg_match('/^(eq|neq|gt|egt|lt|elt)$/', $exp)) {
                    $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($val[1]);
                } elseif (preg_match('/^(notlike|like)$/', $exp)) {
                    // 模糊查找
                    if (is_array($val[1])) {
                        $likeLogic = isset($val[2]) ? strtoupper($val[2]) : 'OR';
                        if (in_array($likeLogic, [
                            'AND',
                            'OR',
                            'XOR'
                        ])) {
                            $like = [];
                            foreach ($val[1] as $item) {
                                $like[] = $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($item);
                            }
                            $whereStr .= '(' . implode(' ' . $likeLogic . ' ', $like) . ')';
                        }
                    } else {
                        $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($val[1]);
                    }
                } elseif ('bind' == $exp) {
                    // 使用表达式
                    $whereStr .= $key . ' = :' . $val[1];
                } elseif ('exp' == $exp) {
                    // 使用表达式
                    $whereStr .= $key . ' ' . $val[1];
                } elseif (preg_match('/^(notin|not in|in)$/', $exp)) {
                    // IN 运算
                    if (isset($val[2]) && 'exp' == $val[2]) {
                        $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $val[1];
                    } else {
                        if (is_string($val[1])) {
                            $val[1] = explode(',', $val[1]);
                        }
                        $zone = implode(',', $this->parseValue($val[1]));
                        $whereStr .= $key . ' ' . $this->exp[$exp] . ' (' . $zone . ')';
                    }
                } elseif (preg_match('/^(notbetween|not between|between)$/', $exp)) {
                    // BETWEEN运算
                    $data = is_string($val[1]) ? explode(',', $val[1]) : $val[1];
                    $whereStr .= $key . ' ' . $this->exp[$exp] . ' ' . $this->parseValue($data[0]) . ' AND ' . $this->parseValue($data[1]);
                } else {
                    E('Express error: ' . $val[0]);
                }
            } else {
                $count = count($val);
                $rule = isset($val[$count - 1]) ? (is_array($val[$count - 1]) ? strtoupper($val[$count - 1][0]) : strtoupper($val[$count - 1])) : '';
                if (in_array($rule, [
                    'AND',
                    'OR',
                    'XOR'
                ])) {
                    $count = $count - 1;
                } else {
                    $rule = 'AND';
                }
                for ($i = 0; $i < $count; $i++) {
                    $data = is_array($val[$i]) ? $val[$i][1] : $val[$i];
                    if ('exp' == strtolower($val[$i][0])) {
                        $whereStr .= $key . ' ' . $data . ' ' . $rule . ' ';
                    } else {
                        $whereStr .= $this->parseWhereItem($key, $val[$i]) . ' ' . $rule . ' ';
                    }
                }
                $whereStr = '( ' . substr($whereStr, 0, -4) . ' )';
            }
        } else {
            $whereStr .= $key . ' = ' . $this->parseValue($val);
        }
        return $whereStr;
    }

    /**
     * 特殊条件分析
     *
     * @param $key
     * @param $val
     * @return string
     */
    protected function parseThinkWhere($key, $val)
    {
        $whereStr = '';
        switch ($key) {
            case '_string':
                // 字符串模式查询条件
                $whereStr = $val;
                break;
            case '_complex':
                // 复合查询条件
                $whereStr = substr($this->parseWhere($val), 6);
                break;
            case '_query':
                // 字符串模式查询条件
                parse_str($val, $where);
                if (isset($where['_logic'])) {
                    $op = ' ' . strtoupper($where['_logic']) . ' ';
                    unset($where['_logic']);
                } else {
                    $op = ' AND ';
                }
                $array = [];
                foreach ($where as $field => $data) {
                    $array[] = $this->parseKey($field) . ' = ' . $this->parseValue($data);
                }
                $whereStr = implode($op, $array);
                break;
        }
        return '( ' . $whereStr . ' )';
    }

    /**
     * limit 分析
     *
     * @param $limit
     * @return string
     */
    protected function parseLimit($limit)
    {
        return !empty($limit) ? ' LIMIT ' . $limit . ' ' : '';
    }

    /**
     * join 分析
     *
     * @param $join
     * @return string
     */
    protected function parseJoin($join)
    {
        $joinStr = '';
        if (!empty($join)) {
            $joinStr = ' ' . implode(' ', $join) . ' ';
        }
        return $joinStr;
    }

    /**
     * order 分析
     *
     * @param $order
     * @return string
     */
    protected function parseOrder($order)
    {
        if (empty($order)) {
            return '';
        }
        $array = [];
        if (is_string($order) && '[RAND]' != $order) {
            $order = array_map('trim', explode(',', $order));
        }
        if (is_array($order)) {
            foreach ($order as $key => $val) {
                if (is_numeric($key)) {
                    list($key, $sort) = explode(' ', strpos($val, ' ') ? $val : $val . ' ');
                } else {
                    $sort = $val;
                }
                if (preg_match('/^[\w\.]+$/', $key)) {
                    $sort = strtoupper($sort);
                    $sort = in_array($sort, [
                        'ASC',
                        'DESC'
                    ], true) ? ' ' . $sort : '';
                    if (strpos($key, '.')) {
                        list($alias, $key) = explode('.', $key);
                        $array[] = $this->parseKey($alias, true) . '.' . $this->parseKey($key, true) . $sort;
                    } else {
                        $array[] = $this->parseKey($key, true) . $sort;
                    }
                }
            }
        } elseif ('[RAND]' == $order) {
            // 随机排序
            $array[] = $this->parseRand();
        }
        $order = implode(',', $array);
        return !empty($order) ? ' ORDER BY ' . $order : '';
    }

    /**
     * group 分析
     *
     * @param $group
     * @return string
     */
    protected function parseGroup($group)
    {
        return !empty($group) ? ' GROUP BY ' . $group : '';
    }

    /**
     * having 分析
     *
     * @param $having
     * @return string
     */
    protected function parseHaving($having)
    {
        return !empty($having) ? ' HAVING ' . $having : '';
    }

    /**
     * comment 分析
     *
     * @param $comment
     * @return string
     */
    protected function parseComment($comment)
    {
        return !empty($comment) ? ' /* ' . $comment . ' */' : '';
    }

    /**
     * distinct 分析
     *
     * @param $distinct
     * @return string
     */
    protected function parseDistinct($distinct)
    {
        return !empty($distinct) ? ' DISTINCT ' : '';
    }

    /**
     * union 分析
     *
     * @param $union
     * @return string
     */
    protected function parseUnion($union)
    {
        if (empty($union)) {
            return '';
        }
        if (isset($union['_all'])) {
            $str = 'UNION ALL ';
            unset($union['_all']);
        } else {
            $str = 'UNION ';
        }
        foreach ($union as $u) {
            $sql[] = $str . (is_array($u) ? $this->buildSelectSql($u) : $u);
        }
        return implode(' ', $sql);
    }

    /**
     * 参数绑定分析
     *
     * @param $bind
     */
    protected function parseBind($bind)
    {
        $this->bind = array_merge($this->bind, $bind);
    }

    /**
     * index 分析，可在操作链中指定需要强制使用的索引
     *
     * @param $index
     * @return string
     */
    protected function parseForce($index)
    {
        if (empty($index)) {
            return '';
        }
        if (is_array($index)) {
            $index = join(',', $index);
        }
        return sprintf(" FORCE INDEX ( %s ) ", $index);
    }

    /**
     * ON DUPLICATE KEY UPDATE 分析
     *
     * @access protected
     * @param mixed $duplicate
     * @return string
     */
    protected function parseDuplicate($duplicate)
    {
        return '';
    }

    /**
     * 插入记录
     *
     * @param       $data
     * @param array $options
     * @param bool  $replace
     * @return bool|int|string
     */
    public function insert($data, $options = [], $replace = false)
    {
        $values = $fields = [];
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        foreach ($data as $key => $val) {
            if (is_array($val) && 'exp' == $val[0]) {
                $fields[] = $this->parseKey($key);
                $values[] = $val[1];
            } elseif (is_null($val)) {
                $fields[] = $this->parseKey($key);
                $values[] = 'NULL';
            } elseif (is_scalar($val)) {
                // 过滤非标量数据
                $fields[] = $this->parseKey($key);
                if (0 === strpos($val, ':') && in_array($val, array_keys($this->bind))) {
                    $values[] = $this->parseValue($val);
                } else {
                    $name = count($this->bind);
                    $values[] = ':' . $name;
                    $this->bindParam($name, $val);
                }
            }
        }
        // 兼容数字传入方式
        $replace = (is_numeric($replace) && $replace > 0) ? true : $replace;
        $sql = (true === $replace ? 'REPLACE' : 'INSERT') . ' INTO ' . $this->parseTable($options['table']) . ' (' . implode(',', $fields) . ') VALUES (' . implode(',', $values) . ')' . $this->parseDuplicate($replace);
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 批量插入记录
     *
     * @param       $dataSet
     * @param array $options
     * @return bool|int|string
     */
    public function insertAll($dataSet, $options = [])
    {
        $values = [];
        $this->model = $options['model'];
        if (!is_array($dataSet[0])) {
            return false;
        }
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        $fields = array_map([
            $this,
            'parseKey'
        ], array_keys($dataSet[0]));
        foreach ($dataSet as $data) {
            $value = [];
            foreach ($data as $key => $val) {
                if (is_array($val) && 'exp' == $val[0]) {
                    $value[] = $val[1];
                } elseif (is_null($val)) {
                    $value[] = 'NULL';
                } elseif (is_scalar($val)) {
                    if (0 === strpos($val, ':') && in_array($val, array_keys($this->bind))) {
                        $value[] = $this->parseValue($val);
                    } else {
                        $name = count($this->bind);
                        $value[] = ':' . $name;
                        $this->bindParam($name, $val);
                    }
                }
            }
            $values[] = 'SELECT ' . implode(',', $value);
        }
        $sql = 'INSERT INTO ' . $this->parseTable($options['table']) . ' (' . implode(',', $fields) . ') ' . implode(' UNION ALL ', $values);
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 通过 Select 方式插入记录
     *
     * @param       $fields
     * @param       $table
     * @param array $options
     * @return bool|int|string
     */
    public function selectInsert($fields, $table, $options = [])
    {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }
        array_walk($fields, [
            $this,
            'parseKey'
        ]);
        $sql = 'INSERT INTO ' . $this->parseTable($table) . ' (' . implode(',', $fields) . ') ';
        $sql .= $this->buildSelectSql($options);
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 更新记录
     *
     * @param $data
     * @param $options
     * @return bool|int|string
     */
    public function update($data, $options)
    {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        $table = $this->parseTable($options['table']);
        $sql = 'UPDATE ' . $table . $this->parseSet($data);
        if (strpos($table, ',')) {
            // 多表更新支持JOIN操作
            $sql .= $this->parseJoin(!empty($options['join']) ? $options['join'] : '');
        }
        $sql .= $this->parseWhere(!empty($options['where']) ? $options['where'] : '');
        if (!strpos($table, ',')) {
            // 单表更新支持order和lmit
            $sql .= $this->parseOrder(!empty($options['order']) ? $options['order'] : '') . $this->parseLimit(!empty($options['limit']) ? $options['limit'] : '');
        }
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 删除记录
     *
     * @param array $options
     * @return bool|int|string
     */
    public function delete($options = [])
    {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        $table = $this->parseTable($options['table']);
        $sql = 'DELETE FROM ' . $table;
        if (strpos($table, ',')) {
            // 多表删除支持USING和JOIN操作
            if (!empty($options['using'])) {
                $sql .= ' USING ' . $this->parseTable($options['using']) . ' ';
            }
            $sql .= $this->parseJoin(!empty($options['join']) ? $options['join'] : '');
        }
        $sql .= $this->parseWhere(!empty($options['where']) ? $options['where'] : '');
        if (!strpos($table, ',')) {
            // 单表删除支持order和limit
            $sql .= $this->parseOrder(!empty($options['order']) ? $options['order'] : '') . $this->parseLimit(!empty($options['limit']) ? $options['limit'] : '');
        }
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * 查找记录
     *
     * @param array $options
     * @return array|bool|string
     */
    public function select($options = [])
    {
        $this->model = $options['model'];
        $this->parseBind(!empty($options['bind']) ? $options['bind'] : []);
        $sql = $this->buildSelectSql($options);
        $result = $this->query($sql, !empty($options['fetch_sql']) ? true : false);
        return $result;
    }

    /**
     * 生成查询SQL
     *
     * @param array $options
     * @return string
     */
    public function buildSelectSql($options = [])
    {
        if (isset($options['page'])) {
            // 根据页数计算limit
            list ($page, $listRows) = $options['page'];
            $page = $page > 0 ? $page : 1;
            $listRows = $listRows > 0 ? $listRows : (is_numeric($options['limit']) ? $options['limit'] : 20);
            $offset = $listRows * ($page - 1);
            $options['limit'] = $offset . ',' . $listRows;
        }
        $sql = $this->parseSql($this->selectSql, $options);
        return $sql;
    }

    /**
     * 替换SQL语句中表达式
     *
     * @param       $sql
     * @param array $options
     * @return mixed
     */
    public function parseSql($sql, $options = [])
    {
        $sql = str_replace([
            '%TABLE%',
            '%DISTINCT%',
            '%FIELD%',
            '%JOIN%',
            '%WHERE%',
            '%GROUP%',
            '%HAVING%',
            '%ORDER%',
            '%LIMIT%',
            '%UNION%',
            '%LOCK%',
            '%COMMENT%',
            '%FORCE%'
        ], [
            $this->parseTable($options['table']),
            $this->parseDistinct(isset($options['distinct']) ? $options['distinct'] : false),
            $this->parseField(!empty($options['field']) ? $options['field'] : '*'),
            $this->parseJoin(!empty($options['join']) ? $options['join'] : ''),
            $this->parseWhere(!empty($options['where']) ? $options['where'] : ''),
            $this->parseGroup(!empty($options['group']) ? $options['group'] : ''),
            $this->parseHaving(!empty($options['having']) ? $options['having'] : ''),
            $this->parseOrder(!empty($options['order']) ? $options['order'] : ''),
            $this->parseLimit(!empty($options['limit']) ? $options['limit'] : ''),
            $this->parseUnion(!empty($options['union']) ? $options['union'] : ''),
            $this->parseLock(isset($options['lock']) ? $options['lock'] : false),
            $this->parseComment(!empty($options['comment']) ? $options['comment'] : ''),
            $this->parseForce(!empty($options['force']) ? $options['force'] : '')
        ], $sql);
        return $sql;
    }

    /**
     * 获取最近一次查询的sql语句
     *
     * @param string $model
     * @return mixed|string
     */
    public function getLastSql($model = '')
    {
        return $model ? $this->modelSql[$model] : $this->queryStr;
    }

    /**
     * 获取最近插入的ID
     *
     * @return null
     */
    public function getInsertId()
    {
        return $this->lastInsID;
    }

    /**
     * 获取最近的错误信息
     *
     * @return string
     */
    public function getError()
    {
        return $this->errorInfo;
    }

    /**
     * SQL指令安全过滤
     *
     * @param $str
     * @return string
     */
    public function escapeString($str)
    {
        return addslashes($str);
    }

    /**
     * 设置当前操作模型
     *
     * @param $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }

    /**
     * 数据库调试 记录当前SQL
     *
     * @param $start
     */
    protected function debug($start)
    {
        if ($this->config['debug']) {
            // 开启数据库调试模式
            if ($start) {
                App::debug($this->queryStr, 'sql.debug');
            } else {
                $this->modelSql[$this->model] = $this->queryStr;
            }
        }
    }

    /**
     * 初始化数据库连接
     *
     * @param bool $master
     * @throws \Think\Exception
     */
    protected function initConnect($master = true)
    {
        // 默认单数据库
        if (!$this->linkId) {
            $this->linkId = $this->connect();
        }
    }

    /**
     * 析构方法
     */
    public function __destruct()
    {
        // 释放查询
        if ($this->PDOStatement) {
            $this->free();
        }
        // 关闭连接
        $this->close();
    }
}

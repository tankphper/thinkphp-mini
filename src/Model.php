<?php
namespace Think;

use Think\Reporter\ErrorReporter;
use Think\Traits\ErrorTrait;
use Think\Traits\InstanceTrait;

class Model implements ErrorReporter
{

    use ErrorTrait, InstanceTrait;

    // 默认写入模型数据
    const MODEL_INSERT = 1;
    // 更新模型数据
    const MODEL_UPDATE = 2;
    // 包含上面两种方式
    const MODEL_BOTH = 3;

    // 必须验证
    const MUST_VALIDATE = 1;
    // 默认表单存在字段则验证
    const EXISTS_VALIDATE = 0;
    // 表单值不为空则验证
    const VALUE_VALIDATE = 2;

    // 当前数据库操作对象
    protected $db = null;
    // 数据库对象池
    private $dbPool = [];
    // 主键名称
    protected $pk = 'id';
    // 主键是否自动增长
    protected $autoinc = false;
    // 数据表前缀
    protected $tablePrefix = null;
    // 模型名称
    protected $modelName = '';
    // 数据库名称
    protected $dbName = '';
    // 数据库配置
    protected $config = '';
    // 数据表名（不包含表前缀）
    protected $tableName = '';
    // 实际数据表名（包含表前缀）
    protected $trueTableName = '';
    // 字段信息
    protected $fields = [];
    // 数据信息
    protected $data = [];
    // 查询表达式参数
    protected $options = [];
    // 自动验证定义
    protected $_validate = [];
    // 自动完成定义
    protected $_auto = [];
    // 命名范围定义
    protected $_scope = [];
    // 是否自动检测数据表字段信息
    protected $autoCheckFields = true;
    // 链操作方法列表
    protected $methods = [
        'strict',
        'order',
        'alias',
        'having',
        'group',
        'lock',
        'distinct',
        'auto',
        'filter',
        'validate',
        'result',
        'token',
        'index',
        'force'
    ];
    // 请求生命周期缓存数据库表字段
    protected static $tableFileds = [];


    /**
     * Model constructor.
     *
     * @param string $modelName
     * @param string $tablePrefix
     * @param string $config
     * @throws Exception
     */
    public function __construct($modelName = '', $tablePrefix = '', $config = '')
    {
        // 获取模型名称
        // 支持 数据库名.模型名的 定义
        if (!empty($modelName)) {
            if (strpos($modelName, '.')) {
                list ($this->dbName, $this->modelName) = explode('.', $modelName);
            } else {
                $this->modelName = $modelName;
            }
        } elseif (empty($this->modelName)) {
            $this->modelName = $this->getModelName();
        }
        // 前缀为 Null 表示没有前缀
        if (is_null($tablePrefix)) {
            $this->tablePrefix = '';
        } elseif ('' != $tablePrefix) {
            $this->tablePrefix = $tablePrefix;
        } elseif (!isset($this->tablePrefix)) {
            $this->tablePrefix = C('DB_PREFIX');
        }
        // 数据库初始化操作
        // 获取数据库操作对象
        // 当前模型有独立的数据库连接信息
        $this->db(0, empty($this->config) ? $config : $this->config, true);
    }

    /**
     * 自动检测数据表信息
     */
    protected function _checkTableInfo()
    {
        // 如果不是Model类 自动记录数据表信息
        // 只在第一次执行记录
        if (empty($this->fields)) {
            // 如果数据表字段没有定义则自动获取
            if (C('DB_FIELDS_CACHE') && !C('APP_DEBUG')) {
                $tableName = $this->getTableName();
                $fields = F('_fields/' . $tableName);
                if ($fields) {
                    $this->fields = $fields;
                    if (!empty($fields['_pk'])) {
                        $this->pk = $fields['_pk'];
                    }
                    return;
                }
            }
            // 没有缓存字段则每次读取数据表信息
            $this->flushField();
        }
    }

    /**
     * 获取字段信息并缓存
     *
     * @return bool
     */
    public function flushField()
    {
        $this->db->setModel($this->modelName);
        $tableName = $this->getTableName();
        if (!empty(static::$tableFileds[$tableName])) {
            $fields = static::$tableFileds[$tableName];
        } else {
            // 缓存不存在则查询数据表信息
            static::$tableFileds[$tableName] = $fields = $this->db->getFields($tableName);
        }
        // 无法获取字段信息
        if (!$fields) {
            return false;
        }
        $this->fields = array_keys($fields);
        unset($this->fields['_pk']);
        foreach ($fields as $key => $val) {
            // 记录字段类型
            $type[$key] = $val['type'];
            if ($val['primary']) {
                // 增加复合主键支持
                if (isset($this->fields['_pk']) && $this->fields['_pk'] != null) {
                    if (is_string($this->fields['_pk'])) {
                        $this->pk = [$this->fields['_pk']];
                        $this->fields['_pk'] = $this->pk;
                    }
                    $this->pk[] = $key;
                    $this->fields['_pk'][] = $key;
                } else {
                    $this->pk = $key;
                    $this->fields['_pk'] = $key;
                }
                if ($val['autoinc']) {
                    $this->autoinc = true;
                }
            }
        }
        // 记录字段类型信息
        $this->fields['_type'] = $type;
        // 缓存开关控制
        if (C('DB_FIELDS_CACHE') && !C('APP_DEBUG')) {
            // 永久缓存数据表信息
            F('_fields/' . $tableName, $this->fields);
        }
    }

    /**
     * 设置数据对象的值
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        // 设置数据对象属性
        $this->data[$name] = $value;
    }

    /**
     * 获取数据对象的值
     *
     * @param $name
     * @return mixed|null
     */
    public function __get($name)
    {
        return $this->data[$name] ?? null;
    }

    /**
     * 检测数据对象的值
     *
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * 销毁数据对象的值
     *
     * @param $name
     */
    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    /**
     * 利用__call方法实现一些特殊的Model方法
     *
     * @param $method
     * @param $args
     * @return $this|mixed|Model|void
     * @throws Exception
     */
    public function __call($method, $args)
    {
        if (in_array(strtolower($method), $this->methods, true)) {
            // 连贯操作的实现
            $this->options[strtolower($method)] = $args[0];
            return $this;
        } elseif (in_array(strtolower($method), [
            'count',
            'sum',
            'min',
            'max',
            'avg'
        ], true)) {
            // 统计查询的实现
            $field = $args[0] ?? '*';
            return $this->getField(strtoupper($method) . '(' . ($field == '*' ? $field : $this->db->parseKey($field, true)) . ') AS tk_' . $method);
        } elseif (strtolower(substr($method, 0, 5)) == 'getby') {
            // 根据某个字段获取记录
            $field = parse_name(substr($method, 5));
            $where[$field] = $args[0];
            return $this->where($where)->find();
        } elseif (strtolower(substr($method, 0, 10)) == 'getfieldby') {
            // 根据某个字段获取记录的某个值
            $name = parse_name(substr($method, 10));
            $where[$name] = $args[0];
            return $this->where($where)->getField($args[1]);
        } elseif (isset($this->_scope[$method])) {
            // 命名范围的单独调用支持
            return $this->scope($method, $args[0]);
        } else {
            E('Method not exist: ' . __CLASS__ . ':' . $method);
            return;
        }
    }

    /**
     * 对保存到数据库的数据进行处理
     *
     * @param $data
     * @return array
     * @throws Exception
     */
    protected function _facade($data)
    {
        // 检查数据字段合法性
        if (!empty($this->fields)) {
            if (!empty($this->options['field'])) {
                $fields = $this->options['field'];
                unset($this->options['field']);
                if (is_string($fields)) {
                    $fields = explode(',', $fields);
                }
            } else {
                $fields = $this->fields;
            }
            foreach ($data as $key => $val) {
                if (!in_array($key, $fields, true)) {
                    if (!empty($this->options['strict'])) {
                        E('Data type invalid: [' . $key . '=>' . $val . ']');
                    }
                    unset($data[$key]);
                } elseif (is_scalar($val)) {
                    // 字段类型检查 和 强制转换
                    $this->_parseType($data, $key);
                }
            }
        }

        // 安全过滤
        if (!empty($this->options['filter'])) {
            $data = array_map($this->options['filter'], $data);
            unset($this->options['filter']);
        }
        $this->_before_write($data);
        return $data;
    }

    /**
     * 写入数据前的回调方法 包括新增和更新
     *
     * @param $data
     */
    protected function _before_write(&$data)
    {
        // TODO
    }

    /**
     * 新增数据
     *
     * @param string $data
     * @param array  $options
     * @param bool   $replace
     * @return bool|mixed
     * @throws Exception
     */
    public function add($data = '', $options = [], $replace = false)
    {
        if (empty($data)) {
            // 没有传递数据，获取当前数据对象的值
            if (!empty($this->data)) {
                $data = $this->data;
                // 重置数据
                $this->data = [];
            } else {
                $this->setError('Data type invalid');
                return false;
            }
        }
        // 数据处理
        $data = $this->_facade($data);
        // 分析表达式
        $options = $this->_parseOptions($options);
        if (false === $this->_before_insert($data, $options)) {
            return false;
        }
        // 写入数据到数据库
        $result = $this->db->insert($data, $options, $replace);
        if (false !== $result && is_numeric($result)) {
            $pk = $this->getPk();
            // 增加复合主键支持
            if (is_array($pk)) {
                return $result;
            }
            $insertId = $this->getInsertId();
            if ($insertId) {
                // 自增主键返回插入ID
                $data[$pk] = $insertId;
                $this->_after_insert($data, $options);
                return $insertId;
            } else {
                $this->_after_insert($data, $options);
            }
        }
        return $result;
    }

    /**
     * 插入数据前的回调方法
     *
     * @param $data
     * @param $options
     */
    protected function _before_insert(&$data, $options)
    {
        // TODO
    }

    /**
     * 插入成功后的回调方法
     *
     * @param $data
     * @param $options
     */
    protected function _after_insert($data, $options)
    {
        // TODO
    }

    /**
     * 批量写入
     *
     * @param       $dataList
     * @param array $options
     * @param bool  $replace
     * @return bool|mixed
     * @throws Exception
     */
    public function addAll($dataList, $options = [], $replace = false)
    {
        if (empty($dataList)) {
            $this->setError('Data type invalid');
            return false;
        }
        // 数据处理
        foreach ($dataList as $key => $data) {
            $dataList[$key] = $this->_facade($data);
        }
        // 分析表达式
        $options = $this->_parseOptions($options);
        // 写入数据到数据库
        $result = $this->db->insertAll($dataList, $options, $replace);
        if (false !== $result) {
            $insertId = $this->getInsertId();
            if ($insertId) {
                return $insertId;
            }
        }
        return $result;
    }

    /**
     * 通过Select方式添加记录
     *
     * @param string $fields
     * @param string $table
     * @param array  $options
     * @return bool
     * @throws Exception
     */
    public function selectAdd($fields = '', $table = '', $options = [])
    {
        // 分析表达式
        $options = $this->_parseOptions($options);
        // 写入数据到数据库
        if (false === $result = $this->db->selectInsert($fields ?: $options['field'], $table ?: $this->getTableName(), $options)) {
            // 数据库插入操作失败
            $this->setError('Operation wrong');
            return false;
        } else {
            // 插入成功
            return $result;
        }
    }

    /**
     * 保存数据
     *
     * @param string $data
     * @param array  $options
     * @return bool
     * @throws Exception
     */
    public function save($data = '', $options = [])
    {
        if (empty($data)) {
            // 没有传递数据，获取当前数据对象的值
            if (!empty($this->data)) {
                $data = $this->data;
                // 重置数据
                $this->data = [];
            } else {
                $this->setError('Data type invalid');
                return false;
            }
        }
        // 数据处理
        $data = $this->_facade($data);
        if (empty($data)) {
            // 没有数据则不执行
            $this->setError('Data type invalid');
            return false;
        }
        // 分析表达式
        $options = $this->_parseOptions($options);
        $pk = $this->getPk();
        if (!isset($options['where'])) {
            // 如果存在主键数据 则自动作为更新条件
            if (is_string($pk) && isset($data[$pk])) {
                $where[$pk] = $data[$pk];
                unset($data[$pk]);
            } elseif (is_array($pk)) {
                // 增加复合主键支持
                foreach ($pk as $field) {
                    if (isset($data[$field])) {
                        $where[$field] = $data[$field];
                    } else {
                        // 如果缺少复合主键数据则不执行
                        $this->setError('Operation wrong');
                        return false;
                    }
                    unset($data[$field]);
                }
            }
            if (!isset($where)) {
                // 如果没有任何更新条件则不执行
                $this->setError('Operation wrong');
                return false;
            } else {
                $options['where'] = $where;
            }
        }
        $pkFirst = is_array($pk) ? $pk[0] : $pk;
        if (is_array($options['where']) && isset($options['where'][$pkFirst])) {
            $pkValue = $options['where'][$pkFirst];
        }
        if (false === $this->_before_update($data, $options)) {
            return false;
        }
        $result = $this->db->update($data, $options);
        if (false !== $result && is_numeric($result)) {
            if (isset($pkValue)) {
                $data[$pkFirst] = $pkValue;
            }
            $this->_after_update($data, $options);
        }
        return $result;
    }

    /**
     * 更新数据前的回调方法
     *
     * @param $data
     * @param $options
     */
    protected function _before_update(&$data, $options)
    {
        // TODO
    }

    /**
     * 更新成功后的回调方法
     *
     * @param $data
     * @param $options
     */
    protected function _after_update($data, $options)
    {
        // TODO
    }

    /**
     * 删除数据
     *
     * @param array $options
     * @return bool
     * @throws Exception
     */
    public function delete($options = [])
    {
        $pk = $this->getPk();
        if (empty($options) && empty($this->options['where'])) {
            // 如果删除条件为空 则删除当前数据对象所对应的记录
            if (!empty($this->data) && isset($this->data[$pk])) {
                return $this->delete($this->data[$pk]);
            } else {
                return false;
            }
        }
        if (is_numeric($options) || is_string($options)) {
            // 根据主键删除记录
            if (strpos($options, ',')) {
                $where[$pk] = [
                    'IN',
                    $options
                ];
            } else {
                $where[$pk] = $options;
            }
            $this->options['where'] = $where;
        }
        // 分析表达式
        $options = $this->_parseOptions();
        if (empty($options['where'])) {
            return false;
        }
        if (is_array($options['where']) && isset($options['where'][$pk])) {
            $pkValue = $options['where'][$pk];
        }
        if (false === $this->_before_delete($options)) {
            return false;
        }
        $result = $this->db->delete($options);
        if (false !== $result && is_numeric($result)) {
            $data = [];
            isset($pkValue) && $data[$pk] = $pkValue;
            $this->_after_delete($data, $options);
        }
        return $result;
    }

    /**
     * 删除数据前的回调方法
     *
     * @param $options
     */
    protected function _before_delete($options)
    {
        // TODO
    }

    /**
     * 删除成功后的回调方法
     *
     * @param $data
     * @param $options
     */
    protected function _after_delete($data, $options)
    {
        // TODO
    }

    /**
     * 查询数据集
     *
     * @param array $options
     * @return array|bool
     * @throws Exception
     */
    public function select($options = [])
    {
        $pk = $this->getPk();
        if (is_string($options) || is_numeric($options)) {
            // 根据主键查询
            if (strpos($options, ',')) {
                $where[$pk] = [
                    'IN',
                    $options
                ];
            } else {
                $where[$pk] = $options;
            }
            $this->options['where'] = $where;
        } elseif (is_array($options) && (count($options) > 0) && is_array($pk)) {
            // 根据复合主键查询
            $count = 0;
            foreach (array_keys($options) as $key) {
                if (is_int($key)) {
                    $count++;
                }
            }
            if ($count == count($pk)) {
                $i = 0;
                foreach ($pk as $field) {
                    $where[$field] = $options[$i];
                    unset($options[$i++]);
                }
                $this->options['where'] = $where;
            } else {
                return false;
            }
        } elseif (false === $options) {
            // 用于子查询 不查询只返回SQL
            $this->options['fetch_sql'] = true;
        }
        // 分析表达式
        $options = $this->_parseOptions();
        // 判断查询缓存
        $cache = $options['cache'] ?? false;
        if ($cache) {
            $key = is_string($cache['key']) ? $cache['key'] : md5(serialize($options));
            $key = ($cache['prefix'] ? $cache['prefix'] . ':' : '') . $key;
            $data = S($key);
            if (false !== $data) {
                return $data;
            }
        }
        $resultSet = $this->db->select($options);
        if (false === $resultSet) {
            return false;
        }
        if (!empty($resultSet)) {
            // 有查询结果
            if (is_string($resultSet)) {
                return $resultSet;
            }
            // 字段映射
            $resultSet = array_map([
                $this,
                '_read_data'
            ], $resultSet);
            $this->_after_select($resultSet, $options);
            if (isset($options['index'])) {
                // 对数据集进行索引
                $index = explode(',', $options['index']);
                foreach ($resultSet as $result) {
                    $_key = $result[$index[0]];
                    if (isset($index[1]) && isset($result[$index[1]])) {
                        $cols[$_key] = $result[$index[1]];
                    } else {
                        $cols[$_key] = $result;
                    }
                }
                $resultSet = $cols;
            }
        }
        if ($cache) {
            S($key, $resultSet, $cache['expire']);
        }
        return $resultSet;
    }

    /**
     * 查询成功后的回调方法
     *
     * @param $resultSet
     * @param $options
     */
    protected function _after_select(&$resultSet, $options)
    {
        // TODO
    }

    /**
     * 生成查询SQL，可用于子查询
     *
     * @return string
     * @throws Exception
     */
    public function buildSql()
    {
        return '( ' . $this->fetchSql(true)->select() . ' )';
    }

    /**
     * 分析表达式
     *
     * @param array $options
     * @param array $data
     * @return array
     * @throws Exception
     */
    protected function _parseOptions($options = [])
    {
        if (is_array($options)) {
            $options = array_merge($this->options, $options);
        }
        if (!isset($options['table'])) {
            // 自动获取表名
            $options['table'] = $this->getTableName();
            $fields = $this->fields;
        } else {
            // 指定数据表 则重新获取字段列表 但不支持类型检测
            $fields = $this->getDbFields();
        }
        // 数据表别名
        if (!empty($options['alias'])) {
            $options['table'] .= ' ' . $options['alias'];
        }
        // 记录操作的模型名称
        $options['model'] = $this->modelName;
        // 字段类型验证
        if (isset($options['where']) && is_array($options['where']) && !empty($fields) && !isset($options['join'])) {
            // 对数组查询条件进行字段类型检查
            foreach ($options['where'] as $key => $val) {
                $key = trim($key);
                if (in_array($key, $fields, true)) {
                    if (is_scalar($val)) {
                        $this->_parseType($options['where'], $key);
                    }
                } elseif (!is_numeric($key) && '_' != substr($key, 0, 1) && false === strpos($key, '.') && false === strpos($key, '(') && false === strpos($key, '|') && false === strpos($key, '&')) {
                    if (!empty($this->options['strict'])) {
                        E('Error query express: [' . $key . '=>' . $val . ']');
                    }
                    unset($options['where'][$key]);
                }
            }
        }
        // 查询过后清空sql表达式组装 避免影响下次查询
        $this->options = [];
        // 表达式过滤
        $this->_options_filter($options);
        return $options;
    }

    /**
     * 表达式过滤回调方法
     *
     * @param $options
     */
    protected function _options_filter(&$options)
    {
        // TODO
    }

    /**
     * 数据类型检测
     *
     * @param $data
     * @param $key
     */
    protected function _parseType(&$data, $key)
    {
        if (!isset($this->options['bind'][':' . $key]) && isset($this->fields['_type'][$key])) {
            $fieldType = strtolower($this->fields['_type'][$key]);
            if (false !== strpos($fieldType, 'enum')) {
                // 支持ENUM类型优先检测
            } elseif (false === strpos($fieldType, 'bigint') && false !== strpos($fieldType, 'int')) {
                $data[$key] = intval($data[$key]);
            } elseif (false !== strpos($fieldType, 'float') || false !== strpos($fieldType, 'double')) {
                $data[$key] = floatval($data[$key]);
            } elseif (false !== strpos($fieldType, 'bool')) {
                $data[$key] = (bool) $data[$key];
            }
        }
    }

    /**
     * 数据读取后的处理
     *
     * @param $data
     * @return mixed
     */
    protected function _read_data($data)
    {
        // 检查字段映射
        if (!empty($this->_map)) {
            foreach ($this->_map as $key => $val) {
                if (isset($data[$val])) {
                    $data[$key] = $data[$val];
                    unset($data[$val]);
                }
            }
        }
        return $data;
    }

    /**
     * 查询数据
     *
     * @param array $options
     * @return array|bool|mixed|null
     * @throws Exception
     */
    public function find($options = [])
    {
        if (is_numeric($options) || is_string($options)) {
            $where[$this->getPk()] = $options;
            $this->options['where'] = $where;
        }
        // 根据复合主键查找记录
        $pk = $this->getPk();
        if (is_array($options) && (count($options) > 0) && is_array($pk)) {
            // 根据复合主键查询
            $count = 0;
            foreach (array_keys($options) as $key) {
                if (is_int($key)) {
                    $count++;
                }
            }
            if ($count == count($pk)) {
                $i = 0;
                foreach ($pk as $field) {
                    $where[$field] = $options[$i];
                    unset($options[$i++]);
                }
                $this->options['where'] = $where;
            } else {
                return false;
            }
        }
        // 总是查找一条记录
        $this->options['limit'] = 1;
        // 分析表达式
        $options = $this->_parseOptions();
        // 判断查询缓存
        $cache = $options['cache'] ?? false;
        if ($cache) {
            $key = is_string($cache['key']) ? $cache['key'] : md5(serialize($options));
            $key = ($cache['prefix'] ? $cache['prefix'] . ':' : '') . $key;
            $data = S($key);
            if (false !== $data) {
                $this->data = $data;
                return $data;
            }
        }
        $resultSet = $this->db->select($options);
        if (false === $resultSet) {
            return false;
        }
        // 查询结果为空
        if (empty($resultSet)) {
            return null;
        }
        if (is_string($resultSet)) {
            return $resultSet;
        }
        // 读取数据后的处理
        $data = $this->_read_data($resultSet[0]);
        $this->_after_find($data, $options);
        $this->data = $data;
        if ($cache) {
            S($key, $data, $cache['expire']);
        }
        return $this->data;
    }

    /**
     * 查询成功的回调方法
     *
     * @param $result
     * @param $options
     */
    protected function _after_find(&$result, $options)
    {
        // TODO
    }

    /**
     * 处理字段映射
     *
     * @param     $data
     * @param int $type
     * @return mixed
     */
    public function parseFieldsMap($data, $type = 1)
    {
        // 检查字段映射
        if (!empty($this->_map)) {
            foreach ($this->_map as $key => $val) {
                if ($type == 1) {
                    // 读取
                    if (isset($data[$val])) {
                        $data[$key] = $data[$val];
                        unset($data[$val]);
                    }
                } else {
                    if (isset($data[$key])) {
                        $data[$val] = $data[$key];
                        unset($data[$key]);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 设置记录的某个字段值
     * 支持使用数据库字段和方法
     *
     * @param        $field
     * @param string $value
     * @return bool
     * @throws Exception
     */
    public function setField($field, $value = '')
    {
        if (is_array($field)) {
            $data = $field;
        } else {
            $data[$field] = $value;
        }
        return $this->save($data);
    }

    /**
     * 字段值增长
     *
     * @param     $field
     * @param int $step
     * @param int $lazyTime
     * @return bool
     * @throws Exception
     */
    public function setInc($field, $step = 1, $lazyTime = 0)
    {
        // 延迟写入
        if ($lazyTime > 0) {
            $condition = $this->options['where'];
            $guid = md5($this->modelName . '_' . $field . '_' . serialize($condition));
            $step = $this->lazyWrite($guid, $step, $lazyTime);
            if (false === $step) {
                // 等待下次写入
                return true;
            }
        }
        return $this->setField($field, [
            'exp',
            $field . '+' . $step
        ]);
    }

    /**
     * 字段值减少
     *
     * @param     $field
     * @param int $step
     * @param int $lazyTime
     * @return bool
     * @throws Exception
     */
    public function setDec($field, $step = 1, $lazyTime = 0)
    {
        // 延迟写入
        if ($lazyTime > 0) {
            $condition = $this->options['where'];
            $guid = md5($this->modelName . '_' . $field . '_' . serialize($condition));
            $step = $this->lazyWrite($guid, $step, $lazyTime);
            if (false === $step) {
                // 等待下次写入
                return true;
            }
        }
        return $this->setField($field, [
            'exp',
            $field . '-' . $step
        ]);
    }

    /**
     * 延时更新检查 返回false表示需要延时
     * 否则返回实际写入的数值
     *
     * @param $guid
     * @param $step
     * @param $lazyTime
     * @return bool|mixed|string
     * @throws Exception
     */
    protected function lazyWrite($guid, $step, $lazyTime)
    {
        // 存在缓存写入数据
        if (false !== ($value = S($guid))) {
            if (REQUEST_TIME > S($guid . '_time') + $lazyTime) {
                // 延时更新时间到了，删除缓存数据 并实际写入数据库
                S($guid, null);
                S($guid . '_time', null);
                return $value + $step;
            } else {
                // 追加数据到缓存
                S($guid, $value + $step);
                return false;
            }
        } else {
            // 没有缓存数据
            S($guid, $step);
            // 计时开始
            S($guid . '_time', REQUEST_TIME);
            return false;
        }
    }

    /**
     * 获取一条记录的某个字段值
     *
     * @param      $field
     * @param null $sepa
     * @return array|mixed|null|string
     * @throws Exception
     */
    public function getField($field, $sepa = null)
    {
        $options['field'] = $field;
        $options = $this->_parseOptions($options);
        // 判断查询缓存
        $cache = $options['cache'] ?? false;
        if ($cache) {
            $key = is_string($cache['key']) ? $cache['key'] : md5($sepa . serialize($options));
            $key = ($cache['prefix'] ? $cache['prefix'] . ':' : '') . $key;
            $data = S($key);
            if (false !== $data) {
                return $data;
            }
        }
        $field = trim($field);
        // 多字段
        if (strpos($field, ',') && false !== $sepa) {
            if (!isset($options['limit'])) {
                $options['limit'] = is_numeric($sepa) ? $sepa : '';
            }
            $resultSet = $this->db->select($options);
            if (!empty($resultSet)) {
                if (is_string($resultSet)) {
                    return $resultSet;
                }
                $_field = explode(',', $field);
                $field = array_keys($resultSet[0]);
                $key1 = array_shift($field);
                $key2 = array_shift($field);
                $cols = [];
                $count = count($_field);
                foreach ($resultSet as $result) {
                    $name = $result[$key1];
                    if (2 == $count) {
                        $cols[$name] = $result[$key2];
                    } else {
                        $cols[$name] = is_string($sepa) ? implode($sepa, array_slice($result, 1)) : $result;
                    }
                }
                if ($cache) {
                    S($key, $cols, $cache['expire']);
                }
                return $cols;
            }
        } else {
            // 查找一条记录，返回数据个数
            // 当sepa指定为true的时候 返回所有数据
            if (true !== $sepa) {
                $options['limit'] = is_numeric($sepa) ? $sepa : 1;
            }
            $result = $this->db->select($options);
            if (!empty($result)) {
                if (is_string($result)) {
                    return $result;
                }
                if (true !== $sepa && 1 == $options['limit']) {
                    $data = reset($result[0]);
                    if ($cache) {
                        S($key, $data, $cache['expire']);
                    }
                    return $data;
                }
                foreach ($result as $val) {
                    $array[] = $val[$field];
                }
                if ($cache) {
                    S($key, $array, $cache['expire']);
                }
                return $array;
            }
        }
        return null;
    }

    /**
     * 创建数据对象 但不保存到数据库
     *
     * @param string $data
     * @param string $type
     * @return array|bool|int|mixed|null|string
     * @throws Exception
     */
    public function create($data = '', $type = '')
    {
        // 如果没有传值默认取POST数据
        if (empty($data)) {
            $data = I('post.');
        } elseif (is_object($data)) {
            $data = get_object_vars($data);
        }
        // 验证数据
        if (empty($data) || !is_array($data)) {
            $this->setError('Data type invalid');
            return false;
        }
        // 新增或更新，复合主键判断
        $pk = $this->getPk();
        is_array($pk) && $pk = $pk[0];
        $type = $type ?: (!empty($data[$pk]) ? self::MODEL_UPDATE : self::MODEL_INSERT);
        // 检查字段映射
        if (!empty($this->_map)) {
            foreach ($this->_map as $key => $val) {
                if (isset($data[$key])) {
                    $data[$val] = $data[$key];
                    unset($data[$key]);
                }
            }
        }
        // 检测提交字段的合法性
        // $this->field('field1,field2...')->create()
        if (isset($this->options['field'])) {
            $fields = $this->options['field'];
            unset($this->options['field']);
        } elseif ($type == self::MODEL_INSERT && isset($this->insertFields)) {
            $fields = $this->insertFields;
        } elseif ($type == self::MODEL_UPDATE && isset($this->updateFields)) {
            $fields = $this->updateFields;
        }
        if (isset($fields)) {
            if (is_string($fields)) {
                $fields = explode(',', $fields);
            }
            // 判断令牌验证字段
            if (C('TOKEN_ON')) {
                $fields[] = C('TOKEN_NAME', null, '__hash__');
            }
            foreach ($data as $key => $val) {
                if (!in_array($key, $fields)) {
                    unset($data[$key]);
                }
            }
        }
        // 数据自动验证
        if (!$this->autoValidation($data, $type)) {
            return false;
        }
        // 表单令牌验证
        if (!$this->autoCheckToken($data)) {
            $this->setError('Form token error');
            return false;
        }
        // 过滤非法字段数据
        if ($this->autoCheckFields) {
            $fields = $this->getDbFields();
            foreach ($data as $key => $val) {
                if (!in_array($key, $fields)) {
                    unset($data[$key]);
                }
            }
        }
        // 创建完成对数据进行自动处理
        $this->autoOperation($data, $type);
        // 赋值当前数据对象
        $this->data = $data;
        // 返回创建的数据以供其他调用
        return $data;
    }

    /**
     * 自动表单令牌验证
     *
     * @param $data
     * @return bool
     */
    public function autoCheckToken($data)
    {
        // 支持使用token(false) 关闭令牌验证
        if (isset($this->options['token']) && !$this->options['token']) {
            return true;
        }
        if (C('TOKEN_ON')) {
            $name = C('TOKEN_NAME', null, '__hash__');
            // 令牌数据无效
            if (!isset($data[$name]) || !isset($_SESSION[$name])) {
                return false;
            }
            // 令牌验证
            list ($key, $value) = explode('_', $data[$name]);
            // 防止重复提交
            if (isset($_SESSION[$name][$key]) && $value && $_SESSION[$name][$key] === $value) {
                unset($_SESSION[$name][$key]); // 验证完成销毁session
                return true;
            }
            // 开启TOKEN重置
            if (C('TOKEN_RESET')) {
                unset($_SESSION[$name][$key]);
            }
            return false;
        }
        return true;
    }

    /**
     * 使用正则验证数据
     *
     * @param $value
     * @param $rule
     * @return bool
     */
    public function regex($value, $rule)
    {
        $validate = [
            'require'  => '/\S+/',
            'email'    => '/^\w+([-+.]\w+)*@\w+([-.]\w+)*\.\w+([-.]\w+)*$/',
            'url'      => '/^http(s?):\/\/(?:[A-za-z0-9-]+\.)+[A-za-z]{2,4}(:\d+)?(?:[\/\?#][\/=\?%\-&~`@[\]\':+!\.#\w]*)?$/',
            'currency' => '/^\d+(\.\d+)?$/',
            'number'   => '/^\d+$/',
            'zip'      => '/^\d{6}$/',
            'integer'  => '/^[-\+]?\d+$/',
            'double'   => '/^[-\+]?\d+(\.\d+)?$/',
            'english'  => '/^[A-Za-z]+$/'
        ];
        // 检查是否有内置的正则表达式
        if (isset($validate[strtolower($rule)])) {
            $rule = $validate[strtolower($rule)];
        }
        return preg_match($rule, $value) === 1;
    }

    /**
     * 自动表单处理
     *
     * @param $data
     * @param $type
     * @return mixed
     */
    private function autoOperation(&$data, $type)
    {
        if (!empty($this->options['auto'])) {
            $_auto = $this->options['auto'];
            unset($this->options['auto']);
        } elseif (!empty($this->_auto)) {
            $_auto = $this->_auto;
        }
        // 自动填充
        if (isset($_auto)) {
            foreach ($_auto as $auto) {
                // 填充因子定义格式
                // array('field','填充内容','填充条件','附加规则',[额外参数])
                if (empty($auto[2])) {
                    // 默认为新增的时候自动填充
                    $auto[2] = self::MODEL_INSERT;
                }
                if ($type == $auto[2] || $auto[2] == self::MODEL_BOTH) {
                    if (empty($auto[3])) {
                        $auto[3] = 'string';
                    }
                    switch (trim($auto[3])) {
                        case 'function': // 使用函数进行填充 字段的值作为参数
                        case 'callback': // 使用回调方法
                            $args = isset($auto[4]) ? (array) $auto[4] : [];
                            if (isset($data[$auto[0]])) {
                                array_unshift($args, $data[$auto[0]]);
                            }
                            if ('function' == $auto[3]) {
                                $data[$auto[0]] = call_user_func_array($auto[1], $args);
                            } else {
                                empty($args) && $args = [''];
                                $data[$auto[0]] = call_user_func_array([
                                    &$this,
                                    $auto[1]
                                ], $args);
                            }
                            break;
                        case 'field': // 用其它字段的值进行填充
                            $data[$auto[0]] = $data[$auto[1]];
                            break;
                        case 'ignore': // 为空忽略
                            if ($auto[1] === $data[$auto[0]]) {
                                unset($data[$auto[0]]);
                            }
                            break;
                        case 'string':
                        default: // 默认作为字符串填充
                            $data[$auto[0]] = $auto[1];
                    }
                    if (isset($data[$auto[0]]) && false === $data[$auto[0]])
                        unset($data[$auto[0]]);
                }
            }
        }
        return $data;
    }

    /**
     * 自动表单验证
     *
     * @param $data
     * @param $type
     * @return bool
     * @throws Exception
     */
    protected function autoValidation($data, $type)
    {
        if (!empty($this->options['validate'])) {
            $_validate = $this->options['validate'];
            unset($this->options['validate']);
        } elseif (!empty($this->_validate)) {
            $_validate = $this->_validate;
        }
        // 属性验证
        // 如果设置了数据自动验证则进行数据验证
        if (isset($_validate)) {
            foreach ($_validate as $key => $val) {
                // 验证因子定义格式
                // array(field,rule,message,condition,type,when,params)
                // 判断是否需要执行验证
                if (empty($val[5]) || ($val[5] == self::MODEL_BOTH && $type < 3) || $val[5] == $type) {
                    $val[3] = isset($val[3]) ? $val[3] : self::EXISTS_VALIDATE;
                    $val[4] = isset($val[4]) ? $val[4] : 'regex';
                    // 判断验证条件
                    switch ($val[3]) {
                        case self::MUST_VALIDATE: // 必须验证 不管表单是否有设置该字段
                            if (false === $this->_validationField($data, $val)) {
                                return false;
                            }
                            break;
                        case self::VALUE_VALIDATE: // 值不为空的时候才验证
                            if ('' != trim($data[$val[0]])) {
                                if (false === $this->_validationField($data, $val)) {
                                    return false;
                                }
                            }
                            break;
                        default: // 默认表单存在该字段就验证
                            if (isset($data[$val[0]])) {
                                if (false === $this->_validationField($data, $val)) {
                                    return false;
                                }
                            }
                    }
                }
            }
        }
        return true;
    }

    /**
     * 验证表单字段 支持批量验证
     * 如果批量验证返回错误的数组信息
     *
     * @param $data
     * @param $val
     * @return bool|void
     * @throws Exception
     */
    protected function _validationField($data, $val)
    {
        if (false === $this->_validationFieldItem($data, $val)) {
            $this->setError(L($val[2]));
            return false;
        }
        return true;
    }

    /**
     * 根据验证因子验证字段
     *
     * @param $data
     * @param $val
     * @return bool
     * @throws Exception
     */
    protected function _validationFieldItem($data, $val)
    {
        switch (strtolower(trim($val[4]))) {
            case 'function': // 使用函数进行验证
            case 'callback': // 调用方法进行验证
                $args = isset($val[6]) ? (array) $val[6] : [];
                if (is_string($val[0]) && strpos($val[0], ','))
                    $val[0] = explode(',', $val[0]);
                if (is_array($val[0])) {
                    // 支持多个字段验证
                    foreach ($val[0] as $field) {
                        $_data[$field] = $data[$field];
                    }
                    array_unshift($args, $_data);
                } else {
                    array_unshift($args, $data[$val[0]]);
                }
                if ('function' == $val[4]) {
                    return call_user_func_array($val[1], $args);
                } else {
                    return call_user_func_array([
                        &$this,
                        $val[1]
                    ], $args);
                }
            case 'confirm': // 验证两个字段是否相同
                return $data[$val[0]] == $data[$val[1]];
            case 'unique': // 验证某个值是否唯一
                if (is_string($val[0]) && strpos($val[0], ',')) {
                    $val[0] = explode(',', $val[0]);
                }
                $map = [];
                if (is_array($val[0])) {
                    // 支持多个字段验证
                    foreach ($val[0] as $field) {
                        $map[$field] = $data[$field];
                    }
                } else {
                    $map[$val[0]] = $data[$val[0]];
                }
                $pk = $this->getPk();
                // 完善编辑的时候验证唯一
                if (!empty($data[$pk]) && is_string($pk)) {
                    $map[$pk] = [
                        'neq',
                        $data[$pk]
                    ];
                }
                if ($this->where($map)->find()) {
                    return false;
                }
                return true;
            default:
                // 检查附加规则
                return $this->check($data[$val[0]], $val[1], $val[4]);
        }
    }

    /**
     * 验证数据 支持 in between equal length regex expire ip_allow ip_deny
     *
     * @param        $value
     * @param        $rule
     * @param string $type
     * @return bool
     */
    public function check($value, $rule, $type = 'regex')
    {
        $type = strtolower(trim($type));
        switch ($type) {
            case 'in':
            case 'notin':
                // 验证是否在某个指定范围之内 逗号分隔字符串或者数组
                $range = is_array($rule) ? $rule : explode(',', $rule);
                return $type == 'in' ? in_array($value, $range) : !in_array($value, $range);
            case 'between':
                // 验证是否在某个范围
            case 'notbetween':
                // 验证是否不在某个范围
                if (is_array($rule)) {
                    $min = $rule[0];
                    $max = $rule[1];
                } else {
                    list ($min, $max) = explode(',', $rule);
                }
                return $type == 'between' ? $value >= $min && $value <= $max : $value < $min || $value > $max;
            case 'equal':
                // 验证是否等于某个值
            case 'notequal':
                // 验证是否等于某个值
                return $type == 'equal' ? $value == $rule : $value != $rule;
            case 'length': // 验证长度
                $length = mb_strlen($value, 'utf-8'); // 当前数据长度
                if (strpos($rule, ',')) { // 长度区间
                    list ($min, $max) = explode(',', $rule);
                    return $length >= $min && $length <= $max;
                } else { // 指定长度
                    return $length == $rule;
                }
            case 'expire':
                list ($start, $end) = explode(',', $rule);
                if (!is_numeric($start)) {
                    $start = strtotime($start);
                }
                if (!is_numeric($end)) {
                    $end = strtotime($end);
                }
                return REQUEST_TIME >= $start && REQUEST_TIME <= $end;
            case 'ip_allow': // IP 操作许可验证
                return in_array(get_client_ip(), explode(',', $rule));
            case 'ip_deny': // IP 操作禁止验证
                return !in_array(get_client_ip(), explode(',', $rule));
            case 'regex':
            default:
                // 默认使用正则验证 可以使用验证类中定义的验证名称
                return $this->regex($value, $rule);
        }
    }

    /**
     * SQL查询
     *
     * @param      $sql
     * @param bool $parse
     * @return mixed
     * @throws Exception
     */
    public function query($sql, $parse = false)
    {
        if (!is_bool($parse) && !is_array($parse)) {
            $parse = func_get_args();
            array_shift($parse);
        }
        $sql = $this->parseSql($sql, $parse);
        return $this->db->query($sql);
    }

    /**
     * 执行SQL语句
     *
     * @param      $sql
     * @param bool $parse
     * @return mixed
     * @throws Exception
     */
    public function execute($sql, $parse = false)
    {
        if (!is_bool($parse) && !is_array($parse)) {
            $parse = func_get_args();
            array_shift($parse);
        }
        $sql = $this->parseSql($sql, $parse);
        return $this->db->execute($sql);
    }

    /**
     * 解析SQL语句
     *
     * @param $sql
     * @param $parse
     * @return null|string|string[]
     * @throws Exception
     */
    protected function parseSql($sql, $parse)
    {
        // 分析表达式
        if (true === $parse) {
            $options = $this->_parseOptions();
            $sql = $this->db->parseSql($sql, $options);
        } elseif (is_array($parse)) {
            // SQL预处理
            $parse = array_map([
                $this->db,
                'escapeString'
            ], $parse);
            $sql = vsprintf($sql, $parse);
        } else {
            $sql = strtr($sql, [
                '__TABLE__'  => $this->getTableName(),
                '__PREFIX__' => $this->tablePrefix
            ]);
            $prefix = $this->tablePrefix;
            $sql = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $sql);
        }
        $this->db->setModel($this->modelName);
        return $sql;
    }

    /**
     * 切换当前的数据库连接
     *
     * @param string $linkNum
     * @param string $config
     * @param bool   $force
     * @return $this|null|void
     * @throws Exception
     */
    public function db($linkNum = '', $config = '', $force = false)
    {
        if ('' === $linkNum && $this->db) {
            return $this->db;
        }
        if (!isset($this->dbPool[$linkNum]) || $force) {
            // 创建一个新的实例，支持读取配置参数
            if (!empty($config) && is_string($config) && false === strpos($config, '/')) {
                $config = C($config);
            }
            $this->dbPool[$linkNum] = Db::getInstance($config);
        } elseif (null === $config) {
            // 关闭数据库连接
            $this->dbPool[$linkNum]->close();
            unset($this->dbPool[$linkNum]);
            return;
        }
        // 切换数据库连接
        $this->db = $this->dbPool[$linkNum];
        // 字段检测
        if (!empty($this->modelName) && $this->autoCheckFields) {
            $this->_checkTableInfo();
        }
        return $this;
    }

    /**
     * 得到当前的数据对象名称
     *
     * @return bool|mixed|string
     */
    public function getModelName()
    {
        if (empty($this->modelName)) {
            $className = get_class($this);
            $pos = strrpos($className, '\\');
            $pos && $className = substr($className, $pos + 1);
            $parseName = parse_name($className);
            $pos = strrpos($parseName, '_');
            if ($pos) {
                $layer = substr($parseName, $pos + 1);
                $parseName = substr($className, 0, strlen($className) - strlen($layer));
            } else {
                $parseName = $className;
            }
            $this->modelName = $parseName;
        }
        return $this->modelName;
    }

    /**
     * 得到完整的数据表名
     *
     * @return string
     */
    public function getTableName()
    {
        if (empty($this->trueTableName)) {
            $tableName = $this->tablePrefix ?: '';
            if (!empty($this->tableName)) {
                $tableName .= $this->tableName;
            } else {
                $tableName .= parse_name($this->modelName);
            }
            $this->trueTableName = strtolower($tableName);
        }
        // 这里不能加默认DB名称
        return ($this->dbName ? $this->dbName . '.' : '') . $this->trueTableName;
    }

    /**
     * 启动事务
     */
    public function startTrans()
    {
        $this->db->startTrans();
        return;
    }

    /**
     * 提交事务
     *
     * @return mixed
     */
    public function commit()
    {
        return $this->db->commit();
    }

    /**
     * 事务回滚
     *
     * @return mixed
     */
    public function rollback()
    {
        return $this->db->rollback();
    }

    /**
     * 返回数据库的错误信息
     *
     * @return mixed
     */
    public function getDbError()
    {
        return $this->db->getError();
    }

    /**
     * 返回最后插入的ID
     *
     * @return mixed
     */
    public function getInsertId()
    {
        return $this->db->getInsertId();
    }

    /**
     * 返回最后执行的sql语句
     *
     * @return mixed
     */
    public function getLastSql()
    {
        return $this->db->getLastSql($this->modelName);
    }

    /**
     * 获取主键名称
     *
     * @return string
     */
    public function getPk()
    {
        return $this->pk;
    }

    /**
     * 获取数据表字段信息
     *
     * @return array|bool
     */
    public function getDbFields()
    {
        // 动态指定表名
        if (isset($this->options['table'])) {
            if (is_array($this->options['table'])) {
                $table = key($this->options['table']);
            } else {
                $table = $this->options['table'];
                if (strpos($table, ')')) {
                    // 子查询
                    return false;
                }
            }
            $fields = $this->db->getFields($table);
            return $fields ? array_keys($fields) : false;
        }
        if ($this->fields) {
            $fields = $this->fields;
            unset($fields['_type'], $fields['_pk']);
            return $fields;
        }
        return false;
    }

    /**
     * 设置数据对象值
     *
     * @param string $data
     * @return $this|array
     * @throws Exception
     */
    public function data($data = '')
    {
        if ('' === $data && !empty($this->data)) {
            return $this->data;
        }
        if (is_object($data)) {
            $data = get_object_vars($data);
        } elseif (is_string($data)) {
            parse_str($data, $data);
        } elseif (!is_array($data)) {
            E('Data type invalid');
        }
        $this->data = $data;
        return $this;
    }

    /**
     * 指定当前的数据表
     *
     * @param $table
     * @return $this
     */
    public function table($table)
    {
        $prefix = $this->tablePrefix;
        if (is_array($table)) {
            $this->options['table'] = $table;
        } elseif (!empty($table)) {
            // 将__TABLE_NAME__替换成带前缀的表名
            $table = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $table);
            $this->options['table'] = $table;
        }
        return $this;
    }

    /**
     * USING支持 用于多表删除
     *
     * @param $using
     * @return $this
     */
    public function using($using)
    {
        $prefix = $this->tablePrefix;
        if (is_array($using)) {
            $this->options['using'] = $using;
        } elseif (!empty($using)) {
            // 将__TABLE_NAME__替换成带前缀的表名
            $using = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $using);
            $this->options['using'] = $using;
        }
        return $this;
    }

    /**
     * 查询SQL组装 join
     *
     * @param        $join
     * @param string $type
     * @return $this
     */
    public function join($join, $type = 'INNER')
    {
        $prefix = $this->tablePrefix;
        if (is_array($join)) {
            foreach ($join as $key => &$_join) {
                $_join = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                    return $prefix . strtolower($match[1]);
                }, $_join);
                $_join = false !== stripos($_join, 'JOIN') ? $_join : $type . ' JOIN ' . $_join;
            }
            $this->options['join'] = $join;
        } elseif (!empty($join)) {
            // 将__TABLE_NAME__字符串替换成带前缀的表名
            $join = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $join);
            $this->options['join'][] = false !== stripos($join, 'JOIN') ? $join : $type . ' JOIN ' . $join;
        }
        return $this;
    }

    /**
     * 查询SQL组装 union
     *
     * @param      $union
     * @param bool $all
     * @return $this
     * @throws Exception
     */
    public function union($union, $all = false)
    {
        if (empty($union)) {
            return $this;
        }
        if ($all) {
            $this->options['union']['_all'] = true;
        }
        if (is_object($union)) {
            $union = get_object_vars($union);
        }
        // 转换union表达式
        if (is_string($union)) {
            $prefix = $this->tablePrefix;
            // 将__TABLE_NAME__字符串替换成带前缀的表名
            $options = preg_replace_callback("/__([A-Z0-9_-]+)__/sU", function ($match) use ($prefix) {
                return $prefix . strtolower($match[1]);
            }, $union);
        } elseif (is_array($union)) {
            if (isset($union[0])) {
                $this->options['union'] = array_merge($this->options['union'], $union);
                return $this;
            } else {
                $options = $union;
            }
        } else {
            E('Data type invalid');
        }
        $this->options['union'][] = $options;
        return $this;
    }

    /**
     * 查询缓存
     *
     * @param bool   $key
     * @param null   $expire
     * @param string $prefix
     * @return $this
     */
    public function cache($key = true, $expire = null, $prefix = '')
    {
        // 增加快捷调用方式 cache(10) 等同于 cache(true, 10)
        if (is_numeric($key) && is_null($expire)) {
            $expire = $key;
            $key = true;
        }
        if (false !== $key)
            $this->options['cache'] = [
                'key'    => $key,
                'expire' => $expire,
                'prefix' => $prefix
            ];
        return $this;
    }

    /**
     * 指定查询字段 支持字段排除
     *
     * @param      $field
     * @param bool $except
     * @return $this
     */
    public function field($field, $except = false)
    {
        // 获取全部字段
        if (true === $field) {
            $fields = $this->getDbFields();
            $field = $fields ?: '*';
        } elseif ($except) {
            // 字段排除
            if (is_string($field)) {
                $field = explode(',', $field);
            }
            $fields = $this->getDbFields();
            $field = $fields ? array_diff($fields, $field) : $field;
        }
        $this->options['field'] = $field;
        return $this;
    }

    /**
     * 调用命名范围
     *
     * @param string $scope
     * @param null   $args
     * @return $this
     */
    public function scope($scope = '', $args = null)
    {
        if ('' === $scope) {
            if (isset($this->_scope['default'])) {
                // 默认的命名范围
                $options = $this->_scope['default'];
            } else {
                return $this;
            }
        } elseif (is_string($scope)) {
            // 支持多个命名范围调用 用逗号分割
            $scopes = explode(',', $scope);
            $options = [];
            foreach ($scopes as $name) {
                if (!isset($this->_scope[$name])) {
                    continue;
                }
                $options = array_merge($options, $this->_scope[$name]);
            }
            if (!empty($args) && is_array($args)) {
                $options = array_merge($options, $args);
            }
        } elseif (is_array($scope)) {
            // 直接传入命名范围定义
            $options = $scope;
        }
        if (is_array($options) && !empty($options)) {
            $this->options = array_merge($this->options, array_change_key_case($options));
        }
        return $this;
    }

    /**
     * 指定查询条件 支持安全过滤
     *
     * @param      $where
     * @param null $parse
     * @return $this
     */
    public function where($where, $parse = null)
    {
        if (!is_null($parse) && is_string($where)) {
            if (!is_array($parse)) {
                $parse = func_get_args();
                array_shift($parse);
            }
            $parse = array_map([
                $this->db,
                'escapeString'
            ], $parse);
            $where = vsprintf($where, $parse);
        } elseif (is_object($where)) {
            $where = get_object_vars($where);
        }
        if (is_string($where) && '' != $where) {
            $map = [];
            $map['_string'] = $where;
            $where = $map;
        }
        if (isset($this->options['where'])) {
            $this->options['where'] = array_merge($this->options['where'], $where);
        } else {
            $this->options['where'] = $where;
        }
        return $this;
    }

    /**
     * 指定查询数量
     *
     * @param      $offset
     * @param null $length
     * @return $this
     */
    public function limit($offset, $length = null)
    {
        if (is_null($length) && strpos($offset, ',')) {
            list ($offset, $length) = explode(',', $offset);
        }
        $this->options['limit'] = intval($offset) . ($length ? ',' . intval($length) : '');
        return $this;
    }

    /**
     * 指定分页
     *
     * @param      $page
     * @param null $listRows
     * @return $this
     */
    public function page($page, $listRows = null)
    {
        if (is_null($listRows) && strpos($page, ',')) {
            list ($page, $listRows) = explode(',', $page);
        }
        $this->options['page'] = [
            intval($page),
            intval($listRows)
        ];
        return $this;
    }

    /**
     * 查询注释
     *
     * @param $comment
     * @return $this
     */
    public function comment($comment)
    {
        $this->options['comment'] = $comment;
        return $this;
    }

    /**
     * 获取执行的SQL语句
     *
     * @param bool $fetch
     * @return $this
     */
    public function fetchSql($fetch = true)
    {
        $this->options['fetch_sql'] = $fetch;
        return $this;
    }

    /**
     * 参数绑定
     *
     * @param      $key
     * @param bool $value
     * @return $this
     */
    public function bind($key, $value = false)
    {
        if (is_array($key)) {
            $this->options['bind'] = $key;
        } else {
            $num = func_num_args();
            if ($num > 2) {
                $params = func_get_args();
                array_shift($params);
                $this->options['bind'][$key] = $params;
            } else {
                $this->options['bind'][$key] = $value;
            }
        }
        return $this;
    }

    /**
     * 设置模型的属性值
     *
     * @param $name
     * @param $value
     * @return $this
     */
    public function setProperty($name, $value)
    {
        if (property_exists($this, $name)) {
            $this->$name = $value;
        }
        return $this;
    }
}

<?php
namespace Think\Model;

use Think\Model;

/**
 * MongoModel 模型类
 *
 * @Author  Tank
 * @Version 2018/11/30
 */
class MongoModel extends Model
{
    /**
     * 主键类型
     */
    const TYPE_OBJECT = 1;

    const TYPE_INT = 2;

    const TYPE_STRING = 3;

    /**
     * 主键名称
     *
     * @var string
     */
    protected $pk = '_id';

    /**
     * _id 类型 1 Object 采用MongoId对象 2 Int 整形 支持自动增长 3 String 字符串Hash
     *
     * @var int
     */
    protected $_idType = self::TYPE_OBJECT;

    /**
     * 主键是否自增
     *
     * @var bool
     */
    protected $_autoinc = true;

    /**
     * Mongo默认关闭字段检测 可以动态追加字段
     *
     * @var bool
     */
    protected $autoCheckFields = false;

    /**
     * 链操作方法列表
     *
     * @var array
     */
    protected $methods = [
        'table',
        'order',
        'auto',
        'filter',
        'validate'
    ];

    /**
     * __call 方法实现特殊的 Model 方法
     *
     * @param string $method
     * @param array  $args
     * @return $this|array|bool|mixed|null|string|Model|void
     * @throws \Think\Exception
     */
    public function __call($method, $args)
    {
        if (in_array(strtolower($method), $this->methods, true)) {
            // 连贯操作的实现
            $this->options[strtolower($method)] = $args[0];
            return $this;
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
        } else {
            E('Method not exist: ' . __CLASS__ . ':' . $method);
            return;
        }
    }

    /**
     * 获取字段信息并缓存 主键和自增信息直接配置
     *
     * @return bool|void
     */
    public function flush()
    {
        // 缓存不存在则查询数据表信息
        $fields = $this->db->getFields();
        // 暂时没有数据无法获取字段信息 下次查询
        if (!$fields) {
            return false;
        }
        $this->fields = array_keys($fields);
        foreach ($fields as $key => $val) {
            // 记录字段类型
            $type[$key] = $val['type'];
        }
        // 记录字段类型信息
        if (C('DB_FIELDTYPE_CHECK')) {
            $this->fields['_type'] = $type;
        }
        // 增加缓存开关控制
        if (C('DB_FIELDS_CACHE') && !C('APP_DEBUG')) {
            // 永久缓存数据表信息
            $dbName = $this->dbName ? $this->dbName : C('DB_NAME');
            F('_fields/' . $dbName . '.' . $this->modelName, $this->fields);
        }
    }

    /**
     * 写入数据前的回调方法 包括新增和更新
     *
     * @param $data
     */
    protected function _before_write(&$data)
    {
        $pk = $this->getPk();
        // 根据主键类型处理主键数据
        if (isset($data[$pk]) && $this->_idType == self::TYPE_OBJECT) {
            $data[$pk] = new \MongoId($data[$pk]);
        }
    }

    /**
     * count统计 配合where连贯操作
     *
     * @return mixed
     * @throws \Think\Exception
     */
    public function count()
    {
        // 分析表达式
        $options = $this->_parseOptions();
        return $this->db->count($options);
    }

    /**
     * 获取唯一值
     *
     * @param       $field
     * @param array $where
     * @return bool
     * @throws \Think\Exception
     */
    public function distinct($field, $where = [])
    {
        // 分析表达式
        $this->options = $this->_parseOptions();
        $this->options['where'] = array_merge((array) $this->options['where'], $where);

        $command = [
            'distinct' => $this->options['table'],
            'key'      => $field,
            'query'    => $this->options['where']
        ];

        $result = $this->command($command);
        return isset($result['values']) ? $result['values'] : false;
    }

    /**
     * 获取下一ID 用于自动增长型
     *
     * @param string $pk
     * @return mixed
     */
    public function getMongoNextId($pk = '')
    {
        if (empty($pk)) {
            $pk = $this->getPk();
        }
        return $this->db->getMongoNextId($pk);
    }

    /**
     * 新增数据
     *
     * @param string $data
     * @param array  $options
     * @param bool   $replace
     * @return bool|mixed
     * @throws \Think\Exception
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
        // 分析表达式
        $options = $this->_parseOptions($options);
        // 数据处理
        $data = $this->_facade($data);
        if (false === $this->_before_insert($data, $options)) {
            return false;
        }
        // 写入数据到数据库
        $result = $this->db->insert($data, $options, $replace);
        if (false !== $result) {
            $this->_after_insert($data, $options);
            if (isset($data[$this->getPk()])) {
                return $data[$this->getPk()];
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
        // 写入数据到数据库
        // 主键自动增长
        if ($this->_autoinc && $this->_idType == self::TYPE_INT) {
            $pk = $this->getPk();
            if (!isset($data[$pk])) {
                $data[$pk] = $this->db->getMongoNextId($pk);
            }
        }
    }

    /**
     * @return mixed
     */
    public function clear()
    {
        return $this->db->clear();
    }

    /**
     * 查询成功后的回调方法
     *
     * @param $resultSet
     * @param $options
     */
    protected function _after_select(&$resultSet, $options)
    {
        array_walk($resultSet, [
            $this,
            'checkMongoId'
        ]);
    }

    /**
     * 获取 MongoId
     *
     * @param $result
     * @return mixed
     */
    protected function checkMongoId(&$result)
    {
        if (is_object($result['_id'])) {
            $result['_id'] = $result['_id']->__toString();
        }
        return $result;
    }

    /**
     * 表达式过滤回调方法
     *
     * @param $options
     */
    protected function _options_filter(&$options)
    {
        $id = $this->getPk();
        if (isset($options['where'][$id]) && is_scalar($options['where'][$id]) && $this->_idType == self::TYPE_OBJECT) {
            $options['where'][$id] = new \MongoId($options['where'][$id]);
        }
    }

    /**
     * 查询数据
     *
     * @param array $options
     * @return array|bool|mixed|null
     * @throws \Think\Exception
     */
    public function find($options = [])
    {
        if (is_numeric($options) || is_string($options)) {
            $id = $this->getPk();
            $where[$id] = $options;
            $options = [];
            $options['where'] = $where;
        }
        // 分析表达式
        $options = $this->_parseOptions($options);
        $result = $this->db->find($options);
        if (false === $result) {
            return false;
        }
        // 查询结果为空
        if (empty($result)) {
            return null;
        } else {
            $this->checkMongoId($result);
        }
        $this->data = $result;
        $this->_after_find($this->data, $options);
        return $this->data;
    }

    /**
     * 字段值增长
     *
     * @param string $field
     * @param int    $step
     * @return bool
     * @throws \Think\Exception
     */
    public function setInc($field, $step = 1)
    {
        return $this->setField($field, [
            'inc',
            $step
        ]);
    }

    /**
     * 字段值减少
     *
     * @param string $field
     * @param int    $step
     * @return bool
     * @throws \Think\Exception
     */
    public function setDec($field, $step = 1)
    {
        return $this->setField($field, [
            'inc',
            '-' . $step
        ]);
    }

    /**
     * 获取一条记录的某个字段值
     *
     * @param string $field
     * @param null   $sepa
     * @return array|mixed|null|string
     * @throws \Think\Exception
     */
    public function getField($field, $sepa = null)
    {
        $options['field'] = $field;
        $options = $this->_parseOptions($options);
        if (strpos($field, ',')) {
            // 多字段
            if (is_numeric($sepa)) {
                // 限定数量
                $options['limit'] = $sepa;
                // 重置为null 返回数组
                $sepa = null;
            }
            $resultSet = $this->db->select($options);
            if (!empty($resultSet)) {
                $_field = explode(',', $field);
                $field = array_keys($resultSet[0]);
                $key = array_shift($field);
                $key2 = array_shift($field);
                $cols = [];
                $count = count($_field);
                foreach ($resultSet as $result) {
                    $name = $result[$key];
                    if (2 == $count) {
                        $cols[$name] = $result[$key2];
                    } else {
                        $cols[$name] = is_null($sepa) ? $result : implode($sepa, $result);
                    }
                }
                return $cols;
            }
        } else {
            // 返回数据个数
            if (true !== $sepa) {
                // 当sepa指定为true的时候 返回所有数据
                $options['limit'] = is_numeric($sepa) ? $sepa : 1;
            } // 查找符合的记录
            $result = $this->db->select($options);
            if (!empty($result)) {
                if (1 == $options['limit'])
                    return reset($result)[$field];
                foreach ($result as $val) {
                    $array[] = $val[$field];
                }
                return $array;
            }
        }
        return null;
    }

    /**
     * 执行Mongo指令
     *
     * @param       $command
     * @param array $options
     * @return mixed
     * @throws \Think\Exception
     */
    public function command($command, $options = [])
    {
        $options = $this->_parseOptions($options);
        return $this->db->command($command, $options);
    }

    /**
     * 执行MongoCode
     *
     * @param       $code
     * @param array $args
     * @return mixed
     */
    public function mongoCode($code, $args = [])
    {
        return $this->db->execute($code, $args);
    }

    /**
     * 数据库切换后回调方法
     */
    protected function _after_db()
    {
        // 切换Collection
        $this->db->switchCollection($this->getTableName(), $this->dbName ? $this->dbName : C('db_name'));
    }

    /**
     * 得到完整的数据表名 Mongo表名不带dbName
     *
     * @return string
     */
    public function getTableName()
    {
        if (empty($this->trueTableName)) {
            $tableName = !empty($this->tablePrefix) ? $this->tablePrefix : '';
            if (!empty($this->tableName)) {
                $tableName .= $this->tableName;
            } else {
                $tableName .= parse_name($this->modelName);
            }
            $this->trueTableName = strtolower($tableName);
        }
        return $this->trueTableName;
    }

    /**
     * 分组查询
     *
     * @param       $key
     * @param       $init
     * @param       $reduce
     * @param array $option
     * @return mixed
     * @throws \Think\Exception
     */
    public function group($key, $init, $reduce, $option = [])
    {
        $option = $this->_parseOptions($option);

        // 合并查询条件
        if (isset($option['where'])) {
            $option['condition'] = array_merge((array) $option['condition'], $option['where']);
        }
        return $this->db->group($key, $init, $reduce, $option);
    }

    /**
     * 返回Mongo运行错误信息
     *
     * @return mixed
     */
    public function getLastError()
    {
        return $this->db->command([
            'getLastError' => 1
        ]);
    }

    /**
     * 返回指定集合的统计信息，包括数据大小、已分配的存储空间和索引的大小
     *
     * @return mixed
     * @throws \Think\Exception
     */
    public function status()
    {
        $option = $this->_parseOptions();
        return $this->db->command([
            'collStats' => $option['table']
        ]);
    }

    /**
     * 取得当前数据库的对象
     *
     * @return mixed
     */
    public function getDB()
    {
        return $this->db->getDB();
    }

    /**
     * 取得集合对象，可以进行创建索引等查询
     *
     * @return mixed
     */
    public function getCollection()
    {
        return $this->db->getCollection();
    }
}
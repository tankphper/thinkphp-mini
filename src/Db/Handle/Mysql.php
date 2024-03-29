<?php
namespace Think\Db\Handle;

use Think\Db\AbstractDb;

class Mysql extends AbstractDb
{

    /**
     * 组装 PDO 连接的 DSN 信息
     *
     * @param array $config
     * @return string|void
     */
    protected function buildDsn($config)
    {
        $dsn = 'mysql:dbname=' . $config['database'] . ';host=' . $config['hostname'];
        if (!empty($config['hostport'])) {
            $dsn .= ';port=' . $config['hostport'];
        } elseif (!empty($config['socket'])) {
            $dsn .= ';unix_socket=' . $config['socket'];
        }
        if (!empty($config['charset'])) {
            // 为兼容各版本PHP,用两种方式设置编码
            $this->options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $config['charset'];
            $dsn .= ';charset=' . $config['charset'];
        }
        return $dsn;
    }

    /**
     * 取得数据表的字段信息
     *
     * @param $tableName
     * @return array
     * @throws \Think\Exception
     */
    public function getFields($tableName)
    {
        $this->initConnect(true);
        list ($tableName) = explode(' ', $tableName);
        if (strpos($tableName, '.')) {
            list ($dbName, $tableName) = explode('.', $tableName);
            $sql = 'SHOW COLUMNS FROM `' . $dbName . '`.`' . $tableName . '`';
        } else {
            $sql = 'SHOW COLUMNS FROM `' . $tableName . '`';
        }
        $result = $this->query($sql);
        $fields = [];
        if ($result) {
            foreach ($result as $key => $val) {
                if (\PDO::CASE_LOWER != $this->linkId->getAttribute(\PDO::ATTR_CASE)) {
                    $val = array_change_key_case($val, CASE_LOWER);
                }
                $fields[$val['field']] = [
                    'name'    => $val['field'],
                    'type'    => $val['type'],
                    'notnull' => (bool) ($val['null'] === ''),
                    // not null is empty, null is yes
                    'default' => $val['default'],
                    'primary' => (strtolower($val['key']) == 'pri'),
                    'autoinc' => (strtolower($val['extra']) == 'auto_increment')
                ];
            }
        }
        return $fields;
    }

    /**
     * 取得数据库的表信息
     *
     * @param string $dbName
     * @return array
     * @throws \Think\Exception
     */
    public function getTables($dbName = '')
    {
        $sql = !empty($dbName) ? 'SHOW TABLES FROM ' . $dbName : 'SHOW TABLES ';
        $result = $this->query($sql);
        $info = [];
        foreach ($result as $key => $val) {
            $info[$key] = current($val);
        }
        return $info;
    }

    /**
     * 字段和表名处理
     *
     * @param      $key
     * @param bool $strict
     * @return mixed|string
     */
    public function parseKey($key, $strict = false)
    {
        $key = trim($key);
        if ($strict && !preg_match('/^[\w\.\*]+$/', $key)) {
            E('Not support data:' . $key);
        }
        if ($strict || (!is_numeric($key) && !preg_match('/[,\'\"\*\(\)`.\s]/', $key))) {
            $key = '`' . $key . '`';
        }
        return $key;
    }

    /**
     * 随机排序
     *
     * @return string
     */
    protected function parseRand()
    {
        return 'rand()';
    }

    /**
     * 批量插入记录
     *
     * @param       $dataSet
     * @param array $options
     * @param bool  $replace
     * @return bool|int|string
     * @throws \Think\Exception
     */
    public function insertAll($dataSet, $options = [], $replace = false)
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
            $values[] = '(' . implode(',', $value) . ')';
        }
        // 兼容数字传入方式
        $replace = (is_numeric($replace) && $replace > 0) ? true : $replace;
        $sql = (true === $replace ? 'REPLACE' : 'INSERT IGNORE') . ' INTO ' . $this->parseTable($options['table']) . ' (' . implode(',', $fields) . ') VALUES ' . implode(',', $values) . $this->parseDuplicate($replace);
        $sql .= $this->parseComment(!empty($options['comment']) ? $options['comment'] : '');
        return $this->execute($sql, !empty($options['fetch_sql']) ? true : false);
    }

    /**
     * ON DUPLICATE KEY UPDATE 分析
     *
     * @param mixed $duplicate
     * @return string
     */
    protected function parseDuplicate($duplicate)
    {
        // 布尔值或空则返回空字符串
        if (is_bool($duplicate) || empty($duplicate)) {
            return '';
        }
        if (is_string($duplicate)) {
            // field1,field2 转数组
            $duplicate = explode(',', $duplicate);
        } elseif (is_object($duplicate)) {
            // 对象转数组
            $duplicate = get_class_vars($duplicate);
        }
        $updates = [];
        foreach ((array) $duplicate as $key => $val) {
            if (is_numeric($key)) { // array('field1', 'field2', 'field3') 解析为 ON DUPLICATE KEY UPDATE field1=VALUES(field1), field2=VALUES(field2), field3=VALUES(field3)
                $updates[] = $this->parseKey($val) . "=VALUES(" . $this->parseKey($val) . ")";
            } else {
                // 兼容标量传值方式
                if (is_scalar($val)) {
                    $val = [
                        'value',
                        $val
                    ];
                }
                if (!isset($val[1])) {
                    continue;
                }
                switch ($val[0]) {
                    case 'exp': // 表达式
                        $updates[] = $this->parseKey($key) . "=($val[1])";
                        break;
                    case 'value': // 值
                    default:
                        $name = count($this->bind);
                        $updates[] = $this->parseKey($key) . "=:" . $name;
                        $this->bindParam($name, $val[1]);
                        break;
                }
            }
        }
        if (empty($updates)) {
            return '';
        }
        return " ON DUPLICATE KEY UPDATE " . join(', ', $updates);
    }
}

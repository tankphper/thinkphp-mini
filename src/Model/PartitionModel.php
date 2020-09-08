<?php
namespace Think\Model;

use Think\Model;

/**
 * 分表模型扩展
 */
class PartitionModel extends Model
{

    /**
     * 分表规则
     *
     * @var array
     */
    protected $partition = [];

    /**
     * 获取分表信息
     *
     * @param null $field
     * @return string
     */
    public function getPartition($field = null)
    {
        if (is_null($field)) {
            return '';
        }
        switch ($this->partition['type']) {
            case 'id':
                // 按照id范围分表
                $step = $this->partition['expr'];
                $seq = floor($field / $step) + 1;
                break;
            case 'year':
                // 按照年份分表
                if (!is_numeric($field)) {
                    $field = strtotime($field);
                }
                $seq = date('Y', $field) - $this->partition['expr'] + 1;
                break;
            case 'mod':
                // 按照id的模数分表
                $seq = ($field % $this->partition['num']) + 1;
                break;
            case 'md5':
                // 按照md5的序列分表
                $seq = (ord(substr(md5($field), 0, 1)) % $this->partition['num']) + 1;
                break;
            default:
                if (function_exists($this->partition['type'])) {
                    // 支持指定函数哈希
                    $fun = $this->partition['type'];
                    $seq = (ord(substr($fun($field), 0, 1)) % $this->partition['num']) + 1;
                } else {
                    // 按照字段的首字母的值分表
                    $seq = (ord($field{0}) % $this->partition['num']) + 1;
                }
        }
        return empty($seq) ? '' : '_' . $seq;
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
            $this->trueTableName = strtolower($tableName) . $this->getPartition();
        }
        return ($this->dbName ? $this->dbName . '.' : '') . $this->trueTableName;
    }
}

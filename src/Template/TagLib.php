<?php
namespace Think\Template;

use Think\Template;

class TagLib
{

    /**
     * 标签库定义XML文件
     *
     * @var string
     */
    protected $xml = '';

    /**
     * 标签定义
     *
     * @var array
     */
    protected $tags = [];

    /**
     * 标签库名称
     *
     * @var string
     */
    protected $tagLib = '';

    /**
     * 标签库标签列表
     *
     * @var array
     */
    protected $tagList = [];

    /**
     * 标签库分析数组
     *
     * @var array
     */
    protected $parse = [];

    /**
     * 标签库是否有效
     *
     * @var bool
     */
    protected $valid = false;

    /**
     * 当前模板对象
     *
     * @var Template
     */
    protected $tpl;

    /**
     * 逻辑操作符
     *
     * @var array
     */
    protected $comparison = [
        ' nheq ' => ' !== ',
        ' heq '  => ' === ',
        ' neq '  => ' != ',
        ' eq '   => ' == ',
        ' egt '  => ' >= ',
        ' gt '   => ' > ',
        ' elt '  => ' <= ',
        ' lt '   => ' < '
    ];

    /**
     * TagLib constructor.
     */
    public function __construct()
    {
        $this->tagLib = strtolower(substr(get_class($this), 6));
        $this->tpl = new Template();
    }

    /**
     * TagLib 标签属性分析 返回标签属性数组
     *
     * @param $attr
     * @param $tag
     * @return array
     * @throws \Think\Exception
     */
    public function parseXmlAttr($attr, $tag)
    {
        // XML解析安全过滤
        $attr = str_replace('&', '___', $attr);
        $xml = '<tpl><tag ' . $attr . ' /></tpl>';
        $xml = simplexml_load_string($xml);
        if (!$xml) {
            E('Xml tag error: ' . $attr);
        }
        $xml = (array) ($xml->tag->attributes());
        if (isset($xml['@attributes'])) {
            $array = array_change_key_case($xml['@attributes']);
            if ($array) {
                $tag = strtolower($tag);
                if (!isset($this->tags[$tag])) {
                    // 检测是否存在别名定义
                    foreach ($this->tags as $key => $val) {
                        if (isset($val['alias']) && in_array($tag, explode(',', $val['alias']))) {
                            $item = $val;
                            break;
                        }
                    }
                } else {
                    $item = $this->tags[$tag];
                }
                $attrs = explode(',', $item['attr']);
                if (isset($item['must'])) {
                    $must = explode(',', $item['must']);
                } else {
                    $must = [];
                }
                foreach ($attrs as $name) {
                    if (isset($array[$name])) {
                        $array[$name] = str_replace('___', '&', $array[$name]);
                    } elseif (false !== array_search($name, $must)) {
                        E('Param error: ' . $name);
                    }
                }
                return $array;
            }
        } else {
            return [];
        }
    }

    /**
     * 解析条件表达式
     *
     * @param $condition
     * @return mixed|null|string|string[]
     */
    public function parseCondition($condition)
    {
        $condition = str_ireplace(array_keys($this->comparison), array_values($this->comparison), $condition);
        $condition = preg_replace('/\$(\w+):(\w+)\s/is', '$\\1->\\2 ', $condition);
        // 识别为数组
        $condition = preg_replace('/\$(\w+)\.(\w+)\s/is', '$\\1["\\2"] ', $condition);
        return $condition;
    }

    /**
     * 自动识别构建变量
     *
     * @param $name
     * @return string
     */
    public function autoBuildVar($name)
    {
        if (strpos($name, '.')) {
            $vars = explode('.', $name);
            $var = array_shift($vars);
            // 识别为数组
            $name = '$' . $var;
            foreach ($vars as $key => $val) {
                if (0 === strpos($val, '$')) {
                    $name .= '["{' . $val . '}"]';
                } else {
                    $name .= '["' . $val . '"]';
                }
            }
        } elseif (strpos($name, ':')) {
            // 额外的对象方式支持
            $name = '$' . str_replace(':', '->', $name);
        } elseif (!defined($name)) {
            $name = '$' . $name;
        }
        return $name;
    }

    /**
     * 获取标签定义
     *
     * @return array
     */
    public function getTags()
    {
        return $this->tags;
    }
}
<?php
namespace Think;

/**
 * 内置模板引擎类
 * 支持XML标签和普通标签的模板解析
 * 编译型模板引擎 支持动态缓存
 */
class Template
{

    /**
     * 模板页面中引入的标签库列表
     *
     * @var array
     */
    protected $tagLib = [];

    /**
     * 当前模板文件
     *
     * @var string
     */
    protected $templateFile = '';

    /**
     * 模板变量
     *
     * @var array
     */
    public $tVar = [];

    /**
     * 配置信息
     *
     * @var array
     */
    public $config = [];

    /**
     * 特殊标签
     *
     * @var array
     */
    private $literal = [];

    /**
     * 块儿
     *
     * @var array
     */
    private $block = [];

    /**
     * Template constructor.
     */
    public function __construct()
    {
        $this->config['tmpl_cache_path'] = C('TMPL_CACHE_PATH');
        $this->config['tmpl_file_suffix'] = C('TMPL_FILE_SUFFIX');
        $this->config['tmpl_cache_suffix'] = C('TMPL_CACHE_SUFFIX');
        $this->config['tmpl_cache_expire'] = C('TMPL_CACHE_EXPIRE');
        $this->config['tmpl_begin'] = $this->stripPreg(C('TMPL_BEGIN_DELIM'));
        $this->config['tmpl_end'] = $this->stripPreg(C('TMPL_END_DELIM'));
        $this->config['taglib_begin'] = $this->stripPreg(C('TAGLIB_BEGIN'));
        $this->config['taglib_end'] = $this->stripPreg(C('TAGLIB_END'));
    }

    /**
     * 转义标签
     *
     * @param $str
     * @return mixed
     */
    private function stripPreg($str)
    {
        return str_replace([
            '{',
            '}',
        ], [
            '\{',
            '\}',
        ], $str);
    }

    /**
     * 获取模板变量
     *
     * @param $name
     * @return bool|mixed
     */
    public function get($name)
    {
        if (isset($this->tVar[$name])) {
            return $this->tVar[$name];
        } else {
            return false;
        }
    }

    /**
     * 设置模板变量
     *
     * @param $name
     * @param $value
     */
    public function set($name, $value)
    {
        $this->tVar[$name] = $value;
    }

    /**
     * 加载模板
     *
     * @param $templateFile
     * @param $templateVar
     * @throws Exception
     */
    public function fetch($templateFile, $templateVar)
    {
        $this->tVar = $templateVar;
        $templateCacheFile = $this->loadTemplate($templateFile);
        Storage::load($templateCacheFile, $this->tVar);
    }

    /**
     * 加载主模板并缓存
     *
     * @param $templateFile
     * @return string
     * @throws Exception
     */
    public function loadTemplate($templateFile)
    {
        if (is_file($templateFile)) {
            $this->templateFile = $templateFile;
            $tmplContent = file_get_contents($templateFile);
        } else {
            $tmplContent = $templateFile;
        }
        // 根据模版文件名定位缓存文件
        $tmplCacheFile = $this->config['tmpl_cache_path'] . md5($templateFile) . $this->config['tmpl_cache_suffix'];
        // 编译模板内容
        $tmplContent = $this->compiler($tmplContent);
        Storage::put($tmplCacheFile, trim($tmplContent), 'tpl');
        return $tmplCacheFile;
    }

    /**
     * 编译模板文件内容
     *
     * @param $tmplContent
     * @return string
     * @throws Exception
     */
    protected function compiler($tmplContent)
    {
        // 模板解析
        $tmplContent = $this->parse($tmplContent);
        // 还原被替换的Literal标签
        $tmplContent = preg_replace_callback('/<!--###literal(\d+)###-->/is', [
            $this,
            'restoreLiteral'
        ], $tmplContent);
        // 优化生成的php代码
        $tmplContent = str_replace('?><?php', '', $tmplContent);
        // 模版编译过滤标签
        Event::trigger('view_compile', $tmplContent);
        return strip_whitespace($tmplContent);
    }

    /**
     * 模板解析入口
     * 支持普通标签和TagLib解析 支持自定义标签库
     *
     * @param $content
     * @return array|mixed|null|string|string[]
     * @throws Exception
     */
    public function parse($content)
    {
        // 内容为空不解析
        if (empty($content)) {
            return '';
        }
        $begin = $this->config['taglib_begin'];
        $end = $this->config['taglib_end'];
        // 检查include语法
        $content = $this->parseInclude($content);
        // 检查PHP语法
        $content = $this->parsePhp($content);
        // 首先替换literal标签内容
        $content = preg_replace_callback('/' . $begin . 'literal' . $end . '(.*?)' . $begin . '\/literal' . $end . '/is', [
            $this,
            'parseLiteral'
        ], $content);

        // 获取需要引入的标签库列表
        // 标签库只需要定义一次，允许引入多个一次
        // 一般放在文件的最前面
        // 格式：<taglib name="html,mytag..." />
        // 当TAGLIB_LOAD配置为true时才会进行检测
        if (C('TAGLIB_LOAD')) {
            $this->getIncludeTagLib($content);
            if (!empty($this->tagLib)) {
                // 对导入的TagLib进行解析
                foreach ($this->tagLib as $tagLibName) {
                    $this->parseTagLib($tagLibName, $content);
                }
            }
        }
        // 内置标签库 无需使用taglib标签导入就可以使用 并且不需使用标签库XML前缀
        $tagLibs = explode(',', C('TAGLIB_BUILD_IN'));
        foreach ($tagLibs as $tag) {
            $this->parseTagLib($tag, $content, true);
        }
        // 解析普通模板标签 {$tagName}
        $content = preg_replace_callback('/(' . $this->config['tmpl_begin'] . ')([^\d\w\s' . $this->config['tmpl_begin'] . $this->config['tmpl_end'] . '].+?)(' . $this->config['tmpl_end'] . ')/is', [
            $this,
            'parseTag'
        ], $content);
        return $content;
    }

    /**
     * 检查PHP语法
     *
     * @param $content
     * @return null|string|string[]
     * @throws Exception
     */
    protected function parsePhp($content)
    {
        if (ini_get('short_open_tag')) {
            // 开启短标签的情况要将<?标签用echo方式输出 否则无法正常输出xml标识
            $content = preg_replace('/(<\?(?!php|=|$))/i', '<?php echo \'\\1\'; ?>' . "\n", $content);
        }
        return $content;
    }

    /**
     * 解析模板中的include标签
     *
     * @param      $content
     * @param bool $extend
     * @return array|mixed|null|string|string[]
     * @throws Exception
     */
    protected function parseInclude($content, $extend = true)
    {
        // 解析继承
        if ($extend) {
            $content = $this->parseExtend($content);
        }
        // 读取模板中的include标签
        $find = preg_match_all('/' . $this->config['taglib_begin'] . 'include\s(.+?)\s*?\/' . $this->config['taglib_end'] . '/is', $content, $matches);
        if ($find) {
            for ($i = 0; $i < $find; $i++) {
                $include = $matches[1][$i];
                $array = $this->parseXmlAttrs($include);
                $file = $array['file'];
                unset($array['file']);
                $content = str_replace($matches[0][$i], $this->parseIncludeItem($file, $array, $extend), $content);
            }
        }
        return $content;
    }

    /**
     * 解析模板中的extend标签
     *
     * @param $content
     * @return array|mixed|null|string|string[]
     * @throws Exception
     */
    protected function parseExtend($content)
    {
        $begin = $this->config['taglib_begin'];
        $end = $this->config['taglib_end'];
        // 读取模板中的继承标签
        $find = preg_match('/' . $begin . 'extend\s(.+?)\s*?\/' . $end . '/is', $content, $matches);
        if ($find) {
            // 替换extend标签
            $content = str_replace($matches[0], '', $content);
            // 记录页面中的block标签
            preg_replace_callback('/' . $begin . 'block\sname=[\'"](.+?)[\'"]\s*?' . $end . '(.*?)' . $begin . '\/block' . $end . '/is', [
                $this,
                'parseBlock'
            ], $content);
            // 读取继承模板
            $array = $this->parseXmlAttrs($matches[1]);
            $content = $this->parseTemplateName($array['name']);
            // 对继承模板中的include进行分析
            $content = $this->parseInclude($content, false);
            // 替换block标签
            $content = $this->replaceBlock($content);
        } else {
            $content = preg_replace_callback('/' . $begin . 'block\sname=[\'"](.+?)[\'"]\s*?' . $end . '(.*?)' . $begin . '\/block' . $end . '/is', function ($match) {
                return stripslashes($match[2]);
            }, $content);
        }
        return $content;
    }

    /**
     * 分析XML属性
     *
     * @param $attrs
     * @return array
     * @throws Exception
     */
    private function parseXmlAttrs($attrs)
    {
        $xml = '<tpl><tag ' . $attrs . ' /></tpl>';
        $xml = simplexml_load_string($xml);
        if (!$xml) {
            E('Xml tag error');
        }
        $xml = (array) ($xml->tag->attributes());
        $array = array_change_key_case($xml['@attributes']);
        return $array;
    }

    /**
     * 替换页面中的literal标签
     *
     * @param $content
     * @return string
     */
    private function parseLiteral($content)
    {
        if (is_array($content)) {
            $content = $content[1];
        }
        if (trim($content) == '') {
            return '';
        }
        $i = count($this->literal);
        $parseStr = "<!--###literal{$i}###-->";
        $this->literal[$i] = $content;
        return $parseStr;
    }

    /**
     * 还原被替换的literal标签
     *
     * @param $tag
     * @return mixed
     */
    private function restoreLiteral($tag)
    {
        if (is_array($tag)) {
            $tag = $tag[1];
        }
        // 还原literal标签
        $parseStr = $this->literal[$tag];
        // 销毁literal记录
        unset($this->literal[$tag]);
        return $parseStr;
    }

    /**
     * 记录当前页面中的block标签
     *
     * @param        $name
     * @param string $content
     * @return string
     */
    private function parseBlock($name, $content = '')
    {
        if (is_array($name)) {
            $content = $name[2];
            $name = $name[1];
        }
        $this->block[$name] = $content;
        return '';
    }

    /**
     * 替换继承模板中的block标签
     *
     * @param $content
     * @return array|mixed|null|string|string[]
     */
    private function replaceBlock($content)
    {
        static $parse = 0;
        $begin = $this->config['taglib_begin'];
        $end = $this->config['taglib_end'];
        $reg = '/(' . $begin . 'block\sname=[\'"](.+?)[\'"]\s*?' . $end . ')(.*?)' . $begin . '\/block' . $end . '/is';
        if (is_string($content)) {
            do {
                $content = preg_replace_callback($reg, [
                    $this,
                    'replaceBlock'
                ], $content);
            } while ($parse && $parse--);
            return $content;
        } elseif (is_array($content)) {
            // 存在嵌套，进一步解析
            if (preg_match('/' . $begin . 'block\sname=[\'"](.+?)[\'"]\s*?' . $end . '/is', $content[3])) {
                $parse = 1;
                $content[3] = preg_replace_callback($reg, [
                    $this,
                    'replaceBlock'
                ], "{$content[3]}{$begin}/block{$end}");
                return $content[1] . $content[3];
            } else {
                $name = $content[2];
                $content = $content[3];
                $content = $this->block[$name] ?? $content;
                return $content;
            }
        }
    }

    /**
     * 搜索模板页面中包含的TagLib库
     * 并返回列表
     *
     * @param $content
     * @throws Exception
     */
    public function getIncludeTagLib(& $content)
    {
        // 搜索是否有TagLib标签
        $find = preg_match('/' . $this->config['taglib_begin'] . 'taglib\s(.+?)(\s*?)\/' . $this->config['taglib_end'] . '\W/is', $content, $matches);
        if ($find) {
            // 替换TagLib标签
            $content = str_replace($matches[0], '', $content);
            // 解析TagLib标签
            $array = $this->parseXmlAttrs($matches[1]);
            $this->tagLib = explode(',', $array['name']);
        }
        return;
    }

    /**
     * TagLib库解析
     *
     * @param      $tagLib
     * @param      $content
     * @param bool $hide
     */
    public function parseTagLib($tagLib, &$content, $hide = false)
    {
        $begin = $this->config['taglib_begin'];
        $end = $this->config['taglib_end'];
        if (strpos($tagLib, '\\')) {
            // 支持指定标签库的命名空间
            $className = $tagLib;
            $tagLib = substr($tagLib, strrpos($tagLib, '\\') + 1);
        } else {
            $className = 'Think\\Template\TagLib\\' . ucwords($tagLib);
        }
        $tLib = new $className();
        $that = $this;
        foreach ($tLib->getTags() as $name => $val) {
            $tags = [$name];
            // 别名设置
            if (isset($val['alias'])) {
                $tags = explode(',', $val['alias']);
                $tags[] = $name;
            }
            $level = $val['level'] ?? 1;
            $closeTag = $val['close'] ?? true;
            foreach ($tags as $tag) {
                // 实际要解析的标签名称
                $parseTag = !$hide ? $tagLib . ':' . $tag : $tag;
                if (!method_exists($tLib, '_' . $tag)) {
                    // 别名可以无需定义解析方法
                    $tag = $name;
                }
                $n1 = empty($val['attr']) ? '(\s*?)' : '\s([^' . $end . ']*)';
                $this->tempVar = array(
                    $tagLib,
                    $tag
                );
                if (!$closeTag) {
                    $patterns = '/' . $begin . $parseTag . $n1 . '\/(\s*?)' . $end . '/is';
                    $content = preg_replace_callback($patterns, function ($matches) use ($tLib, $tag, $that) {
                        return $that->parseXmlTag($tLib, $tag, $matches[1], $matches[2]);
                    }, $content);
                } else {
                    $patterns = '/' . $begin . $parseTag . $n1 . $end . '(.*?)' . $begin . '\/' . $parseTag . '(\s*?)' . $end . '/is';
                    for ($i = 0; $i < $level; $i++) {
                        $content = preg_replace_callback($patterns, function ($matches) use ($tLib, $tag, $that) {
                            return $that->parseXmlTag($tLib, $tag, $matches[1], $matches[2]);
                        }, $content);
                    }
                }
            }
        }
    }

    /**
     * 解析标签库的标签
     * 需要调用对应的标签库文件解析类
     *
     * @param $tagLib
     * @param $tag
     * @param $attr
     * @param $content
     * @return mixed
     */
    public function parseXmlTag($tagLib, $tag, $attr, $content)
    {
        $parse = '_' . $tag;
        $content = trim($content);
        $tags = $tagLib->parseXmlAttr($attr, $tag);
        return $tagLib->$parse($tags, $content);
    }

    /**
     * 模板标签解析
     * 格式： {TagName:args [|content] }
     *
     * @param $tagStr
     * @return string
     */
    public function parseTag($tagStr)
    {
        if (is_array($tagStr)) {
            $tagStr = $tagStr[2];
        }
        $tagStr = stripslashes($tagStr);
        $flag = substr($tagStr, 0, 1);
        $flag2 = substr($tagStr, 1, 1);
        $name = substr($tagStr, 1);
        if ('$' == $flag && '.' != $flag2 && '(' != $flag2) { // 解析模板变量 格式 {$varName}
            return $this->parseVar($name);
        } elseif ('-' == $flag || '+' == $flag) { // 输出计算
            return '<?php echo ' . $flag . $name . ';?>';
        } elseif (':' == $flag) { // 输出某个函数的结果
            return '<?php echo ' . $name . ';?>';
        } elseif ('~' == $flag) { // 执行某个函数
            return '<?php ' . $name . ';?>';
        } elseif (substr($tagStr, 0, 2) == '//' || (substr($tagStr, 0, 2) == '/*' && substr(rtrim($tagStr), -2) == '*/')) {
            // 注释标签
            return '';
        }
        // 未识别的标签直接返回
        return C('TMPL_BEGIN_DELIM') . $tagStr . C('TMPL_END_DELIM');
    }

    /**
     * 模板变量解析,支持使用函数
     * 格式： {$varname|function1|function2=arg1,arg2}
     *
     * @param $varStr
     * @return mixed|string
     */
    public function parseVar($varStr)
    {
        $varStr = trim($varStr);
        static $varParseMap = [];
        // 如果已经解析过该变量字串，则直接返回变量值
        if (isset($varParseMap[$varStr])) {
            return $varParseMap[$varStr];
        }
        $parseStr = '';
        if (!empty($varStr)) {
            $varArray = explode('|', $varStr);
            // 取得变量名称
            $var = array_shift($varArray);
            if (false !== strpos($var, '.')) {
                // 支持 {$var.property}
                $vars = explode('.', $var);
                $var = array_shift($vars);
                // 识别为数组
                $name = '$' . $var;
                foreach ($vars as $key => $val) {
                    $name .= '["' . $val . '"]';
                }
            } elseif (false !== strpos($var, '[')) {
                // 支持 {$var['key']} 方式输出数组
                $name = '$' . $var;
            } elseif (false !== strpos($var, ':') && false === strpos($var, '(') && false === strpos($var, '::') && false === strpos($var, '?')) {
                // 支持 {$var:property} 方式输出对象的属性
                $var = str_replace(':', '->', $var);
                $name = '$' . $var;
            } else {
                $name = '$' . $var;
            }
            // 对变量使用函数
            if (count($varArray) > 0) {
                $name = $this->parseVarFunction($name, $varArray);
            }else{
                // isset 判断
                $issetStr = "(%s ?? '')";
                $name = sprintf($issetStr, $name);
            }
            $parseStr = '<?php echo (' . $name . '); ?>';
        }
        $varParseMap[$varStr] = $parseStr;
        return $parseStr;
    }

    /**
     * 对模板变量使用函数
     * 格式 {$varname|function1|function2=arg1,arg2}
     *
     * @param $name
     * @param $varArray
     * @return string
     */
    public function parseVarFunction($name, $varArray)
    {
        // 对变量使用函数
        $length = count($varArray);
        for ($i = 0; $i < $length; $i++) {
            $args = explode('=', $varArray[$i], 2);
            // 模板函数过滤
            $fun = trim($args[0]);
            switch ($fun) {
                // $name|default='tank'
                case 'default':
                    $name = '(isset(' . $name . ') && (' . $name . ' !== ""))?(' . $name . '):' . $args[1];
                    break;
                default:
                    if (isset($args[1])) {
                        if (strstr($args[1], '###')) {
                            $args[1] = str_replace('###', $name, $args[1]);
                            $name = "$fun($args[1])";
                        } else {
                            $name = "$fun($name,$args[1])";
                        }
                    } elseif (!empty($args[0])) {
                        $name = "$fun($name)";
                    }
            }
        }
        return $name;
    }

    /**
     * 加载公共模板并缓存 和当前模板在同一路径，否则使用相对路径
     *
     * @param       $tmplPublicName
     * @param array $vars
     * @param       $extend
     * @return array|mixed|null|string|string[]
     * @throws Exception
     */
    private function parseIncludeItem($tmplPublicName, $vars = [], $extend)
    {
        // 分析模板文件名并读取内容
        $parseStr = $this->parseTemplateName($tmplPublicName);
        // 替换变量
        foreach ($vars as $key => $val) {
            $parseStr = str_replace('[' . $key . ']', $val, $parseStr);
        }
        // 再次对包含文件进行模板分析
        return $this->parseInclude($parseStr, $extend);
    }

    /**
     * 分析加载的模板文件并读取内容 支持多个模板文件读取
     *
     * @param $templateName
     * @return string
     */
    private function parseTemplateName($templateName)
    {
        if (substr($templateName, 0, 1) == '$') {
            // 支持加载变量文件名
            $templateName = $this->get(substr($templateName, 1));
        }
        $array = explode(',', $templateName);
        $parseStr = '';
        foreach ($array as $templateName) {
            if (empty($templateName)) {
                continue;
            }
            if (false === strpos($templateName, $this->config['tmpl_file_suffix'])) {
                // 解析规则为：模块@控制器/操作
                $templateName = T($templateName);
            }
            $parseStr .= file_get_contents($templateName);
        }
        return $parseStr;
    }
}

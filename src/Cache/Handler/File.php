<?php
namespace Think\Cache\Handler;

use Think\Cache\AbstractCache;

/**
 * 文件类型缓存类
 */
class File extends AbstractCache
{

    /**
     * 架构函数
     *
     * @access public
     */
    public function __construct(array $options)
    {
        $this->options['temp'] = !empty($options['temp']) ? $options['temp'] : C('DATA_CACHE_PATH');
        $this->options['prefix'] = $options['prefix'] ?? C('DATA_CACHE_PREFIX');
        $this->options['expire'] = $options['expire'] ?? C('DATA_CACHE_TIME');
        $this->options['length'] = $options['length'] ?? 0;
        if (substr($this->options['temp'], -1) != '/') {
            $this->options['temp'] .= '/';
        }
        $this->init();
    }

    /**
     * 初始化检查
     *
     * @return void
     */
    private function init()
    {
        // 创建应用缓存目录
        if (!is_dir($this->options['temp'])) {
            mkdir($this->options['temp']);
        }
    }

    /**
     * 取得变量的存储文件名
     *
     * @param string $name
     * @return string
     */
    private function filename(string $name)
    {
        $name = md5(C('DATA_CACHE_SALT') . $name);
        if (C('DATA_CACHE_SUB_DIR')) {
            // 使用子目录
            $dir = '';
            for ($i = 0; $i < C('DATA_CACHE_DIR_LEVEL'); $i++) {
                $dir .= $name{$i} . '/';
            }
            if (!is_dir($this->options['temp'] . $dir)) {
                mkdir($this->options['temp'] . $dir, 0755, true);
            }
            $filename = $dir . $this->options['prefix'] . $name . '.php';
        } else {
            $filename = $this->options['prefix'] . $name . '.php';
        }
        return $this->options['temp'] . $filename;
    }

    /**
     * 读取缓存
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key)
    {
        $filename = $this->filename($key);
        if (!is_file($filename)) {
            return false;
        }
        $content = file_get_contents($filename);
        if (false !== $content) {
            $expire = (int) substr($content, 8, 12);
            if (0 != $expire && time() > filemtime($filename) + $expire) {
                // 缓存过期删除缓存文件
                unlink($filename);
                return false;
            }
            $content = substr($content, 20, -3);
            if (C('DATA_CACHE_COMPRESS') && function_exists('gzcompress')) {
                // 启用数据压缩
                $content = gzuncompress($content);
            }
            return unserialize($content);
        } else {
            return false;
        }
    }

    /**
     * 写入缓存
     *
     * @param string $key
     * @param        $value
     * @param null   $expire
     * @return bool
     */
    public function set(string $key, $value, $expire = null)
    {
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        $filename = $this->filename($key);
        $data = serialize($value);
        if (C('DATA_CACHE_COMPRESS') && function_exists('gzcompress')) {
            // 数据压缩
            $data = gzcompress($data, 3);
        }
        $check = '';
        $data = "<?php\n//" . sprintf('%012d', $expire) . $check . $data . "\n?>";
        $result = file_put_contents($filename, $data);
        if ($result) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 删除缓存
     *
     * @param string $key
     * @return boolean
     */
    public function del(string $key)
    {
        return unlink($this->filename($key));
    }

    /**
     * 是否存在
     *
     * @param string $key
     * @return bool
     */
    function exists(string $key)
    {
        return false;
    }

    /**
     * 设置超时
     *
     * @param string $key
     * @param int    $timeout
     * @return bool
     */
    function expire(string $key, int $timeout)
    {
        return unlink($this->filename($key));
    }

    /**
     * 原子自增
     *
     * @param string $key
     * @param float  $step
     * @param null   $expire
     * @return false
     */
    function incr(string $key, float $step, $expire = null)
    {
        return false;
    }
}

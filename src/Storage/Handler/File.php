<?php
namespace Think\Storage\Handler;

use Think\Storage;

class File extends Storage
{

    /**
     * @var array
     */
    private $contents = [];

    /**
     * File constructor.
     */
    public function __construct()
    {
    }

    /**
     * 文件内容读取
     *
     * @param        $filename
     * @param string $type
     * @return bool
     */
    public function read($filename, $type = '')
    {
        return $this->get($filename, 'content', $type);
    }

    /**
     * 文件写入
     *
     * @param $filename
     * @param $content
     * @return bool
     * @throws \Think\Exception
     */
    public function put($filename, $content)
    {
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        if (false === file_put_contents($filename, $content)) {
            E('Storage write error: ' . $filename);
        } else {
            $this->contents[$filename] = $content;
            return true;
        }
    }

    /**
     * 文件追加写入
     *
     * @param        $filename
     * @param        $content
     * @param string $type
     * @return bool
     * @throws \Think\Exception
     */
    public function append($filename, $content, $type = '')
    {
        if (is_file($filename)) {
            $content = $this->read($filename, $type) . $content;
        }
        return $this->put($filename, $content, $type);
    }

    /**
     * 加载文件
     *
     * @param      $filename
     * @param null $vars
     */
    public function load($filename, $vars = null)
    {
        if (!is_null($vars)) {
            extract($vars, EXTR_OVERWRITE);
        }
        include $filename;
    }

    /**
     * 文件是否存在
     *
     * @param        $filename
     * @return bool
     */
    public function has($filename)
    {
        return is_file($filename);
    }

    /**
     * 文件删除
     *
     * @param        $filename
     * @param string $type
     * @return bool
     */
    public function unlink($filename)
    {
        unset($this->contents[$filename]);
        return is_file($filename) ? unlink($filename) : false;
    }

    /**
     * 读取文件信息
     *
     * @param        $filename
     * @param        $name
     * @return bool|mixed
     */
    public function get($filename, $name)
    {
        if (!isset($this->contents[$filename])) {
            if (!is_file($filename)) {
                return false;
            }
            $this->contents[$filename] = file_get_contents($filename);
        }
        $content = $this->contents[$filename];
        $info = [
            'mtime'   => filemtime($filename),
            'content' => $content
        ];
        return $info[$name];
    }
}

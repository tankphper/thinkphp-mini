<?php
namespace Think\Upload\Handler;

class Local
{

    /**
     * 上传文件根目录
     *
     * @var string
     */
    private $rootPath;

    /**
     * 本地上传错误信息
     *
     * @var string
     */
    private $error = '';

    /**
     * Local constructor.
     *
     * @param null $config
     */
    public function __construct($config = null)
    {

    }

    /**
     * 检测上传根目录
     *
     * @param $rootpath
     * @return bool
     */
    public function checkRootPath($rootpath)
    {
        if (!(is_dir($rootpath) && is_writable($rootpath))) {
            $this->error = '上传根目录不存在：' . $rootpath;
            return false;
        }
        $this->rootPath = $rootpath;
        return true;
    }

    /**
     * 检测上传目录
     *
     * @param $savepath
     * @return bool
     */
    public function checkSavePath($savepath)
    {
        // 检测并创建目录
        if (!$this->mkdir($savepath)) {
            return false;
        } else {
            // 检测目录是否可写
            if (!is_writable($this->rootPath . $savepath)) {
                $this->error = '上传目录不可写：' . $savepath;
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * 保存指定文件
     *
     * @param      $file
     * @param bool $replace
     * @return bool
     */
    public function save($file, $replace = true)
    {
        $filename = $this->rootPath . $file['savepath'] . $file['savename'];
        // 不覆盖同名文件
        if (!$replace && is_file($filename)) {
            $this->error = '存在同名文件' . $file['savename'];
            return false;
        }
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $filename)) {
            $this->error = '文件上传保存错误';
            return false;
        }
        return true;
    }

    /**
     * 创建目录
     *
     * @param $savepath
     * @return bool
     */
    public function mkdir($savepath)
    {
        $dir = $this->rootPath . $savepath;
        if (is_dir($dir)) {
            return true;
        }
        if (mkdir($dir, 0777, true)) {
            return true;
        } else {
            $this->error = '目录创建失败：' . $savepath;
            return false;
        }
    }

    /**
     * 获取最后一次上传错误信息
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }
}

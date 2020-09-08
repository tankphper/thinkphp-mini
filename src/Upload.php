<?php
namespace Think;

class Upload
{

    /**
     * 默认上传配置
     *
     * @var array
     */
    private $config = [
        // 允许上传的文件MiMe类型
        'mimes'        => [],
        // 上传的文件大小限制 (0-不做限制)
        'maxSize'      => 0,
        // 允许上传的文件后缀
        'exts'         => [],
        // 自动子目录保存文件
        'autoSub'      => true,
        // 子目录创建方式，[0]-函数名，[1]-参数，多个参数使用数组
        'subName'      => [
            'date',
            'Y-m-d'
        ],
        // 保存根路径
        'rootPath'     => './uploads/',
        // 保存路径
        'savePath'     => '',
        // 上传文件命名规则，[0]-函数名，[1]-参数，多个参数使用数组
        'saveName'     => [
            'uniqid',
            ''
        ],
        // 文件保存后缀，空则使用原后缀
        'saveExt'      => '',
        // 存在同名是否覆盖
        'replace'      => false,
        // 是否生成hash编码
        'hash'         => true,
        // 检测文件是否存在回调，如果存在返回文件信息数组
        'callback'     => false,
        // 文件上传驱动
        'driver'       => '',
        // 上传驱动配置
        'driverConfig' => []
    ];

    /**
     * 上传错误信息
     *
     * @var string
     */
    private $error = '';

    /**
     * 上传驱动实例
     *
     * @var Object
     */
    private $uploader;

    /**
     * Upload constructor.
     *
     * @param array  $config
     * @param string $driver
     * @param null   $driverConfig
     * @throws Exception
     */
    public function __construct($config = [], $driver = '', $driverConfig = null)
    {
        $this->config = array_merge($this->config, $config);
        $this->setDriver($driver, $driverConfig);
        if (!empty($this->config['mimes'])) {
            if (is_string($this->mimes)) {
                $this->config['mimes'] = explode(',', $this->mimes);
            }
            $this->config['mimes'] = array_map('strtolower', $this->mimes);
        }
        if (!empty($this->config['exts'])) {
            if (is_string($this->exts)) {
                $this->config['exts'] = explode(',', $this->exts);
            }
            $this->config['exts'] = array_map('strtolower', $this->exts);
        }
    }

    /**
     * 魔术方法
     *
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->config[$name];
    }

    /**
     * 魔术方法
     *
     * @param $name
     * @param $value
     * @throws Exception
     */
    public function __set($name, $value)
    {
        if (isset($this->config[$name])) {
            $this->config[$name] = $value;
            if ($name == 'driverConfig') {
                $this->setDriver();
            }
        }
    }

    /**
     * 魔术方法
     *
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->config[$name]);
    }

    /**
     * 获取错误信息
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 上传单个文件
     *
     * @param $file
     * @return array|bool|mixed
     */
    public function uploadOne($file)
    {
        $info = $this->upload([$file]);
        return $info ? $info[0] : $info;
    }

    /**
     * 上传文件
     *
     * @param string $files
     * @return array|bool
     */
    public function upload($files = '')
    {
        if ('' === $files) {
            $files = $_FILES;
        }
        if (empty($files)) {
            $this->error = '没有上传的文件';
            return false;
        }
        // 检测上传根目录
        if (!$this->uploader->checkRootPath($this->rootPath)) {
            $this->error = $this->uploader->getError();
            return false;
        }

        // 检查上传目录
        if (!$this->uploader->checkSavePath($this->savePath)) {
            $this->error = $this->uploader->getError();
            return false;
        }

        // 逐个检测并上传文件
        $info = [];
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
        }
        // 对上传文件数组信息处理
        $files = $this->dealFiles($files);
        foreach ($files as $key => $file) {
            $file['name'] = strip_tags($file['name']);
            if (!isset($file['key'])) {
                $file['key'] = $key;
            }
            // 通过扩展获取文件类型，可解决FLASH上传$FILES数组返回文件类型错误的问题
            if (isset($finfo)) {
                $file['type'] = finfo_file($finfo, $file['tmp_name']);
            }
            // 获取上传文件后缀，允许上传无后缀文件
            $file['ext'] = pathinfo($file['name'], PATHINFO_EXTENSION);
            // 文件上传检测
            if (!$this->check($file)) {
                continue;
            }
            // 获取文件hash
            if ($this->hash) {
                $file['md5'] = md5_file($file['tmp_name']);
                $file['sha1'] = sha1_file($file['tmp_name']);
            }
            // 调用回调函数检测文件是否存在
            $data = [];
            $this->callback && $data = call_user_func($this->callback, $file);
            if ($this->callback && $data) {
                if (file_exists('.' . $data['path'])) {
                    $info[$key] = $data;
                    continue;
                } elseif ($this->removeTrash) {
                    // 删除垃圾据
                    call_user_func($this->removeTrash, $data);
                }
            }
            // 生成保存文件名
            $savename = $this->getSaveName($file);
            if (false == $savename) {
                continue;
            } else {
                $file['savename'] = $savename;
            }
            // 检测并创建子目录
            $subpath = $this->getSubPath($file['name']);
            if (false === $subpath) {
                continue;
            } else {
                $file['savepath'] = $this->savePath . $subpath;
            }
            // 对图像文件进行严格检测
            $ext = strtolower($file['ext']);
            if (in_array($ext, [
                'gif',
                'jpg',
                'jpeg',
                'bmp',
                'png',
                'swf'
            ])) {
                $imginfo = getimagesize($file['tmp_name']);
                if (empty($imginfo) || ($ext == 'gif' && empty($imginfo['bits']))) {
                    $this->error = '非法图像文件';
                    continue;
                }
            }
            // 保存文件 并记录保存成功的文件
            if ($this->uploader->save($file, $this->replace)) {
                unset($file['error'], $file['tmp_name']);
                $info[$key] = $file;
            } else {
                $this->error = $this->uploader->getError();
            }
        }
        if (isset($finfo)) {
            finfo_close($finfo);
        }
        return empty($info) ? false : $info;
    }

    /**
     * 转换上传文件数组变量为正确的方式
     *
     * @param $files
     * @return array
     */
    private function dealFiles($files)
    {
        $fileArray = [];
        $n = 0;
        foreach ($files as $key => $file) {
            if (is_array($file['name'])) {
                $keys = array_keys($file);
                $count = count($file['name']);
                for ($i = 0; $i < $count; $i++) {
                    $fileArray[$n]['key'] = $key;
                    foreach ($keys as $_key) {
                        $fileArray[$n][$_key] = $file[$_key][$i];
                    }
                    $n++;
                }
            } else {
                $fileArray = $files;
                break;
            }
        }
        return $fileArray;
    }

    /**
     * 设置上传驱动
     *
     * @param null $driver
     * @param null $config
     * @throws Exception
     */
    private function setDriver($driver = null, $config = null)
    {
        $driver = $driver ?: ($this->driver ?: 'local');
        $config = $config ?: ($this->driverConfig ?: []);
        $class = strpos($driver, '\\') ? $driver : 'Think\\Upload\\Handler\\' . ucfirst(strtolower($driver));
        $this->uploader = new $class($config);
        if (!$this->uploader) {
            E('Upload handle not exist：' . $driver);
        }
    }

    /**
     * 检查上传的文件
     *
     * @param $file
     * @return bool
     */
    private function check($file)
    {
        if ($file['error']) {
            $this->error($file['error']);
            return false;
        }
        if (empty($file['name'])) {
            $this->error = '未知上传错误';
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            $this->error = '非法上传文件';
            return false;
        }
        if (!$this->checkSize($file['size'])) {
            $this->error = '上传文件大小不符';
            return false;
        }
        // FLASH 上传的文件获取到的 mime 类型都为 application/octet-stream
        if (!$this->checkMime($file['type'])) {
            $this->error = '上传文件MIME类型不允许';
            return false;
        }
        if (!$this->checkExt($file['ext'])) {
            $this->error = '上传文件后缀不允许';
            return false;
        }
        return true;
    }

    /**
     * 获取错误代码信息
     *
     * @param $errorNo
     */
    private function error($errorNo)
    {
        switch ($errorNo) {
            case 1:
                $this->error = '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值';
                break;
            case 2:
                $this->error = '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值';
                break;
            case 3:
                $this->error = '文件只有部分被上传';
                break;
            case 4:
                $this->error = '没有文件被上传';
                break;
            case 6:
                $this->error = '找不到临时文件夹';
                break;
            case 7:
                $this->error = '文件写入失败';
                break;
            default:
                $this->error = '未知上传错误';
        }
    }

    /***
     * 检查文件大小是否合法
     *
     * @param $size
     * @return bool
     */
    private function checkSize($size)
    {
        return !($size > $this->maxSize) || (0 == $this->maxSize);
    }

    /**
     * 检查上传的文件MIME类型是否合法
     *
     * @param $mime
     * @return bool
     */
    private function checkMime($mime)
    {
        return empty($this->config['mimes']) ? true : in_array(strtolower($mime), $this->mimes);
    }

    /**
     * 检查上传的文件后缀是否合法
     *
     * @param $ext
     * @return bool
     */
    private function checkExt($ext)
    {
        return empty($this->config['exts']) ? true : in_array(strtolower($ext), $this->exts);
    }

    /**
     * 根据上传文件命名规则取得保存文件名
     *
     * @param $file
     * @return bool|string
     */
    private function getSaveName($file)
    {
        $rule = $this->saveName;
        // 保持文件名不变
        if (empty($rule)) {
            // 解决 pathinfo 中文文件名BUG
            $filename = substr(pathinfo("_{$file['name']}", PATHINFO_FILENAME), 1);
            $savename = $filename;
        } else {
            $savename = $this->getName($rule, $file['name']);
            if (empty($savename)) {
                $this->error = '文件命名规则错误';
                return false;
            }
        }
        // 文件保存后缀，支持强制更改文件后缀
        $ext = empty($this->config['saveExt']) ? $file['ext'] : $this->saveExt;

        return $savename . '.' . $ext;
    }

    /**
     * 获取子目录的名称
     *
     * @param $filename
     * @return bool|string
     */
    private function getSubPath($filename)
    {
        $subpath = '';
        $rule = $this->subName;
        if ($this->autoSub && !empty($rule)) {
            $subpath = $this->getName($rule, $filename) . '/';
            if (!empty($subpath) && !$this->uploader->mkdir($this->savePath . $subpath)) {
                $this->error = $this->uploader->getError();
                return false;
            }
        }
        return $subpath;
    }

    /**
     * 根据指定的规则获取文件或目录名称
     *
     * @param $rule
     * @param $filename
     * @return mixed|string
     */
    private function getName($rule, $filename)
    {
        $name = '';
        if (is_array($rule)) {
            // 数组规则
            $func = $rule[0];
            $param = (array) $rule[1];
            foreach ($param as &$value) {
                $value = str_replace('__FILE__', $filename, $value);
            }
            $name = call_user_func_array($func, $param);
        } elseif (is_string($rule)) {
            // 字符串规则
            if (function_exists($rule)) {
                $name = call_user_func($rule);
            } else {
                $name = $rule;
            }
        }
        return $name;
    }
}

<?php
namespace Think;

class Verify
{

    /**
     * 配置
     *
     * @var array
     */
    protected $config = [
        // 验证码加密密钥
        'secretKey'   => 'Tank',
        // 验证码字符集合
        'codeSet'     => '2468ABCD',
        // 验证码过期时间（秒）
        'expire'      => 1800,
        // 使用中文验证码
        'useZh'       => false,
        // 使用数学算数
        'useMath'     => true,
        // 中文验证码字符串
        'zhSet'       => '我是刘德华',
        // 使用背景图片
        'useImgBg'    => false,
        // 验证码字体大小(px)
        'fontSize'    => 25,
        // 是否画混淆曲线
        'useCurve'    => true,
        // 是否添加杂点
        'useNoise'    => true,
        // 验证码图片高度
        'imageHeight' => 0,
        // 验证码图片宽度
        'imageWidth'  => 0,
        // 验证码位数
        'length'      => 5,
        // 验证码字体
        'fontttf'     => '',
        // 背景颜色
        'bgColor'          => [
            243,
            251,
            254
        ],
        // 验证成功后是否重置
        'reset'       => true
    ];
    // 验证码图片实例
    private $_image = null;
    // 验证码字体颜色
    private $_color = null;


    /**
     * Verify constructor.
     *
     * @param array $config
     */
    public function __construct($config = [])
    {
        $this->config = array_merge($this->config, $config);
        $this->useMath && $this->length = 4;
    }

    /**
     * 使用 $this->name 获取配置
     *
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->config[$name];
    }

    /**
     * 设置验证码配置
     *
     * @param $name
     * @param $value
     */
    public function __set($name, $value)
    {
        if (isset($this->config[$name])) {
            $this->config[$name] = $value;
        }
    }

    /**
     * 检查配置
     *
     * @param $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->config[$name]);
    }

    /**
     * 验证验证码是否正确
     *
     * @param        $code
     * @param string $id
     * @return bool
     */
    public function check($code, $id = '')
    {
        $key = $this->authcode($this->secretKey) . $id;
        // 验证码不能为空
        $secode = session($key);
        if (empty($code) || empty($secode)) {
            return false;
        }
        // 验证码过期
        if (REQUEST_TIME - $secode['verify_time'] > $this->expire) {
            session($key, null);
            return false;
        }
        if ($this->authcode(strtoupper($code)) == $secode['verify_code']) {
            $this->reset && session($key, null);
            return true;
        }
        return false;
    }

    /**
     * 输出验证码并把验证码的值保存的session中
     *
     * @param string $id
     */
    public function entry($id = '')
    {
        // 图片宽(px)
        $this->imageWidth || $this->imageWidth = $this->length * $this->fontSize * 1.5 + $this->length * $this->fontSize / 2;
        // 图片高(px)
        $this->imageHeight || $this->imageHeight = $this->fontSize * 2.5;
        // 建立一幅 $this->imageWidth x $this->imageHeight 的图像
        $this->_image = imagecreate($this->imageWidth, $this->imageHeight);
        // 设置背景
        imagecolorallocate($this->_image, $this->bgColor[0], $this->bgColor[1], $this->bgColor[2]);
        // 验证码字体随机颜色
        $this->_color = imagecolorallocate($this->_image, mt_rand(100, 255), mt_rand(100, 255), mt_rand(100, 255));
        // 验证码使用随机字体
        $this->fontttf = $this->_randFont($this->useZh);
        // 图片背景
        if ($this->useImgBg) {
            $this->_background();
        }
        // 绘杂点
        if ($this->useNoise) {
            $this->_writeNoise();
        }
        // 绘干扰线
        if ($this->useCurve) {
            $this->_writeCurve();
        }
        // 绘验证码
        $code = [];
        // 验证码第N个字符的左边距
        $codeNX = 0;
        if ($this->useZh) {
            // 中文验证码
            for ($i = 0; $i < $this->length; $i++) {
                $code[$i] = iconv_substr($this->zhSet, floor(mt_rand(0, mb_strlen($this->zhSet, 'utf-8') - 1)), 1, 'utf-8');
                imagettftext($this->_image, $this->fontSize, mt_rand(-40, 40), $this->fontSize * ($i + 1) * 1.5, $this->fontSize + mt_rand(10, 20), $this->_color, $this->fontttf, $code[$i]);
            }
        } elseif ($this->useMath) {
            // 数学运算
            $methods = [
                '+',
                '-'
            ];
            $code[1] = $method = $methods[array_rand($methods)];
            $code[0] = $method == '+' ? mt_rand(1, 50) : mt_rand(11, 99);
            $code[2] = $method == '+' ? mt_rand(0, 10) : mt_rand(0, 10);
            $code[3] = '=';
            $codeValue = $method == '+' ? $code[0] + $code[2] : $code[0] - $code[2];
            for ($i = 0; $i < count($code); $i++) {
                $perW = ceil($this->imageWidth / count($code));
                $codeNX = $perW * $i + ($i == 1 ? 16 : 10);
                imagettftext($this->_image, $this->fontSize, mt_rand(-10, 10), $codeNX, $this->fontSize * 1.8, $this->_color, $this->fontttf, $code[$i]);
            }
        } else {
            // 普通字符
            for ($i = 0; $i < $this->length; $i++) {
                $code[$i] = $this->codeSet[mt_rand(0, strlen($this->codeSet) - 1)];
                $codeNX += mt_rand($this->fontSize * 1.2, $this->fontSize * 1.6);
                imagettftext($this->_image, $this->fontSize, mt_rand(-40, 40), $codeNX, $this->fontSize * 1.6, $this->_color, $this->fontttf, $code[$i]);
            }
        }
        // 保存验证码
        $key = $this->authcode($this->secretKey);
        if ($this->useMath) {
            $code = $this->authcode(strtoupper($codeValue));
        } else {
            $code = $this->authcode(strtoupper(implode('', $code)));
        }
        // 保存校验码
        $sessCode = [
            'verify_code' => $code,
            'verify_time' => REQUEST_TIME
        ];
        session($key . $id, $sessCode);
        // 响应头部
        header('Cache-Control: private, max-age=0, no-store, no-cache, must-revalidate');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('content-type: image/png');
        // 输出图像
        imagepng($this->_image);
        imagedestroy($this->_image);
    }

    /**
     * 画一条由两条连在一起构成的随机正弦函数曲线作干扰线
     *
     * 正弦型函数解析式：y=Asin(ωx+φ)+b
     * 各常数值对函数图像的影响：
     * A：决定峰值（即纵向拉伸压缩的倍数）
     * b：表示波形在Y轴的位置关系或纵向移动距离（上加下减）
     * φ：决定波形与X轴位置关系或横向移动距离（左加右减）
     * ω：决定周期（最小正周期T=2π/∣ω∣）
     */
    private function _writeCurve()
    {
        $px = $py = 0;
        // 曲线前部分
        $A = mt_rand(1, $this->imageHeight / 2); // 振幅
        $b = mt_rand(-$this->imageHeight / 4, $this->imageHeight / 4); // Y轴方向偏移量
        $f = mt_rand(-$this->imageHeight / 4, $this->imageHeight / 4); // X轴方向偏移量
        $T = mt_rand($this->imageHeight, $this->imageWidth * 2); // 周期
        $w = (2 * M_PI) / $T;

        $px1 = 0; // 曲线横坐标起始位置
        $px2 = mt_rand($this->imageWidth / 2, $this->imageWidth * 0.8); // 曲线横坐标结束位置

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if ($w != 0) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageHeight / 2; // y = Asin(ωx+φ) + b
                $i = (int) ($this->fontSize / 5);
                while ($i > 0) {
                    imagesetpixel($this->_image, $px + $i, $py + $i, $this->_color); // 这里(while)循环画像素点比imagettftext和imagestring用字体大小一次画出（不用这while循环）性能要好很多
                    $i--;
                }
            }
        }
        // 曲线后部分
        $A = mt_rand(1, $this->imageHeight / 2); // 振幅
        $f = mt_rand(-$this->imageHeight / 4, $this->imageHeight / 4); // X轴方向偏移量
        $T = mt_rand($this->imageHeight, $this->imageWidth * 2); // 周期
        $w = (2 * M_PI) / $T;
        $b = $py - $A * sin($w * $px + $f) - $this->imageHeight / 2;
        $px1 = $px2;
        $px2 = $this->imageWidth;

        for ($px = $px1; $px <= $px2; $px = $px + 1) {
            if ($w != 0) {
                $py = $A * sin($w * $px + $f) + $b + $this->imageHeight / 2; // y = Asin(ωx+φ) + b
                $i = (int) ($this->fontSize / 5);
                while ($i > 0) {
                    imagesetpixel($this->_image, $px + $i, $py + $i, $this->_color);
                    $i--;
                }
            }
        }
    }

    /**
     * 画杂点
     */
    private function _writeNoise()
    {
        $codeSet = '.....';
        for ($i = 0; $i < 10; $i++) {
            // 杂点颜色
            $noiseColor = imagecolorallocate($this->_image, mt_rand(150, 225), mt_rand(150, 225), mt_rand(150, 225));
            for ($j = 0; $j < 5; $j++) {
                // 绘杂点
                imagestring($this->_image, 5, mt_rand(-10, $this->imageWidth), mt_rand(-10, $this->imageHeight), $codeSet[mt_rand(0, 4)], $noiseColor);
            }
        }
    }

    /**
     * 使用随机字体
     *
     * @param bool $useZh
     * @return string
     */
    private function _randFont($useZh = false)
    {
        $ttfPath = dirname(__FILE__) . '/Verify/' . ($useZh ? 'zhttfs' : 'ttfs') . '/';
        if (empty($this->fontttf)) {
            $dir = dir($ttfPath);
            $ttfs = [];
            while (false !== ($file = $dir->read())) {
                if ($file[0] != '.' && substr($file, -4) == '.ttf') {
                    $ttfs[] = $file;
                }
            }
            $dir->close();
            $fontttf = $ttfPath . $ttfs[array_rand($ttfs)];
        } else {
            $fontttf = $ttfPath . $this->fontttf;
        }
        return $fontttf;
    }

    /**
     * 绘制背景图片
     */
    private function _background()
    {
        $path = dirname(__FILE__) . '/Verify/bgs/';
        $dir = dir($path);
        $bgs = [];
        while (false !== ($file = $dir->read())) {
            if ($file[0] != '.' && substr($file, -4) == '.jpg') {
                $bgs[] = $path . $file;
            }
        }
        $dir->close();
        $gb = $bgs[array_rand($bgs)];
        list ($width, $height) = @getimagesize($gb);
        // Resample
        $bgImage = @imagecreatefromjpeg($gb);
        @imagecopyresampled($this->_image, $bgImage, 0, 0, 0, 0, $this->imageWidth, $this->imageHeight, $width, $height);
        @imagedestroy($bgImage);
    }

    /**
     * 加密验证码
     *
     * @param $str
     * @return string
     */
    private function authcode($str)
    {
        $key = substr(md5($this->secretKey), 5, 8);
        $str = substr(md5($str), 8, 10);
        return md5($key . $str);
    }
}

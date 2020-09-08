<?php
namespace Think\Traits;

trait ErrorTrait
{

    /**
     * 错误消息
     *
     * @var string
     */
    private $errorMessage = '';

    /**
     * 错误代码
     *
     * @var int
     */
    private $errorCode = -1;

    /**
     * 获取错误信息
     *
     * @param bool $withCode
     * @return string
     */
    public function getError(bool $withCode = false): string
    {
        $errorMessage = $this->errorMessage;
        $withCode && $errorMessage .= '(' . $this->errorCode . ')';
        return $errorMessage;
    }

    /**
     * 获取错误代码
     *
     * @return int
     */
    public function getErrorCode(): int
    {
        return $this->errorCode;
    }

    /**
     * 设置错误信息
     *
     * @param mixed $message
     * @param int   $code
     */
    public function setError($message = '', int $code = -1)
    {
        $message = is_array($message) || is_object($message) ? json_encode($message, JSON_UNESCAPED_UNICODE) : $message;
        $this->errorMessage = $message ?? '';
        $this->errorCode = $code;
    }
}
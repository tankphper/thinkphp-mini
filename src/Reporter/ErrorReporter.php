<?php
namespace Think\Reporter;

interface ErrorReporter
{

    /**
     * 获取错误信息
     *
     * @param bool $withCode
     * @return string
     */
    public function getError(bool $withCode = false): string;

    /**
     * 获取错误代码
     *
     * @return int
     */
    public function getErrorCode(): int;

    /**
     * 设置错误信息
     *
     * @param mixed $message
     * @param int   $code
     * @return mixed
     */
    public function setError($message = '', int $code = -1);
}
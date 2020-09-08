<?php
namespace Think\Session\Handler;

use SessionHandler;
use Think\Manager\Redis as RedisManager;

class Redis extends SessionHandler
{

    /**
     * Redis 操作句柄
     *
     * @var null
     */
    protected $handler = null;

    /**
     * Session 配置
     *
     * @var $config
     */
    protected $config = [];

    /**
     * Redis constructor.
     */
    public function __construct()
    {
        $this->config = [
            'session_expire' => C('SESSION_EXPIRE') ?: ini_get('session.gc_maxlifetime'),
            'session_prefix' => C('SESSION_PREFIX') ?: 'session:'
        ];
    }

    /**
     * 打开 Session
     *
     * @param string $savePath
     * @param string $sessName
     * @return bool
     * @throws \Think\Exception
     */
    public function open($savePath, $sessName)
    {
        $this->handler = RedisManager::getInstance();
        return true;
    }

    /**
     * 关闭 Session
     *
     * @return bool
     */
    public function close()
    {
        $this->gc(ini_get('session.gc_maxlifetime'));
        $this->handler->close();
        $this->handler = null;
        return true;
    }

    /**
     * 读取 Session
     *
     * @param string $sessId
     * @return string
     */
    public function read($sessId)
    {
        $sessId = $this->config['session_prefix'] . $sessId;
        return (string) $this->handler->get($sessId);
    }

    /**
     * 写入 Session
     *
     * @param string $sessId
     * @param string $sessData
     * @return bool|mixed
     */
    public function write($sessId, $sessData)
    {
        $sessId = $this->config['session_prefix'] . $sessId;
        if ($this->config['session_expire'] > 0) {
            return $this->handler->setex($sessId, $this->config['session_expire'], $sessData);
        } else {
            return $this->handler->set($sessId, $sessData);
        }
    }

    /**
     * 删除 Session
     *
     * @param string $sessId
     * @return bool
     */
    public function destroy($sessId)
    {
        $sessId = $this->config['session_prefix'] . $sessId;
        return $this->handler->delete($sessId) > 0;
    }

    /**
     * Session 垃圾回收
     *
     * @param int $sessMaxLifeTime
     * @return bool
     */
    public function gc($sessMaxLifeTime)
    {
        return true;
    }
}

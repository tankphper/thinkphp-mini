<?php
namespace Think\Session\Handler;

use SessionHandler;
use PDO;

class Db extends SessionHandler
{

    /**
     * Session 数据表
     *
     * @var string
     */
    protected $sessionTable = '';

    /**
     * 数据库句柄
     *
     * @var null
     */
    protected $hander = null;

    /**
     * Session 配置
     *
     * @var $config
     */
    protected $config = [];

    /**
     * PDO 连接参数
     *
     * @var array
     */
    protected $options = [
        PDO::ATTR_CASE              => PDO::CASE_LOWER,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false
    ];

    /**
     * Db constructor.
     */
    public function __construct()
    {
        $this->config = [
            'session_expire' => C('SESSION_EXPIRE') ?: ini_get('session.gc_maxlifetime'),
            'session_prefix' => C('SESSION_PREFIX') ?: 'session:'
        ];
        $sessionTable = C('SESSION_TABLE');
        $this->sessionTable = $sessionTable ? $sessionTable : C('DB_PREFIX') . 'session';
    }

    /**
     * 解析pdo连接的dsn信息
     *
     * @param        $name
     * @param string $host
     * @param string $port
     * @param string $socket
     * @param string $charset
     * @return string
     */
    protected function parseDsn($name, $host = '127.0.0.1', $port = '', $socket = '', $charset = 'utf8mb4')
    {
        $dsn = 'mysql:dbname=' . $name . ';host=' . $host;
        if (!empty($port)) {
            $dsn .= ';port=' . $port;
        } elseif (!empty($socket)) {
            $dsn .= ';unix_socket=' . $socket;
        }
        if (!empty($charset)) {
            // 为兼容各版本PHP,用两种方式设置编码
            $this->options[\PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES ' . $charset;
            $dsn .= ';charset=' . $charset;
        }
        return $dsn;
    }

    /**
     * 打开 Session
     *
     * @param string $savePath
     * @param string $sessName
     * @return bool
     */
    public function open($savePath, $sessName)
    {
        // 数据库链接
        $dsn = $this->parseDsn(C('DB_NAME'), C('DB_HOST'), C('DB_PORT'));
        $hander = new PDO($dsn, C('DB_USER'), C('DB_PWD'));
        if (!$hander) {
            return false;
        }
        $this->hander = $hander;
        return true;
    }

    /**
     * 关闭 Session
     *
     * @return bool
     */
    public function close()
    {
        $this->gc($this->config['session_expire']);
        $this->hander = null;
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
        $res = $this->hander->prepare("SELECT session_data AS data FROM " . $this->sessionTable . " WHERE session_id = '$sessId'   AND session_expire >" . time());
        $res->execute();
        if (!$res) {
            return '';
        }
        $result = $res->fetch(PDO::FETCH_ASSOC);
        return $result['data'] ?? '';
    }

    /**
     * 写入 Session
     *
     * @param string $sessId
     * @param string $sessData
     * @return bool
     */
    public function write($sessId, $sessData)
    {
        $sessId = $this->config['session_prefix'] . $sessId;
        $expire = time() + $this->config['session_expire'];
        $sessData = addslashes($sessData);
        $res = $this->hander->exec("REPLACE INTO " . $this->sessionTable . " (session_id, session_expire, session_data) VALUES( '$sessId', '$expire', '$sessData')");
        if ($res) {
            return true;
        }
        return false;
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
        $res = $this->hander->exec("DELETE FROM " . $this->sessionTable . " WHERE session_id = '$sessId'");
        if ($res) {
            return true;
        }
        return false;
    }

    /**
     * Session 回收
     *
     * @param int $sessMaxLifeTime
     * @return bool|mixed
     */
    public function gc($sessMaxLifeTime)
    {
        return $this->hander->exec('DELETE FROM ' . $this->sessionTable . ' WHERE session_expire < ' . time());
    }
}
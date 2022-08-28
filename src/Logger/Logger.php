<?php

namespace Think\Logger;

use Monolog\Logger as MonologLogger;
use Monolog\Formatter\LineFormatter;
use Think\Logger\Handler\FileRotateHandler;

class Logger extends MonologLogger
{

    /**
     * Log app name
     *
     * @var string
     */
    protected $name = 'app';

    /**
     * Flush count size
     *
     * @var int
     */
    protected $flushSize = 0;

    /**
     * Line format
     *
     * @var string
     */
    protected $format = '%datetime% [%channel%.%level_name%] %remote_ip% [%request_id%] %message%';

    /**
     * Temp logs wait to flush
     *
     * @var array
     */
    protected $records = [];

    /**
     * Logger constructor.
     */
    public function __construct($options = [])
    {
        $this->flushSize = C('LOG_FLUSH_SIZE', null, $this->flushSize);
        // Default handler
        $logHandler = new FileRotateHandler(MonologLogger::DEBUG);
        $lineFormatter = new LineFormatter(C('LOG_FORMAT', null, $this->format));
        $logFile = $options['logFileName'] ? LOG_PATH . '/' . $options['logFileName'] . '.log' : C('LOG_FILE', null, '');
        $logHandler->setLogFile($logFile);
        $logHandler->setRotateSize(C('LOG_ROTATE_SIZE', null, ''));
        $logHandler->setKeepDay(C('LOG_FILE_KEEP_DAY', null, 7));
        $logHandler->setFormatter($lineFormatter);

        parent::__construct(C('LOG_NAME', null, $this->name), [$logHandler]);
    }

    /**
     * Rewrite monolog method
     *
     * @param int    $level
     * @param string $message
     * @param array  $context
     * @return bool|void
     */
    public function addRecord($level, $message, array $context = [])
    {
        if (!static::$timezone) {
            static::$timezone = new \DateTimeZone(date_default_timezone_get() ?: 'PRC');
        }
        $ts = new \DateTime(null, static::$timezone);
        $ts->setTimezone(static::$timezone);

        $message = $this->formatMessage($message);
        $record = $this->formatRecord($message, $level, $context, $ts);

        foreach ($this->processors as $processor) {
            $record = call_user_func($processor, $record);
        }
        $this->records[] = $record;
        $countRecords = count($this->records);
        if ($countRecords >= $this->flushSize) {
            $this->flushLog();
        }
        return true;
    }

    /**
     * Force flush logs to file
     *
     * @return bool
     */
    public function forceFlush()
    {
        $this->flushLog();
        return true;
    }

    /**
     * Flush log to file
     */
    private function flushLog()
    {
        if (empty($this->records)) {
            return false;
        }
        reset($this->handlers);
        while ($handler = current($this->handlers)) {
            if (true === $handler->handleBatch($this->records)) {
                break;
            }
            next($this->handlers);
        }
        $this->records = [];
    }

    /**
     * Format log record
     *
     * @param string $message
     * @param int    $level
     * @param mixed  $ts
     * @param array  $extra
     * @return array
     */
    private function formatRecord(string $message, int $level, array $context, $ts, $extra = []): array
    {
        $channel = $this->getName();
        $levelName = static::getLevelName($level);
        $record = [
            'message'        => (string) $message,
            'context'        => $context,
            'level'          => $level,
            'level_name'     => $levelName,
            'channel'        => $channel,
            'remote_ip'      => get_client_ip(),
            'request_id'     => defined('REQUEST_ID') ? REQUEST_ID : '',
            'request_uri'    => defined('REQUEST_URI') ? REQUEST_URI : '',
            'request_method' => defined('REQUEST_METHOD') ? REQUEST_METHOD : '',
            'datetime'       => $ts,
            'extra'          => $extra
        ];
        return $record;
    }

    /**
     * Format message
     *
     * @param $message
     * @return string
     */
    private function formatMessage($message): string
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }
        return $message;
    }

}

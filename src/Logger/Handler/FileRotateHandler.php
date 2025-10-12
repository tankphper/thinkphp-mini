<?php
namespace Think\Logger\Handler;

use Monolog\Handler\AbstractProcessingHandler;

class FileRotateHandler extends AbstractProcessingHandler
{
    /**
     * Log file
     *
     * @var string
     */
    protected $logFile = '';

    /**
     * Log file keep day
     *
     * @var int
     */
    protected $logFileKeepDay = 7;

    /**
     * Rotate file size
     *
     * @var int
     */
    protected $rotateSize = 20971520;

    /**
     * Handle batch
     *
     * @param array $records
     * @return bool|void
     */
    public function handleBatch(array $records)
    {
        $messages = [];
        foreach ($records as $record) {
            if (!$this->isHandling($record)) {
                return false;
            }
            $record = $this->processRecord($record);
            $record['formatted'] = $this->getFormatter()->format($record);
            $messages[] = $record;
        }
        $formats = array_column($messages, 'formatted');
        $this->write($formats);
    }

    /**
     * Set log file
     *
     * @param $logFile
     */
    public function setLogFile($logFile)
    {
        $this->logFile = $logFile;
    }

    /**
     * Set rotote size
     *
     * @param $rotateSize
     */
    public function setRotateSize($rotateSize)
    {
        $this->rotateSize = $rotateSize;
    }

    /**
     * Set log file keep day
     *
     * @param $keepDay
     */
    public function setKeepDay($keepDay)
    {
        $this->logFileKeepDay = $keepDay;
    }

    /**
     * Write log
     *
     * @param array $record
     */
    protected function write(array $record)
    {
        $this->createDir();
        // set formatter, monolog default is LineFormatter
        $logMessages = implode("\n", $record) . "\n";
        $this->syncWrite($this->logFile, $logMessages);
    }

    /**
     * Write logfile
     *
     * @param string $logFile
     * @param string $logMessage
     */
    private function syncWrite(string $logFile, string $logMessage)
    {
        $fp = fopen($logFile, 'a');
        if ($fp === false) {
            throw new \InvalidArgumentException(sprintf('Unable to open logfile: %s', $logFile));
        }
        flock($fp, LOCK_EX);
        fwrite($fp, $logMessage);
        flock($fp, LOCK_UN);
        fclose($fp);

        // rotate
        $this->rotateLog();
    }

    /**
     * Create log directory
     */
    private function createDir()
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            $res = mkdir($logDir, 0777, true);
            if (false === $res) {
                throw new \UnexpectedValueException(sprintf('The log directory "%s" cannot be created', $logDir));
            }
        }
    }

    /**
     * Rotate log file
     *
     * @return void
     */
    private function rotateLog()
    {
        if (!$this->rotateSize) {
            return;
        }
        $fileSize = filesize($this->logFile);
        if ($fileSize >= $this->rotateSize) {
            $fileName = date('YmdHi') . '.' . basename($this->logFile);
            @rename($this->logFile, dirname($this->logFile) . '/' . $fileName);
            $this->deleteLog($this->logFile);
        }
    }

    /**
     * 删除旧日志
     *
     * @param $logFile
     */
    private function deleteLog($logFile)
    {
        $files = glob(dirname($logFile) . '/*.' . basename($logFile));
        if (!empty($files)) {
            $deleteTime = strtotime('-' . $this->logFileKeepDay . 'day');
            foreach ($files as $file) {
                if (filemtime($file) <= $deleteTime) {
                    @unlink($file);
                }
            }
        }
    }
}
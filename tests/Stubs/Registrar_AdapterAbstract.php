<?php

use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class Registrar_AdapterAbstract
{
    protected $_log;
    protected bool $_testMode = false;

    public function setLog(Box_Log $log)
    {
        $this->_log = $log;
        return $this;
    }

    public function getLog(): Box_Log
    {
        if (!$this->_log instanceof Box_Log) {
            $this->_log = new Box_Log();
        }
        return $this->_log;
    }

    public function getHttpClient(): HttpClientInterface
    {
        throw new \RuntimeException('getHttpClient() must be mocked in tests.');
    }

    public function enableTestMode()
    {
        $this->_testMode = true;
        return $this;
    }
}

<?php

use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class Registrar_AdapterAbstract
{
    private ?Box_Log $log                    = null;
    private ?HttpClientInterface $httpClient = null;

    public function getLog(): Box_Log
    {
        if ($this->log === null) {
            $this->log = new Box_Log();
        }
        return $this->log;
    }

    public function setLog(Box_Log $log): static
    {
        $this->log = $log;
        return $this;
    }

    public function getHttpClient(): HttpClientInterface
    {
        throw new \RuntimeException('getHttpClient() must be provided — mock it in tests.');
    }

    public function enableTestMode(): void {}
}

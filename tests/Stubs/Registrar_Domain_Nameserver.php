<?php

class Registrar_Domain_Nameserver
{
    private ?string $host = null;
    private ?string $ip   = null;

    public function getHost(): ?string { return $this->host; }
    public function setHost(string $host): self { $this->host = $host; return $this; }

    public function getIp(): ?string { return $this->ip; }
    public function setIp(string $ip): self { $this->ip = $ip; return $this; }
}

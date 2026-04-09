<?php

class Server_Account
{
    private ?string $username  = null;
    private ?string $password  = null;
    private ?string $domain    = null;
    private ?string $ip        = null;
    private ?Server_Client  $client  = null;
    private ?Server_Package $package = null;
    private ?bool   $reseller  = null;
    private ?bool   $suspended = null;
    private ?string $note      = null;

    public function getUsername(): ?string  { return $this->username; }
    public function setUsername(?string $v): static { $this->username = $v; return $this; }

    public function getPassword(): ?string  { return $this->password; }
    public function setPassword(?string $v): static { $this->password = $v; return $this; }

    public function getDomain(): ?string  { return $this->domain; }
    public function setDomain(?string $v): static { $this->domain = $v; return $this; }

    public function getIp(): ?string  { return $this->ip; }
    public function setIp(?string $v): static { $this->ip = $v; return $this; }

    public function getClient(): ?Server_Client  { return $this->client; }
    public function setClient(?Server_Client $v): static { $this->client = $v; return $this; }

    public function getPackage(): Server_Package { return $this->package; }
    public function setPackage(Server_Package $v): static { $this->package = $v; return $this; }

    public function getReseller(): ?bool  { return $this->reseller; }
    public function setReseller(bool $v): static { $this->reseller = $v; return $this; }

    public function getSuspended(): ?bool  { return $this->suspended; }
    public function setSuspended(bool $v): static { $this->suspended = $v; return $this; }

    public function getNote(): ?string  { return $this->note; }
    public function setNote(?string $v): static { $this->note = $v; return $this; }
}

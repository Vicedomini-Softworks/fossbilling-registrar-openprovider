<?php

class Server_Client
{
    private ?string $email     = null;
    private ?string $firstName = null;
    private ?string $lastName  = null;
    private ?string $company   = null;
    private ?string $country   = null;

    public function getEmail(): ?string   { return $this->email; }
    public function setEmail(?string $v): static { $this->email = $v; return $this; }

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $v): static { $this->firstName = $v; return $this; }

    public function getLastName(): ?string  { return $this->lastName; }
    public function setLastName(?string $v): static { $this->lastName = $v; return $this; }

    public function getFullName(): string   { return trim($this->firstName . ' ' . $this->lastName); }

    public function getCompany(): ?string   { return $this->company; }
    public function setCompany(?string $v): static { $this->company = $v; return $this; }

    public function getCountry(): ?string   { return $this->country; }
    public function setCountry(?string $v): static { $this->country = $v; return $this; }
}

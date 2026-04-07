<?php

class Registrar_Domain_Contact
{
    private ?string $firstName  = null;
    private ?string $lastName   = null;
    private ?string $email      = null;
    private ?string $tel        = null;
    private ?string $telCc      = null;
    private ?string $address1   = null;
    private ?string $city       = null;
    private ?string $state      = null;
    private ?string $country    = null;
    private ?string $zip        = null;
    private ?string $company    = null;

    public function getFirstName(): ?string  { return $this->firstName; }
    public function setFirstName(string $v): self { $this->firstName = $v; return $this; }

    public function getLastName(): ?string   { return $this->lastName; }
    public function setLastName(string $v): self  { $this->lastName = $v; return $this; }

    public function getEmail(): ?string      { return $this->email; }
    public function setEmail(string $v): self     { $this->email = $v; return $this; }

    public function getTel(): ?string        { return $this->tel; }
    public function setTel(string $v): self       { $this->tel = $v; return $this; }

    public function getTelCc(): ?string      { return $this->telCc; }
    public function setTelCc(string $v): self     { $this->telCc = $v; return $this; }

    public function getAddress1(): ?string   { return $this->address1; }
    public function setAddress1(string $v): self  { $this->address1 = $v; return $this; }

    public function getCity(): ?string       { return $this->city; }
    public function setCity(string $v): self      { $this->city = $v; return $this; }

    public function getState(): ?string      { return $this->state; }
    public function setState(string $v): self     { $this->state = $v; return $this; }

    public function getCountry(): ?string    { return $this->country; }
    public function setCountry(string $v): self   { $this->country = $v; return $this; }

    public function getZip(): ?string        { return $this->zip; }
    public function setZip(string $v): self       { $this->zip = $v; return $this; }

    public function getCompany(): ?string    { return $this->company; }
    public function setCompany(string $v): self   { $this->company = $v; return $this; }
}

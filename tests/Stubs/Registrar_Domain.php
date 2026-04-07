<?php

class Registrar_Domain
{
    private ?string $sld                = null;
    private ?string $tld                = null;
    private ?int    $period             = null;
    private ?string $epp                = null;
    private bool    $privacyEnabled     = false;
    private bool    $locked             = false;
    private ?\DateTime $registeredAt    = null;
    private ?\DateTime $expiresAt       = null;

    private ?Registrar_Domain_Nameserver $ns1 = null;
    private ?Registrar_Domain_Nameserver $ns2 = null;
    private ?Registrar_Domain_Nameserver $ns3 = null;
    private ?Registrar_Domain_Nameserver $ns4 = null;

    private ?Registrar_Domain_Contact $contactRegistrar = null;
    private ?Registrar_Domain_Contact $contactAdmin     = null;
    private ?Registrar_Domain_Contact $contactTech      = null;
    private ?Registrar_Domain_Contact $contactBilling   = null;

    public function getSld(): ?string  { return $this->sld; }
    public function setSld(string $v): self { $this->sld = $v; return $this; }

    public function getTld(): ?string  { return $this->tld; }
    public function setTld(string $v): self { $this->tld = $v; return $this; }

    public function getName(): string  { return $this->sld . $this->tld; }

    public function getRegistrationPeriod(): ?int  { return $this->period; }
    public function setRegistrationPeriod(int $v): self { $this->period = $v; return $this; }

    public function getEpp(): ?string  { return $this->epp; }
    public function setEpp(string $v): self  { $this->epp = $v; return $this; }

    public function isPrivacyEnabled(): bool  { return $this->privacyEnabled; }
    public function setPrivacyEnabled(bool $v): self { $this->privacyEnabled = $v; return $this; }

    public function isLocked(): bool  { return $this->locked; }
    public function setLocked(bool $v): self { $this->locked = $v; return $this; }

    public function getRegisteredAt(): ?\DateTime  { return $this->registeredAt; }
    public function setRegisteredAt(\DateTime $v): self { $this->registeredAt = $v; return $this; }

    public function getExpiresAt(): ?\DateTime  { return $this->expiresAt; }
    public function setExpiresAt(\DateTime $v): self { $this->expiresAt = $v; return $this; }

    public function getNs1(): ?Registrar_Domain_Nameserver { return $this->ns1; }
    public function setNs1(Registrar_Domain_Nameserver $v): self { $this->ns1 = $v; return $this; }

    public function getNs2(): ?Registrar_Domain_Nameserver { return $this->ns2; }
    public function setNs2(Registrar_Domain_Nameserver $v): self { $this->ns2 = $v; return $this; }

    public function getNs3(): ?Registrar_Domain_Nameserver { return $this->ns3; }
    public function setNs3(Registrar_Domain_Nameserver $v): self { $this->ns3 = $v; return $this; }

    public function getNs4(): ?Registrar_Domain_Nameserver { return $this->ns4; }
    public function setNs4(Registrar_Domain_Nameserver $v): self { $this->ns4 = $v; return $this; }

    public function getContactRegistrar(): ?Registrar_Domain_Contact { return $this->contactRegistrar; }
    public function setContactRegistrar(Registrar_Domain_Contact $v): self { $this->contactRegistrar = $v; return $this; }

    public function getContactAdmin(): ?Registrar_Domain_Contact { return $this->contactAdmin; }
    public function setContactAdmin(Registrar_Domain_Contact $v): self { $this->contactAdmin = $v; return $this; }

    public function getContactTech(): ?Registrar_Domain_Contact { return $this->contactTech; }
    public function setContactTech(Registrar_Domain_Contact $v): self { $this->contactTech = $v; return $this; }

    public function getContactBilling(): ?Registrar_Domain_Contact { return $this->contactBilling; }
    public function setContactBilling(Registrar_Domain_Contact $v): self { $this->contactBilling = $v; return $this; }
}

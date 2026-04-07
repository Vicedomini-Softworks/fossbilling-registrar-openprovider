<?php

/**
 * Stub that mirrors the real FOSSBilling Registrar_Domain class.
 * Nameservers are plain strings; dates are Unix timestamps.
 */
class Registrar_Domain
{
    private $_tld;
    private $_sld;
    private $_registered_at;
    private $_expires_at;
    private ?int $_period   = null;
    private $_epp;
    private ?bool $_privacy = null;
    private $_locked;
    private $_ns1;
    private $_ns2;
    private $_ns3;
    private $_ns4;

    private ?Registrar_Domain_Contact $_contact_registrar = null;
    private ?Registrar_Domain_Contact $_contact_admin     = null;
    private ?Registrar_Domain_Contact $_contact_tech      = null;
    private ?Registrar_Domain_Contact $_contact_billing   = null;

    public function getSld()             { return $this->_sld; }
    public function setSld($v): self     { $this->_sld = $v; return $this; }

    public function getTld($with_dot = true)
    {
        if ($with_dot === false && isset($this->_tld[0]) && $this->_tld[0] === '.') {
            return ltrim((string) $this->_tld, '.');
        }
        return $this->_tld;
    }
    public function setTld($v): self { $this->_tld = $v; return $this; }

    public function getName(): string { return $this->_sld . $this->_tld; }

    public function getRegistrationPeriod(): ?int  { return $this->_period; }
    public function setRegistrationPeriod($v): self { $this->_period = (int) $v; return $this; }

    public function getEpp()         { return $this->_epp; }
    public function setEpp($v): self { $this->_epp = $v; return $this; }

    public function getPrivacyEnabled(): ?bool      { return $this->_privacy; }
    public function setPrivacyEnabled($v): self     { $this->_privacy = (bool) $v; return $this; }

    public function getLocked()          { return $this->_locked; }
    public function setLocked($v): self  { $this->_locked = $v; return $this; }

    public function getRegistrationTime() { return $this->_registered_at; }
    public function setRegistrationTime($v): self { $this->_registered_at = $v; return $this; }

    public function getExpirationTime() { return $this->_expires_at; }
    public function setExpirationTime($v): self { $this->_expires_at = $v; return $this; }

    // Nameservers: plain strings, exactly as FOSSBilling stores them
    public function getNs1()         { return $this->_ns1; }
    public function setNs1($v): self { $this->_ns1 = $v; return $this; }

    public function getNs2()         { return $this->_ns2; }
    public function setNs2($v): self { $this->_ns2 = $v; return $this; }

    public function getNs3()         { return $this->_ns3; }
    public function setNs3($v): self { $this->_ns3 = $v; return $this; }

    public function getNs4()         { return $this->_ns4; }
    public function setNs4($v): self { $this->_ns4 = $v; return $this; }

    public function getContactRegistrar(): ?Registrar_Domain_Contact { return $this->_contact_registrar; }
    public function setContactRegistrar(Registrar_Domain_Contact $c): self { $this->_contact_registrar = $c; return $this; }

    public function getContactAdmin(): ?Registrar_Domain_Contact { return $this->_contact_admin; }
    public function setContactAdmin(Registrar_Domain_Contact $c): self { $this->_contact_admin = $c; return $this; }

    public function getContactTech(): ?Registrar_Domain_Contact { return $this->_contact_tech; }
    public function setContactTech(Registrar_Domain_Contact $c): self { $this->_contact_tech = $c; return $this; }

    public function getContactBilling(): ?Registrar_Domain_Contact { return $this->_contact_billing; }
    public function setContactBilling(Registrar_Domain_Contact $c): self { $this->_contact_billing = $c; return $this; }
}

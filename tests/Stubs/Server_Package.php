<?php

class Server_Package
{
    private ?string $name   = null;
    private array   $custom = [];

    public function getName(): ?string { return $this->name; }
    public function setName(?string $v): static { $this->name = $v; return $this; }

    public function getCustomValue(string $param): ?string { return $this->custom[$param] ?? null; }
    public function setCustomValue(string $param, ?string $value): static { $this->custom[$param] = $value; return $this; }
    public function setCustomValues(array $values): static { $this->custom = array_merge($this->custom, $values); return $this; }
}

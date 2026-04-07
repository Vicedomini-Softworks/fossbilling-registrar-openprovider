<?php

class Registrar_Exception extends \Exception
{
    public function __construct(string $message = '', array $variables = [], int $code = 0)
    {
        parent::__construct($message, $code);
    }
}

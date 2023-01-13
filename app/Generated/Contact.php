<?php

namespace App\Generated;

class Contact
{
    public ?string $email;
    public ?string $prefix;
    public ?string $phone;
    public ?string $phonefull;

    public function toArray(): array
    {
        $json = json_encode($this, JSON_PRETTY_PRINT);
        $array = json_decode($json, true);
        return $array;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setPrefix(?string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function getPhonefull(): string
    {
        return $this->phonefull;
    }

    public function setPhonefull(?string $phonefull): void
    {
        $this->phonefull = $phonefull;
    }
}

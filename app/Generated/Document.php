<?php

namespace App\Generated;

class Document
{
    public string $type;
    public string $number;

    public function toArray(): array
    {
        $json = json_encode($this, JSON_PRETTY_PRINT);
        $array = json_decode($json, true);
        return $array;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setNumber(string $number): void
    {
        $this->number = $number;
    }

    public function getNumber(): string
    {
        return $this->number;
    }
}

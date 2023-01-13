<?php

namespace App\Generated;

class Business
{
    public int $id;
    public string $tradename;
    public string $businessname;
    public Document $document;
    public ?string $constitution;
    public Contact $contact;
    public Address $address;
    public bool $status;

    public function toArray(): array
    {
        $json = json_encode($this, JSON_PRETTY_PRINT);
        $array = json_decode($json, true);
        return $array;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getTradename(): string
    {
        return $this->tradename;
    }

    public function setTradename(string $tradename): void
    {
        $this->tradename = $tradename;
    }

    public function getBusinessname(): string
    {
        return $this->businessname;
    }

    public function setBusinessname(string $businessname): void
    {
        $this->businessname = $businessname;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function setDocument(Document $document): void
    {
        $this->document = $document;
    }

    public function getConstitution(): string
    {
        return $this->constitution;
    }

    public function setConstitution(?string $constitution): void
    {
        $this->constitution = $constitution;
    }

    public function getContact(): Contact
    {
        return $this->contact;
    }

    public function setContact(Contact $contact): void
    {
        $this->contact = $contact;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function setAddress(Address $address): void
    {
        $this->address = $address;
    }

    public function getStatus(): bool
    {
        return $this->status;
    }

    public function setStatus(bool $status): void
    {
        $this->status = $status;
    }
}

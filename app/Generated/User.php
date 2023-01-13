<?php

namespace App\Generated;

class User
{
    public int $id;
    public string $relative_id;
    public string $username;
    public string $password;
    public string $auth_token;
    public string $recovery_email;
    public Person $person;
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

    public function getRelativeId(): string
    {
        return $this->relative_id;
    }

    public function setRelativeId(string $relative_id): void
    {
        $this->relative_id = $relative_id;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getAuthToken(): string
    {
        return $this->auth_token;
    }

    public function setAuthToken(string $auth_token): void
    {
        $this->auth_token = $auth_token;
    }

    public function getRecoveryEmail(): string
    {
        return $this->recovery_email;
    }

    public function setRecoveryEmail(string $recovery_email): void
    {
        $this->recovery_email = $recovery_email;
    }

    public function getPerson(): Person
    {
        return $this->person;
    }

    public function setPerson(Person $person): void
    {
        $this->person = $person;
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

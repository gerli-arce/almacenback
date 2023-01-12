<?php

namespace App\Models;

class Response {
    public int $status;
    public string $message;
    public int $draw;
    public int $iTotalDisplayRecords;
    public int $iTotalRecords;
    public array $data;

    public function __construct() {
        $this -> status = 500;
        $this -> message = 'Error inesperado';
    }
    public function toArray(): array {
        $json = json_encode($this, JSON_PRETTY_PRINT);
        $array = json_decode($json, true);
        return $array;
    }

    public function getStatus(): int {
        return $this->status;
    }
    public function setStatus(int $status): void {
        $this->status = $status;
    }
    public function getMessage(): string {
        return $this->message;
    }
    public function setMessage(string $message): void {
        $this->message = $message;
    }
    public function getData(): array {
        return $this->data;
    }
    public function setData(array $data): void {
        $this->data = $data;
    }
    public function getDraw(): int {
        return $this->draw;
    }
    public function setDraw(int $draw): void {
        $this->draw = $draw;
    }
    public function getITotalDisplayRecords(): int {
        return $this->iTotalDisplayRecords;
    }
    public function setITotalDisplayRecords(int $iTotalDisplayRecords): void {
        $this->iTotalDisplayRecords = $iTotalDisplayRecords;
    }
    public function getITotalRecords(): int {
        return $this->iTotalRecords;
    }
    public function setITotalRecords(int $iTotalRecords): void {
        $this->iTotalRecords = $iTotalRecords;
    }
    
}
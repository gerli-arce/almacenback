<?php

namespace App\Generated;

use App\gLibraries\gUbigeo;

class Address
{
    public string $ubigeo;
    public string $department;
    public string $province;
    public string $district;
    public ?string $address;

    public function toArray(): array
    {
        $json = json_encode($this, JSON_PRETTY_PRINT);
        $array = json_decode($json, true);
        return $array;
    }

    public function getUbigeo(): string
    {
        return $this->ubigeo;
    }

    public function setUbigeo(?string $ubigeo): void
    {
        $data = gUbigeo::find($ubigeo);
        $this->ubigeo = $data['ubigeo'];
        $this->department = $data['dpto_gw'];
        $this->province = $data['prov_gw'];
        $this->district = $data['dist_gw'];
    }

    public function getDepartment(): string
    {
        return $this->department;
    }

    public function setDepartment(string $department): void
    {
        $this->department = $department;
    }

    public function getProvince(): string
    {
        return $this->province;
    }

    public function setProvince(string $province): void
    {
        $this->province = $province;
    }

    public function getDistrict(): string
    {
        return $this->district;
    }

    public function setDistrict(string $district): void
    {
        $this->district = $district;
    }

    public function getAddress(): string
    {
        return $this->address;
    }

    public function setAddress(?string $address): void
    {
        $this->address = $address;
    }
}

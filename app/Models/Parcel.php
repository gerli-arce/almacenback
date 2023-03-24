<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parcel extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = "parcels";
}

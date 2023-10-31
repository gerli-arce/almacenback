<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = "room";
}

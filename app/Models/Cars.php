<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cars extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = "cars";
}

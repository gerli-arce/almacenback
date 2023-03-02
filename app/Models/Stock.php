<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = "stock";
}

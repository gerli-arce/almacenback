<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plans extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'plans';
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Person extends Model
{
    static $rules = [
    ];
    public $timestamps = false;
    protected $table = 'person';
}

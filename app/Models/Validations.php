<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Validations extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'validations';
}

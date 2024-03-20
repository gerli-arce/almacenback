<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Claims extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'claims';
}

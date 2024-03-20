<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClaimsType extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'claims_type';
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationType extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'operation_types';
}

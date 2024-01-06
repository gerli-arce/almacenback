<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangesCar extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'changes_cars';
}

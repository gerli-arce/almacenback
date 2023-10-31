<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotographsByRoom extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'photographs_by_room';
}

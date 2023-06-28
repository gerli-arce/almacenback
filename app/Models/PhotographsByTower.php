<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotographsByTower extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = "photographs_by_tower";
}

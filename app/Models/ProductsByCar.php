<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductsByCar extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'products_by_car';
}

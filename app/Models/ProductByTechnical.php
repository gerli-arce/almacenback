<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductByTechnical extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = "product_by_technical";
}

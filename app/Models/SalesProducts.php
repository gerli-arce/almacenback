<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesProducts extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = "sales_products";
}

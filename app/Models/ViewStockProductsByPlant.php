<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViewStockProductsByPlant extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'view_stock_products_by_plant';
}

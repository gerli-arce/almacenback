<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViewStockRoom extends Model
{
    static $rules = [];
    public $timestamp = false;
    protected $table = "view_stock_rooms";
}

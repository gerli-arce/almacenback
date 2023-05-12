<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViewSales extends Model
{
    static $rules = [];
    public $timestamp = false;
    protected $table = 'view_sales';
}

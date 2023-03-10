<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProducByTechnical extends Model
{
    static $rules = [];
    public $timestamp = false;
    protected $table = "product_by_technical";
}

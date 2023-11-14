<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EntryProducts extends Model
{
    static $rules = [];
    protected $guarded = [];
    public $timestamps = false;
    protected $table = 'entry_products';
}


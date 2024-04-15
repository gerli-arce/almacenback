<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViewValidationsBySale extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'view_validations_by_sales';
}

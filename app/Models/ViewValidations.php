<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViewValidations extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'view_validations';
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViewUsers extends Model
{
    static $rules=[];
    
    public $timestamps = false;
    protected $table = "view_users";
}

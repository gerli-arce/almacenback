<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    static $rules = [
        'role'=>'require',
        'permissions'=>'require',
        'status'=>'require'
    ];

    public $timestamps = false;
}

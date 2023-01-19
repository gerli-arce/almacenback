<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    static $rules = [
        'role'=>'require',
        'priority'=>'require',
        'permissions'=>'require',
        'description'=>'',
        'status'=>'require'
    ];

    public $timestamps = false;
}

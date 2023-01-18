<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    static $rules = [
        'permission'=>'require',
        '_view'=>'require',
        'description'=>'',
        'status'=>'require'
    ];

    public $timestamps = false;
}

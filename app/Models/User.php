<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    static $rules = [
        'username'=>'require',
        'password'=>'require',
        'relative_id'=>'require',
        'auth_token'=>'require',
        '_person'=>'require',
        '_branch'=>'require',
        'origin'=>'require',
        '_role'=>'require',
        'creation_data'=>'require',
        'status'=>'require'
    ];

    public $timestamps = false;
}

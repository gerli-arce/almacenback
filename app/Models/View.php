<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class View extends Model
{
    static $rules =[
        'view'=>'require',
        'path'=>'require',
        'description'=>'require',
        'status'=>'require'
    ];

    public $timestamps = false;
}

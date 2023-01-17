<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    static $rules = [
        'name'=>'require',
        'correlative'=>'require',
        'department'=>'require',
        'province'=>'require',
        'distric'=>'require',
        'address'=>'require',
        'status'=>'require',
    ];
    public $timestamps = false;
}

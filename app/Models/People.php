<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class People extends Model
{
    static $rules = [
        'doc_type'=>'require',
        'doc_number'=>'require',
        'name'=>'require',
        'lastname'=>'require',
        'birthdate'=>'',
        'gender'=>'',
        'email'=>'',
        'phone'=>'',
        'department'=>'',
        'province'=>'',
        'distric'=>'',
        'address'=>'',
        'type'=>'',
        '_branch'=>'',
        'status'=>'require',
    ];

    public $timestamps = false;
}

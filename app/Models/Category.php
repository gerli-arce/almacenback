<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    static $rules = [
        'category'=>'required',
        'description'=>'required',
        'creation_date'=>'required',
        '_creation_user'=>'required',
        'update_date'=>'required',
        '_update_user'=>'required',
        'status'=>'required',
    ];

    public $timestamps = false;
}

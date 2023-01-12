<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categories extends Model
{
    static $rules = [
        'role' => 'unique|require',
        'description' => 'require',
        'permissions' => 'require',
        'status' => 'require'
    ];
    public $timestamps = false;

    public function user(){
        return $this->hasMany(User::class, '_role', 'id');
    }
}

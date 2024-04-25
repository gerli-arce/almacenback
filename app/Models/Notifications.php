<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifications extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = "notifications";
}

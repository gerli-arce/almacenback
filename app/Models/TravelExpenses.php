<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravelExpenses extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'travel_expenses';
}

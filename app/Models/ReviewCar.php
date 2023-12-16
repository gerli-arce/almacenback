<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewCar extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'review_car';
}

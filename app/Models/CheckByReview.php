<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CheckByReview extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'check_by_review';
}

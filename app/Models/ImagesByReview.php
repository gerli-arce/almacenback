<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImagesByReview extends Model
{
    static $rules = [];
    public $timestamps = false;
    protected $table = 'images_by_review';
}

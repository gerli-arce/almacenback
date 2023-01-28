<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Peoples extends Model
{
    static $rules = [
        // 'name'=>'require',
        // 'lastname'=>'require',
        // 'doc_type'=>'require',
        // 'doc_number'=>'require',
        // 'birthdate'=>'',
        // 'gender'=>'',
        // 'email'=>'',
        // 'phone_prefix'=>'',
        // 'phone_number'=>'',
        // 'ubigeo'=>'',
        // 'address'=>'',
        // 'date_update'=>'require',
        // 'date_creation'=>'require',
        // 'origin'=>'require',
        // 'service_update'=>'require',
        // 'service_creation'=>'require',
        // 'ip'=>'require',
        // 'status'=>'require',
    ];

    public $timestamps = false;
    // protected $table = "view_permissionsbyview";

}

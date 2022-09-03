<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Phone extends Model
{
    protected $table = 'customers_phone';
    protected $primaryKey = 'id';
    protected $fillable = ['bo_ativo', 'bo_main', 'bo_whatsapp', 'id_customers', 'created_at', 'phone', 'country_code', 'updated_at'];
}

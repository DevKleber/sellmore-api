<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Calendar extends Model
{
    protected $table = 'calendar';
    protected $primaryKey = 'id';
    protected $fillable = ['date', 'date_end', 'bo_ativo', 'id_usuario', 'id_customers', 'date_end'];
}

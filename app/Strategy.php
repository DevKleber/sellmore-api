<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Strategy extends Model
{
    protected $table = 'strategy';
    protected $primaryKey = 'id';
    protected $fillable = ['strategy', 'id_usuario', 'url_sale'];
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $table = 'usuario_log';
    protected $primaryKey = 'id';
    protected $fillable = [
        'display_resolution',
        'os',
        'device',
        'browser',
        'ip',
        'user_agent',
        'id_user',
        'created_at',
        'updated_at',
        'language',
    ];
}

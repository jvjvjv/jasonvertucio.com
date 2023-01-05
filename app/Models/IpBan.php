<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpBan extends Model
{

    protected $table = 'ip_ban';
    protected $fillable = [
      'ip',
      'banned_method',
      'banned_url',
      'banned_body'
    ];
}

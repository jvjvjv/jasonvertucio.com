<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PhoneMessage extends Model
{
  use Softdeletes;
  protected $fillable = [
    'message','sid','from_phone_id','to_phone_id'
  ];
  protected $hidden = ['created_at','updated_at','deleted_at'];

  public function toNumber() {
    return $this->belongsTo(PhoneNumber::class,'to_phone_id');
  }
  
  public function fromNumber() {
    return $this->belongsTo(PhoneNumber::class,'from_phone_id');
  }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Caller extends Model
{
    protected $guarded = [];

    public function servicePoints()
    {
        return $this->belongsToMany(ServicePoint::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicePoint extends Model
{
    protected $guarded = [];

    public function callers()
    {
        return $this->belongsToMany(Caller::class);
    }
}

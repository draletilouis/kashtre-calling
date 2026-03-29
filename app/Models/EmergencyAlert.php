<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyAlert extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_active'    => 'boolean',
        'triggered_at' => 'datetime',
        'resolved_at'  => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'message',
        'type',
        'is_active',
        'triggered_at',
        'resolved_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'triggered_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];
}

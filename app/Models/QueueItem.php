<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueueItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'queued_at' => 'datetime',
        'updated_in_master_at' => 'datetime',
    ];
}

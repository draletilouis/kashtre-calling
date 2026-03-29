<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CallLog extends Model
{
    protected $guarded = [];

    protected $casts = [
        'called_at'     => 'datetime',
        'audio_enabled' => 'boolean',
        'video_enabled' => 'boolean',
    ];

    public function caller()
    {
        return $this->belongsTo(Caller::class);
    }
}

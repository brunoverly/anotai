<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    protected $fillable = [
        'from',
        'message_id',
        'type',
        'payload'
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PixelEvent extends Model
{
    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

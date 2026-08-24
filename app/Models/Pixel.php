<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pixel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'pixel_id',
        'capi_key',
        'status',
        'test_mode',
        'coverage_type',
        'target_selection',
    ];

    protected $casts = [
        'test_mode' => 'boolean',
        'target_selection' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function events()
    {
        return $this->hasMany(PixelEvent::class, 'pixel_id', 'pixel_id');
    }
}

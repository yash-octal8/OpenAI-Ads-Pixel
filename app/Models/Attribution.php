<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'pixel_id',
        'shopify_order_id',
        'order_number',
        'event_id',
        'oppref',
        'campaign_id',
        'ad_group_id',
        'ad_id',
        'revenue',
        'currency',
        'event_time',
    ];

    protected $casts = [
        'revenue' => 'float',
        'event_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

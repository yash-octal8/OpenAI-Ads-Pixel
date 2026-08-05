<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Osiset\ShopifyApp\Storage\Models\Plan as BasePlan;

class Plan extends BasePlan
{
    use HasFactory;

    protected $table = 'plans';

    protected $guarded = ['id'];

    public function features()
    {
        return $this->belongsToMany(Feature::class)
            ->withPivot('value')
            ->withTimestamps();
    }
}

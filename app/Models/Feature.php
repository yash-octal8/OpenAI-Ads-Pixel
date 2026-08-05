<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'hidden_feature' => 'boolean',
    ];

    public function plans()
    {
        return $this->belongsToMany(Plan::class)
            ->withPivot('value')
            ->withTimestamps();
    }
}

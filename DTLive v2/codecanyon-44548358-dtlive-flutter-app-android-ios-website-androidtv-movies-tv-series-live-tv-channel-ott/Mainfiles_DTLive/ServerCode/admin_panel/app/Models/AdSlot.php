<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdSlot extends Model
{
    protected $table = 'ad_slots';
    protected $guarded = [];

    protected $casts = [
        'storage_type' => 'integer',
        'sort_order' => 'integer',
        'status' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitoringStat extends Model
{
    use HasFactory;

    protected $table = 'monitoring_stats';

    protected $fillable = [
        'website_id',
        'visits',
        'unique_visitors',
        'bandwidth',
        'response_time',
        'is_online',
        'date'
    ];

    protected $casts = [
        'visits' => 'integer',
        'unique_visitors' => 'integer',
        'bandwidth' => 'integer',
        'response_time' => 'integer',
        'is_online' => 'boolean',
        'date' => 'date',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
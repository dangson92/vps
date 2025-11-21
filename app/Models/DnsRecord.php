<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnsRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'website_id',
        'record_type',
        'name',
        'value',
        'ttl',
        'priority',
        'cloudflare_id'
    ];

    protected $casts = [
        'ttl' => 'integer',
        'priority' => 'integer',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VpsServer extends Model
{
    use HasFactory;

    protected $table = 'vps_servers';

    protected $fillable = [
        'name',
        'ip_address',
        'ssh_user',
        'ssh_port',
        'ssh_key_path',
        'worker_key',
        'status',
        'specs'
    ];

    protected $casts = [
        'specs' => 'array',
        'ssh_port' => 'integer',
    ];

    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function generateWorkerKey(): string
    {
        return bin2hex(random_bytes(32));
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CloudflareAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'api_key',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
    ];

    /**
     * Get websites using this Cloudflare account
     */
    public function websites(): HasMany
    {
        return $this->hasMany(Website::class);
    }

    /**
     * Set this account as default
     */
    public function setAsDefault(): void
    {
        // Unset all other defaults
        static::where('id', '!=', $this->id)->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }

    /**
     * Get the default Cloudflare account
     */
    public static function getDefault(): ?self
    {
        return static::where('is_default', true)->first();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'type',
        'template_package',
        'vps_server_id',
        'cloudflare_account_id',
        'document_root',
        'status',
        'content_version',
        'deployed_version',
        'content_updated_at',
        'deployed_at',
        'suspended_at',
        'ssl_enabled',
        'ssl_expires_at',
        'wordpress_config',
        'nginx_config',
        'custom_settings'
    ];

    protected $casts = [
        'ssl_enabled' => 'boolean',
        'ssl_expires_at' => 'datetime',
        'content_updated_at' => 'datetime',
        'deployed_at' => 'datetime',
        'suspended_at' => 'datetime',
        'wordpress_config' => 'array',
        'nginx_config' => 'array',
        'custom_settings' => 'array',
    ];

    public function vpsServer(): BelongsTo
    {
        return $this->belongsTo(VpsServer::class);
    }

    public function cloudflareAccount(): BelongsTo
    {
        return $this->belongsTo(CloudflareAccount::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function dnsRecords(): HasMany
    {
        return $this->hasMany(DnsRecord::class);
    }

    public function monitoringStats(): HasMany
    {
        return $this->hasMany(MonitoringStat::class);
    }

    public function isDeployed(): bool
    {
        return $this->status === 'deployed';
    }

    public function hasUnpublishedChanges(): bool
    {
        return (int)($this->content_version ?? 0) > (int)($this->deployed_version ?? 0);
    }

    public function getDocumentRoot(): string
    {
        if ($this->document_root) {
            return $this->document_root;
        }

        $domain = $this->domain;
        $parts = explode('.', $domain);

        // If subdomain (more than 2 parts), put inside parent domain folder
        if (count($parts) > 2) {
            $subdomain = $parts[0];
            $parentDomain = implode('.', array_slice($parts, 1));
            return "/var/www/{$parentDomain}/{$subdomain}";
        }

        return "/var/www/{$domain}";
    }
}

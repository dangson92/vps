<?php

namespace App\Services;

use App\Models\Website;
use App\Models\DnsRecord;
use App\Models\CloudflareAccount;
use Cloudflare\API\Auth\APIToken;
use Cloudflare\API\Endpoints\DNS;
use Cloudflare\API\Endpoints\Zones;
use Cloudflare\API\Adapter\Guzzle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;

class DnsService
{
    private $dns;
    private $zoneId;
    private $zones;
    private $website;

    public function __construct(?Website $website = null)
    {
        $this->website = $website;
        $this->initializeCloudflare();
    }

    private function initializeCloudflare(): void
    {
        // Try to use website's Cloudflare account first
        if ($this->website && $this->website->cloudflareAccount) {
            $apiToken = Crypt::decryptString($this->website->cloudflareAccount->api_key);
        } else {
            // Fallback to config if no website or no account assigned
            $apiToken = config('services.cloudflare.api_token');
        }

        $this->zoneId = config('services.cloudflare.zone_id');

        if ($apiToken) {
            $key = new APIToken($apiToken);
            $adapter = new Guzzle($key);
            $this->dns = new DNS($adapter);
            $this->zones = new Zones($adapter);
        }
    }

    public function createRecords(Website $website): void
    {
        if (!$this->dns) {
            Log::warning('Cloudflare not configured, skipping DNS records');
            return;
        }

        try {
            // Create A record
            $aRecord = $this->createARecord($website);
            
            // Create www CNAME record
            $cnameRecord = $this->createCnameRecord($website);

            Log::info("DNS records created for {$website->domain}");
        } catch (\Exception $e) {
            Log::error("Failed to create DNS records for {$website->domain}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createARecord(Website $website): DnsRecord
    {
        $vps = $website->vpsServer;
        $zoneId = $this->resolveZoneIdForDomain($website->domain);
        $name = $website->domain;
        $value = $vps->ip_address;
        
        $cloudflareId = null;
        try {
            $cloudflareId = $this->dns->getRecordID($zoneId, 'A', $name);
            if ($cloudflareId) {
                $this->dns->updateRecordDetails($zoneId, $cloudflareId, [
                    'name' => $name,
                    'content' => $value,
                    'ttl' => 300,
                    'proxied' => false,
                ]);
            } else {
                $this->dns->addRecord($zoneId, 'A', $name, $value, 300, false, '', []);
                $cloudflareId = $this->dns->getRecordID($zoneId, 'A', $name);
            }
        } catch (\Throwable $e) {
            Log::warning('Cloudflare A record sync failed', [
                'domain' => $website->domain,
                'error' => $e->getMessage()
            ]);
        }

        return DnsRecord::updateOrCreate(
            [
                'website_id' => $website->id,
                'record_type' => 'A',
                'name' => $name,
            ],
            [
                'value' => $value,
                'ttl' => 300,
                'cloudflare_id' => $cloudflareId,
            ]
        );
    }

    public function createCnameRecord(Website $website): DnsRecord
    {
        $zoneId = $this->resolveZoneIdForDomain($website->domain);
        $name = 'www.' . $website->domain;
        $value = $website->domain;
        $cloudflareId = null;
        try {
            $cloudflareId = $this->dns->getRecordID($zoneId, 'CNAME', $name);
            if ($cloudflareId) {
                $this->dns->updateRecordDetails($zoneId, $cloudflareId, [
                    'name' => $name,
                    'content' => $value,
                    'ttl' => 300,
                    'proxied' => false,
                ]);
            } else {
                $this->dns->addRecord($zoneId, 'CNAME', $name, $value, 300, false, '', []);
                $cloudflareId = $this->dns->getRecordID($zoneId, 'CNAME', $name);
            }
        } catch (\Throwable $e) {
            Log::warning('Cloudflare CNAME record sync failed', [
                'domain' => $website->domain,
                'error' => $e->getMessage()
            ]);
        }

        return DnsRecord::updateOrCreate(
            [
                'website_id' => $website->id,
                'record_type' => 'CNAME',
                'name' => $name,
            ],
            [
                'value' => $value,
                'ttl' => 300,
                'cloudflare_id' => $cloudflareId,
            ]
        );
    }

    public function deleteWebsiteRecords(Website $website): void
    {
        // Try to resolve zone ID, but don't fail if not found (website may not be deployed)
        try {
            $zoneId = $this->resolveZoneIdForDomain($website->domain);
        } catch (\Throwable $e) {
            Log::info("Zone not found for domain {$website->domain}, deleting local DNS records only");
            // Delete local records even if Cloudflare zone not found
            $website->dnsRecords()->delete();
            return;
        }

        foreach ($website->dnsRecords as $record) {
            try {
                $cfId = $record->cloudflare_id;
                if (!$cfId) {
                    try {
                        $cfId = $this->dns->getRecordID($zoneId, $record->record_type, $record->name);
                    } catch (\Throwable $e) {
                    }
                }
                if ($cfId) {
                    $this->dns->deleteRecord($zoneId, $cfId);
                }
                $record->delete();
            } catch (\Exception $e) {
                Log::warning("Failed to delete DNS record", [
                    'record_id' => $record->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    public function createRecord(Request $request, Website $website): DnsRecord
    {
        $validated = $request->validate([
            'record_type' => 'required|in:A,AAAA,CNAME,MX,TXT',
            'name' => 'required|string|max:255',
            'value' => 'required|string|max:255',
            'ttl' => 'integer|min:60|max:86400',
            'priority' => 'nullable|integer|min:0|max:65535',
        ]);

        $zoneId = $this->resolveZoneIdForDomain($website->domain);
        $priority = '';
        if ($validated['record_type'] === 'MX' && isset($validated['priority'])) {
            $priority = (string) $validated['priority'];
        }
        $cloudflareId = null;
        try {
            $cloudflareId = $this->dns->getRecordID($zoneId, $validated['record_type'], $validated['name']);
            if ($cloudflareId) {
                $details = [
                    'name' => $validated['name'],
                    'content' => $validated['value'],
                    'ttl' => $validated['ttl'] ?? 300,
                    'proxied' => false,
                ];
                if ($validated['record_type'] === 'MX' && $priority !== '') {
                    $details['priority'] = (int) $priority;
                }
                $this->dns->updateRecordDetails($zoneId, $cloudflareId, $details);
            } else {
                $this->dns->addRecord(
                    $zoneId,
                    $validated['record_type'],
                    $validated['name'],
                    $validated['value'],
                    $validated['ttl'] ?? 300,
                    false,
                    $priority,
                    []
                );
                $cloudflareId = $this->dns->getRecordID($zoneId, $validated['record_type'], $validated['name']);
            }
        } catch (\Throwable $e) {
            Log::warning('Cloudflare record sync failed', [
                'domain' => $website->domain,
                'type' => $validated['record_type'],
                'name' => $validated['name'],
                'error' => $e->getMessage()
            ]);
        }

        return DnsRecord::updateOrCreate(
            [
                'website_id' => $website->id,
                'record_type' => $validated['record_type'],
                'name' => $validated['name'],
            ],
            [
                'value' => $validated['value'],
                'ttl' => $validated['ttl'] ?? 300,
                'priority' => $validated['priority'] ?? null,
                'cloudflare_id' => $cloudflareId,
            ]
        );
    }

    public function deleteRecord(string $recordId): void
    {
        $record = DnsRecord::findOrFail($recordId);
        $zoneId = $this->resolveZoneIdForDomain($record->website->domain);
        
        $cfId = $record->cloudflare_id;
        if (!$cfId) {
            try {
                $cfId = $this->dns->getRecordID($zoneId, $record->record_type, $record->name);
            } catch (\Throwable $e) {
            }
        }
        if ($cfId) {
            $this->dns->deleteRecord($zoneId, $cfId);
        }
        
        $record->delete();
    }

    private function resolveZoneIdForDomain(string $domain): string
    {
        if ($this->zoneId) {
            return $this->zoneId;
        }
        if (!$this->zones) {
            throw new \Exception('Cloudflare not configured');
        }
        $labels = explode('.', $domain);
        for ($i = 0; $i < count($labels) - 1; $i++) {
            $candidate = implode('.', array_slice($labels, $i));
            try {
                $id = $this->zones->getZoneID($candidate);
                if ($id) {
                    return $id;
                }
            } catch (\Throwable $e) {
            }
        }
        throw new \Exception('Unable to resolve Cloudflare zone for domain');
    }
}
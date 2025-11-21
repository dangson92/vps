<?php

namespace App\Services;

use App\Models\Website;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SslService
{
    public function generate(Website $website): void
    {
        $vps = $website->vpsServer;
        
        if (!$vps->isActive()) {
            throw new \Exception('VPS server is not active');
        }

        try {
            $response = Http::timeout(300)
                ->connectTimeout(30)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$vps->ip_address}:8080/api/generate-ssl", [
                    'domain' => $website->domain,
                    'email' => config('services.ssl.email', 'admin@' . $website->domain),
                    'document_root' => $website->getDocumentRoot(),
                ]);

            if (!$response->successful()) {
                throw new \Exception('SSL generation failed: ' . $response->body());
            }

            $result = $response->json();
            
            $website->update([
                'ssl_enabled' => true,
                'ssl_expires_at' => now()->addMonths(3), // Let's Encrypt certificates are valid for 3 months
            ]);

            Log::info("SSL certificate generated for {$website->domain}");
        } catch (ConnectionException $e) {
            $response = Http::timeout(300)
                ->connectTimeout(30)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://127.0.0.1:8080/api/generate-ssl", [
                    'domain' => $website->domain,
                    'email' => config('services.ssl.email', 'admin@' . $website->domain),
                    'document_root' => $website->getDocumentRoot(),
                ]);

            if (!$response->successful()) {
                Log::error("Failed to generate SSL for {$website->domain}", [
                    'error' => $response->body()
                ]);
                throw new \Exception('SSL generation failed: ' . $response->body());
            }

            $result = $response->json();
            
            $website->update([
                'ssl_enabled' => true,
                'ssl_expires_at' => now()->addMonths(3),
            ]);

            Log::info("SSL certificate generated for {$website->domain}");
        } catch (\Exception $e) {
            Log::error("Failed to generate SSL for {$website->domain}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function renew(Website $website): void
    {
        if (!$website->ssl_enabled) {
            throw new \Exception('SSL is not enabled for this website');
        }

        if ($website->ssl_expires_at && $website->ssl_expires_at->diffInDays(now()) > 30) {
            throw new \Exception('SSL certificate does not need renewal yet');
        }

        $this->generate($website);
    }

    public function revoke(Website $website): void
    {
        $vps = $website->vpsServer;
        
        if (!$vps->isActive()) {
            throw new \Exception('VPS server is not active');
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$vps->ip_address}:8080/api/revoke-ssl", [
                    'domain' => $website->domain,
                ]);

            if (!$response->successful()) {
                throw new \Exception('SSL revocation failed: ' . $response->body());
            }

            $website->update([
                'ssl_enabled' => false,
                'ssl_expires_at' => null,
            ]);

            Log::info("SSL certificate revoked for {$website->domain}");
        } catch (\Exception $e) {
            Log::error("Failed to revoke SSL for {$website->domain}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
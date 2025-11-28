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
            $port = (int) (config('services.worker.port') ?? 8080);
            $paths = (array) (config('services.worker.ssl_generate_endpoints') ?? ['/api/generate-ssl','/api/generate_ssl','/api/ssl/generate']);
            $hosts = ["{$vps->ip_address}", '127.0.0.1'];

            $response = null;
            $lastBody = null;
            $lastStatus = null;
            foreach ($hosts as $host) {
                foreach ($paths as $path) {
                    $resp = Http::timeout(300)
                        ->connectTimeout(30)
                        ->withHeaders([
                            'X-Worker-Key' => $vps->worker_key,
                            'Content-Type' => 'application/json',
                        ])
                        ->post("http://{$host}:{$port}{$path}", [
                            'domain' => $website->domain,
                            'email' => config('services.ssl.email', 'admin@' . $website->domain),
                            'document_root' => $website->getDocumentRoot(),
                        ]);
                    if ($resp->successful()) {
                        $response = $resp;
                        break 2;
                    }
                    $lastStatus = $resp->status();
                    $lastBody = (string) $resp->body();
                    if ($lastStatus !== 404) {
                        throw new \Exception('SSL generation failed: ' . ($lastBody ?: 'unknown_error'));
                    }
                }
            }
            if (!$response || !$response->successful()) {
                $msg = 'SSL generation failed (endpoint not found): ' . ($lastBody ?: 'not_found');
                throw new \Exception($msg);
            }

            $result = $response->json();
            
            $website->update([
                'ssl_enabled' => true,
                'ssl_expires_at' => now()->addMonths(3), // Let's Encrypt certificates are valid for 3 months
            ]);

            Log::info("SSL certificate generated for {$website->domain}");

            $this->updateNginxSsl($website);
        } catch (ConnectionException $e) {
            $port = (int) (config('services.worker.port') ?? 8080);
            $paths = (array) (config('services.worker.ssl_generate_endpoints') ?? ['/api/generate-ssl','/api/generate_ssl','/api/ssl/generate']);
            $hosts = ['127.0.0.1'];
            $response = null;
            $lastBody = null;
            $lastStatus = null;
            foreach ($hosts as $host) {
                foreach ($paths as $path) {
                    $resp = Http::timeout(300)
                        ->connectTimeout(30)
                        ->withHeaders([
                            'X-Worker-Key' => $vps->worker_key,
                            'Content-Type' => 'application/json',
                        ])
                        ->post("http://{$host}:{$port}{$path}", [
                            'domain' => $website->domain,
                            'email' => config('services.ssl.email', 'admin@' . $website->domain),
                            'document_root' => $website->getDocumentRoot(),
                        ]);
                    if ($resp->successful()) {
                        $response = $resp;
                        break 2;
                    }
                    $lastStatus = $resp->status();
                    $lastBody = (string) $resp->body();
                    if ($lastStatus !== 404) {
                        Log::error("Failed to generate SSL for {$website->domain}", [
                            'error' => $lastBody ?: 'unknown_error'
                        ]);
                        throw new \Exception('SSL generation failed: ' . ($lastBody ?: 'unknown_error'));
                    }
                }
            }
            if (!$response || !$response->successful()) {
                $msg = 'SSL generation failed (endpoint not found): ' . ($lastBody ?: 'not_found');
                Log::error("Failed to generate SSL for {$website->domain}", [
                    'error' => $msg
                ]);
                throw new \Exception($msg);
            }

            $result = $response->json();
            
            $website->update([
                'ssl_enabled' => true,
                'ssl_expires_at' => now()->addMonths(3),
            ]);

            Log::info("SSL certificate generated for {$website->domain}");

            $this->updateNginxSsl($website);
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
            $port = (int) (config('services.worker.port') ?? 8080);
            $paths = (array) (config('services.worker.ssl_revoke_endpoints') ?? ['/api/revoke-ssl','/api/revoke_ssl','/api/ssl/revoke']);
            $hosts = ["{$vps->ip_address}", '127.0.0.1'];
            $response = null;
            $lastBody = null;
            $lastStatus = null;
            foreach ($hosts as $host) {
                foreach ($paths as $path) {
                    $resp = Http::timeout(60)
                        ->withHeaders([
                            'X-Worker-Key' => $vps->worker_key,
                            'Content-Type' => 'application/json',
                        ])
                        ->post("http://{$host}:{$port}{$path}", [
                            'domain' => $website->domain,
                        ]);
                    if ($resp->successful()) {
                        $response = $resp;
                        break 2;
                    }
                    $lastStatus = $resp->status();
                    $lastBody = (string) $resp->body();
                    if ($lastStatus !== 404) {
                        throw new \Exception('SSL revocation failed: ' . ($lastBody ?: 'unknown_error'));
                    }
                }
            }
            if (!$response || !$response->successful()) {
                $msg = 'SSL revocation failed (endpoint not found): ' . ($lastBody ?: 'not_found');
                throw new \Exception($msg);
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

    private function updateNginxSsl(Website $website): void
    {
        $vps = $website->vpsServer;
        if (!$vps || !$vps->isActive()) return;
        $port = (int) (config('services.worker.port') ?? 8080);
        $hosts = ["{$vps->ip_address}", '127.0.0.1'];
        $conf = $this->buildSslNginxConfig($website);
        $resp = null;
        foreach ($hosts as $host) {
            $resp = \Illuminate\Support\Facades\Http::timeout(60)
                ->withHeaders([
                    'X-Worker-Key' => $vps->worker_key,
                    'Content-Type' => 'application/json',
                ])
                ->post("http://{$host}:{$port}/api/update-nginx", [
                    'domain' => $website->domain,
                    'nginx_config' => $conf,
                ]);
            if ($resp->successful()) {
                return;
            }
        }
        throw new \Exception('Failed to update nginx SSL config: ' . ($resp ? $resp->body() : 'unknown_error'));
    }

    private function buildSslNginxConfig(Website $website): string
    {
        $domain = $website->domain;
        $documentRoot = $website->getDocumentRoot();
        $phpSocket = '/var/run/php/php8.2-fpm.sock';
        $conf = "server {\n";
        $conf .= "    listen 80;\n";
        $conf .= "    server_name {$domain} www.{$domain};\n";
        $conf .= "    root {$documentRoot};\n";
        $conf .= "    index index.html index.htm index.php;\n\n";
        $conf .= "    location ^~ /.well-known/acme-challenge/ {\n";
        $conf .= "        root {$documentRoot};\n";
        $conf .= "        default_type \"text/plain\";\n";
        $conf .= "        try_files \$uri =404;\n";
        $conf .= "    }\n\n";
        if ($website->type === 'wordpress') {
            $conf .= "    location / {\n";
            $conf .= "        try_files \$uri \$uri/ /index.php?\$args;\n";
            $conf .= "    }\n\n";
            $conf .= "    location ~ \\.(php)$ {\n";
            $conf .= "        include snippets/fastcgi-php.conf;\n";
            $conf .= "        fastcgi_pass unix:{$phpSocket};\n";
            $conf .= "    }\n\n";
            $conf .= "    location ~ /\\.ht {\n";
            $conf .= "        deny all;\n";
            $conf .= "    }\n";
        } else {
            $conf .= "    location / {\n";
            $conf .= "        try_files \$uri \$uri/ \$uri/index.html =404;\n";
            $conf .= "    }\n";
        }
        $conf .= "}\n\n";
        $conf .= "server {\n";
        $conf .= "    listen 443 ssl;\n";
        $conf .= "    server_name {$domain} www.{$domain};\n";
        $conf .= "    root {$documentRoot};\n";
        $conf .= "    index index.html index.htm index.php;\n";
        $conf .= "    ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;\n";
        $conf .= "    ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;\n\n";
        if ($website->type === 'wordpress') {
            $conf .= "    location / {\n";
            $conf .= "        try_files \$uri \$uri/ /index.php?\$args;\n";
            $conf .= "    }\n\n";
            $conf .= "    location ~ \\.(php)$ {\n";
            $conf .= "        include snippets/fastcgi-php.conf;\n";
            $conf .= "        fastcgi_pass unix:{$phpSocket};\n";
            $conf .= "    }\n\n";
            $conf .= "    location ~ /\\.ht {\n";
            $conf .= "        deny all;\n";
            $conf .= "    }\n";
        } else {
            $conf .= "    location / {\n";
            $conf .= "        try_files \$uri \$uri/ \$uri/index.html =404;\n";
            $conf .= "    }\n";
        }
        $conf .= "}\n";
        return $conf;
    }
}

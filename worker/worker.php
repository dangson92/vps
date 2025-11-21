<?php

/**
 * VPS Manager Worker Node
 * This script runs on each VPS server to receive and execute commands from the master server
 */

class VpsWorker
{
    private $configFile = '/etc/vps-worker/config.json';
    private $logFile = '/var/log/vps-worker/worker.log';
    private $config;
    private $masterServer;
    private $workerKey;

    public function __construct()
    {
        $this->loadConfig();
        $this->setupLogging();
    }

    private function loadConfig(): void
    {
        if (!file_exists($this->configFile)) {
            $this->createDefaultConfig();
        }

        $this->config = json_decode(file_get_contents($this->configFile), true);
        $this->masterServer = $this->config['master_server'] ?? 'localhost:8000';
        $this->workerKey = $this->config['worker_key'] ?? '';

        if (empty($this->workerKey)) {
            $this->workerKey = $this->generateWorkerKey();
            $this->config['worker_key'] = $this->workerKey;
            $this->saveConfig();
        }
    }

    private function createDefaultConfig(): void
    {
        $defaultConfig = [
            'master_server' => 'localhost:8000',
            'worker_key' => '',
            'web_port' => 8080,
            'log_level' => 'info',
            'nginx_sites_available' => '/etc/nginx/sites-available',
            'nginx_sites_enabled' => '/etc/nginx/sites-enabled',
            'document_root' => '/var/www',
            'mysql_host' => 'localhost',
            'mysql_user' => 'root',
            'mysql_password' => '',
        ];

        $dir = dirname($this->configFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->configFile, json_encode($defaultConfig, JSON_PRETTY_PRINT));
    }

    private function saveConfig(): void
    {
        file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT));
    }

    private function generateWorkerKey(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function setupLogging(): void
    {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logEntry = "[{$timestamp}] {$level}: {$message}{$contextStr}\n";
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function startWebServer(): void
    {
        $this->log('info', 'Starting worker web server on port ' . $this->config['web_port']);
        
        $command = sprintf(
            'php -S 0.0.0.0:%d %s',
            $this->config['web_port'],
            __FILE__
        );
        passthru($command);
    }

    public function handleRequest(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Verify worker key
        $headers = getallheaders();
        $workerKey = $headers['X-Worker-Key'] ?? $headers['x-worker-key'] ?? '';
        
        if ($workerKey !== $this->workerKey) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid worker key']);
            return;
        }

        $this->log('info', 'Handling request', ['method' => $method, 'path' => $path]);

        try {
            switch ($path) {
                case '/api/deploy':
                    $this->handleDeploy();
                    break;
                case '/api/deploy-page':
                    $this->handleDeployPage();
                    break;
                case '/api/remove-page':
                    $this->handleRemovePage();
                    break;
                case '/api/remove-website':
                    $this->handleRemoveWebsite();
                    break;
                case '/api/generate-ssl':
                    $this->handleGenerateSsl();
                    break;
                case '/api/revoke-ssl':
                    $this->handleRevokeSsl();
                    break;
                case '/api/deactivate-website':
                    $this->handleDeactivateWebsite();
                    break;
                default:
                    http_response_code(404);
                    echo json_encode(['error' => 'Endpoint not found']);
            }
        } catch (\Exception $e) {
            $this->log('error', 'Request failed', ['error' => $e->getMessage()]);
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function handleDeploy(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $domain = $data['domain'] ?? '';
        $type = $data['type'] ?? 'html';
        $documentRoot = $data['document_root'] ?? "/var/www/{$domain}";
        $nginxConfig = $data['nginx_config'] ?? '';
        $wordpressConfig = $data['wordpress_config'] ?? [];

        $this->log('info', 'Deploying website', ['domain' => $domain, 'type' => $type]);

        // Create document root
        $this->createDocumentRoot($documentRoot);

        // Deploy based on type
        if ($type === 'wordpress') {
            $this->deployWordPress($domain, $documentRoot, $wordpressConfig);
        } elseif ($type === 'laravel1') {
            $this->deployLaravel1($domain, $documentRoot);
        } else {
            $this->deployHtml($domain, $documentRoot);
        }

        // Create nginx configuration
        $this->createNginxConfig($domain, $documentRoot, $nginxConfig);

        // Reload nginx
        $this->reloadNginx();

        echo json_encode(['status' => 'deployed', 'message' => 'Website deployed successfully']);
    }

    private function handleDeployPage(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $pagePath = $data['page_path'] ?? '';
        $filename = $data['filename'] ?? '';
        $content = $data['content'] ?? '';
        $documentRoot = $data['document_root'] ?? '';

        $targetDir = $documentRoot;
        if (!empty($pagePath) && $pagePath !== '/') {
            $normalized = '/' . ltrim($pagePath, '/');
            $targetDir = rtrim($documentRoot, '/') . $normalized;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
        }

        $filePath = rtrim($targetDir, '/') . '/' . $filename;
        
        $this->log('info', 'Deploying page', ['path' => $pagePath, 'file' => $filename]);

        if (file_put_contents($filePath, $content) === false) {
            throw new \Exception("Failed to write page to {$filePath}");
        }

        echo json_encode(['status' => 'deployed', 'message' => 'Page deployed successfully']);
    }

    private function handleRemovePage(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $filename = $data['filename'] ?? '';
        $documentRoot = $data['document_root'] ?? '';
        $filePath = "{$documentRoot}/{$filename}";

        $this->log('info', 'Removing page', ['file' => $filename]);

        if (file_exists($filePath)) {
            unlink($filePath);
        }

        echo json_encode(['status' => 'removed', 'message' => 'Page removed successfully']);
    }

    private function handleRemoveWebsite(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $domain = $data['domain'] ?? '';
        $documentRoot = $data['document_root'] ?? '';
        $wordpressConfig = $data['wordpress_config'] ?? [];

        $this->log('info', 'Removing website', ['domain' => $domain]);

        // Remove document root
        if (is_dir($documentRoot)) {
            $this->removeDirectory($documentRoot);
        }

        // Remove nginx configuration
        $this->removeNginxConfig($domain);

        // Remove WordPress database if exists
        if (!empty($wordpressConfig['db_name'])) {
            $this->removeDatabase($wordpressConfig['db_name']);
        }

        // Reload nginx
        $this->reloadNginx();

        echo json_encode(['status' => 'removed', 'message' => 'Website removed successfully']);
    }

    private function handleGenerateSsl(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $domain = $data['domain'] ?? '';
        $email = $data['email'] ?? 'admin@' . $domain;
        $documentRoot = $data['document_root'] ?? "/var/www/{$domain}";

        $this->log('info', 'Generating SSL certificate', ['domain' => $domain]);

        // Check if certbot is installed
        if (!$this->commandExists('certbot')) {
            throw new \Exception('Certbot is not installed. Please install certbot first.');
        }

        $this->ensureAcmeLocation($domain, $documentRoot);
        $this->ensureAcmeDirectory($documentRoot);
        
        $command = sprintf(
            'certbot certonly --webroot --force-renewal -w %s -d %s -d %s --email %s --agree-tos --non-interactive',
            escapeshellarg($documentRoot),
            escapeshellarg($domain),
            escapeshellarg('www.' . $domain),
            escapeshellarg($email)
        );

        $outputLines = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $outputLines, $returnCode);

        $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
        $keyPath = "/etc/letsencrypt/live/{$domain}/privkey.pem";
        
        if ($returnCode !== 0 && !(file_exists($certPath) && file_exists($keyPath))) {
            throw new \Exception('SSL certificate generation failed: ' . implode("\n", $outputLines));
        }

        $this->updateNginxForSsl($domain, $documentRoot);

        echo json_encode(['status' => 'generated', 'message' => 'SSL certificate generated successfully']);
    }

    private function ensureAcmeLocation(string $domain, string $documentRoot): void
    {
        $configFile = "{$this->config['nginx_sites_available']}/{$domain}";
        if (!file_exists($configFile)) {
            return;
        }

        $config = file_get_contents($configFile);
        if (strpos($config, '.well-known/acme-challenge') !== false) {
            return;
        }

        $acmeBlock = "\n    location ^~ /.well-known/acme-challenge/ {\n" .
            "        root {$documentRoot};\n" .
            "        default_type \"text/plain\";\n" .
            "        try_files $uri =404;\n" .
            "    }\n";

        $config = preg_replace('/(root\s+[^;]+;)/', "$1{$acmeBlock}", $config, 1);
        file_put_contents($configFile, $config);
        $this->reloadNginx();
    }

    private function ensureAcmeDirectory(string $documentRoot): void
    {
        $acmeDir = rtrim($documentRoot, '/'). '/.well-known/acme-challenge';
        if (!is_dir($acmeDir)) {
            mkdir($acmeDir, 0755, true);
        }
        $testFile = $acmeDir . '/test';
        if (!file_exists($testFile)) {
            file_put_contents($testFile, 'ok');
        }
    }

    private function handleRevokeSsl(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $domain = $data['domain'] ?? '';

        $this->log('info', 'Revoking SSL certificate', ['domain' => $domain]);

        $command = sprintf('certbot revoke --cert-name %s --non-interactive', escapeshellarg($domain));
        shell_exec($command);

        echo json_encode(['status' => 'revoked', 'message' => 'SSL certificate revoked successfully']);
    }

    private function createDocumentRoot(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    private function deployHtml(string $domain, string $documentRoot): void
    {
        if (!file_exists("{$documentRoot}/index.html")) {
            $indexContent = "<h1>Welcome to {$domain}</h1>\n<p>Your website is ready!</p>";
            file_put_contents("{$documentRoot}/index.html", $indexContent);
        }
    }

    private function deployLaravel1(string $domain, string $documentRoot): void
    {
        $this->log('info', 'Deploying Laravel1 website', ['domain' => $domain]);

        // Create templates directory
        $templatesDir = "{$documentRoot}/templates";
        if (!is_dir($templatesDir)) {
            mkdir($templatesDir, 0755, true);
        }

        // Laravel1 pages are deployed individually via deployPage
        // Just create a placeholder if no index exists
        if (!file_exists("{$documentRoot}/index.html")) {
            $indexContent = "<!DOCTYPE html><html><head><title>{$domain}</title></head><body><h1>Welcome to {$domain}</h1></body></html>";
            file_put_contents("{$documentRoot}/index.html", $indexContent);
        }
    }

    private function deployWordPress(string $domain, string $documentRoot, array $config): void
    {
        $this->log('info', 'Deploying WordPress', ['domain' => $domain]);

        // Download WordPress
        $wpZip = '/tmp/wordpress-latest.zip';
        shell_exec('wget -O ' . escapeshellarg($wpZip) . ' https://wordpress.org/latest.zip');
        
        // Extract WordPress
        shell_exec('cd /tmp && unzip -q ' . escapeshellarg($wpZip));
        
        // Move WordPress files
        shell_exec('mv /tmp/wordpress/* ' . escapeshellarg($documentRoot) . '/');
        
        // Clean up
        unlink($wpZip);
        shell_exec('rm -rf /tmp/wordpress');

        // Create database
        $dbName = $config['db_name'] ?? 'wp_' . str_replace('.', '_', $domain);
        $dbUser = $config['db_user'] ?? $dbName;
        $dbPassword = $config['db_password'] ?? $this->generatePassword();

        $this->createDatabase($dbName, $dbUser, $dbPassword);

        // Create wp-config.php
        $this->createWpConfig($documentRoot, $dbName, $dbUser, $dbPassword, $domain);
    }

    private function createDatabase(string $dbName, string $dbUser, string $dbPassword): void
    {
        $mysql = sprintf(
            'mysql -h%s -u%s -p%s',
            escapeshellarg($this->config['mysql_host']),
            escapeshellarg($this->config['mysql_user']),
            escapeshellarg($this->config['mysql_password'])
        );

        // Create database
        $sql = "CREATE DATABASE IF NOT EXISTS `{$dbName}`;";
        shell_exec("echo " . escapeshellarg($sql) . " | {$mysql}");

        // Create user and grant privileges
        $sql = "CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPassword}';";
        $sql .= "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'localhost';";
        $sql .= "FLUSH PRIVILEGES;";
        
        shell_exec("echo " . escapeshellarg($sql) . " | {$mysql}");
    }

    private function createWpConfig(string $documentRoot, string $dbName, string $dbUser, string $dbPassword, string $domain): void
    {
        $wpConfig = "{$documentRoot}/wp-config.php";
        
        if (file_exists("{$documentRoot}/wp-config-sample.php")) {
            $config = file_get_contents("{$documentRoot}/wp-config-sample.php");
            
            $config = str_replace('database_name_here', $dbName, $config);
            $config = str_replace('username_here', $dbUser, $config);
            $config = str_replace('password_here', $dbPassword, $config);
            $config = str_replace('localhost', $this->config['mysql_host'], $config);
            
            // Add salts
            $salts = $this->generateWordPressSalts();
            $config = preg_replace('/define\(\'AUTH_KEY\'.*?\);/', $salts['auth_key'], $config);
            $config = preg_replace('/define\(\'SECURE_AUTH_KEY\'.*?\);/', $salts['secure_auth_key'], $config);
            $config = preg_replace('/define\(\'LOGGED_IN_KEY\'.*?\);/', $salts['logged_in_key'], $config);
            $config = preg_replace('/define\(\'NONCE_KEY\'.*?\);/', $salts['nonce_key'], $config);
            $config = preg_replace('/define\(\'AUTH_SALT\'.*?\);/', $salts['auth_salt'], $config);
            $config = preg_replace('/define\(\'SECURE_AUTH_SALT\'.*?\);/', $salts['secure_auth_salt'], $config);
            $config = preg_replace('/define\(\'LOGGED_IN_SALT\'.*?\);/', $salts['logged_in_salt'], $config);
            $config = preg_replace('/define\(\'NONCE_SALT\'.*?\);/', $salts['nonce_salt'], $config);
            
            file_put_contents($wpConfig, $config);
        }
    }

    private function createNginxConfig(string $domain, string $documentRoot, string $nginxConfig): void
    {
        $configFile = "{$this->config['nginx_sites_available']}/{$domain}";
        
        if (!empty($nginxConfig)) {
            file_put_contents($configFile, $nginxConfig);
        } else {
            // Generate default nginx config
            $config = $this->generateDefaultNginxConfig($domain, $documentRoot);
            file_put_contents($configFile, $config);
        }

        // Enable site
        $enabledLink = "{$this->config['nginx_sites_enabled']}/{$domain}";
        if (!file_exists($enabledLink)) {
            symlink($configFile, $enabledLink);
        }
    }

    private function generateDefaultNginxConfig(string $domain, string $documentRoot): string
    {
        return <<<NGINX
server {
    listen 80;
    server_name {$domain} www.{$domain};
    root {$documentRoot};
    index index.html index.htm index.php;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \\.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\\.ht {
        deny all;
    }
}
NGINX;
    }

    private function updateNginxForSsl(string $domain, string $documentRoot): void
    {
        $configFile = "{$this->config['nginx_sites_available']}/{$domain}";
        
        if (file_exists($configFile)) {
            $config = file_get_contents($configFile);
            $redirectLocation = <<<RED
    location / {
        return 301 https://\$host\$request_uri;
    }
RED;
            $config = str_replace(
                "    location / {\n        try_files \$uri \$uri/ =404;\n    }\n",
                $redirectLocation . "\n",
                $config
            );
            $config = str_replace(
                "    location / {\n        try_files \$uri \$uri/ /index.php?\$args;\n    }\n\n",
                $redirectLocation . "\n\n",
                $config
            );
            
            if (strpos($config, 'listen 443 ssl') === false) {
                $httpsBlock = <<<HTTPS
server {
    listen 443 ssl http2;
    server_name {$domain} www.{$domain};
    root {$documentRoot};
    index index.html index.htm index.php;
    ssl_certificate /etc/letsencrypt/live/{$domain}/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/{$domain}/privkey.pem;
    include /etc/letsencrypt/options-ssl-nginx.conf;
    ssl_dhparam /etc/letsencrypt/ssl-dhparams.pem;

    location / {
        try_files \$uri \$uri/ =404;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
HTTPS;
                $config .= "\n" . $httpsBlock;
            }
            $bytes = file_put_contents($configFile, $config);
            if ($bytes === false) {
                $this->log('error', 'Failed to write HTTPS nginx config', ['file' => $configFile]);
                throw new \Exception('Failed to write HTTPS nginx config');
            } else {
                $this->log('info', 'Updated nginx config with HTTPS block', ['file' => $configFile]);
            }

            $this->reloadNginx();
        }
    }

    private function removeNginxConfig(string $domain): void
    {
        $configFile = "{$this->config['nginx_sites_available']}/{$domain}";
        $enabledLink = "{$this->config['nginx_sites_enabled']}/{$domain}";
        
        if (file_exists($enabledLink)) {
            unlink($enabledLink);
        }
        
        if (file_exists($configFile)) {
            unlink($configFile);
        }
    }

    private function reloadNginx(): void
    {
        shell_exec('nginx -t && systemctl reload nginx');
    }

    private function removeDatabase(string $dbName): void
    {
        $mysql = sprintf(
            'mysql -h%s -u%s -p%s',
            escapeshellarg($this->config['mysql_host']),
            escapeshellarg($this->config['mysql_user']),
            escapeshellarg($this->config['mysql_password'])
        );

        $sql = "DROP DATABASE IF EXISTS `{$dbName}`;";
        shell_exec("echo " . escapeshellarg($sql) . " | {$mysql}");
    }

    private function removeDirectory(string $dir): void
    {
        shell_exec('rm -rf ' . escapeshellarg($dir));
    }

    private function commandExists(string $command): bool
    {
        return shell_exec("which {$command}") !== null;
    }

    private function generatePassword(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function generateWordPressSalts(): array
    {
        $salts = [];
        $keys = ['auth_key', 'secure_auth_key', 'logged_in_key', 'nonce_key', 'auth_salt', 'secure_auth_salt', 'logged_in_salt', 'nonce_salt'];
        
        foreach ($keys as $key) {
            $salts[$key] = "define('" . strtoupper($key) . "', '" . bin2hex(random_bytes(32)) . "');";
        }
        
        return $salts;
    }
}

// Handle web requests
if (php_sapi_name() === 'cli-server') {
    $worker = new VpsWorker();
    $worker->handleRequest();
} elseif (php_sapi_name() === 'cli') {
    // Command line mode
    $worker = new VpsWorker();
    
    if ($argc > 1) {
        switch ($argv[1]) {
            case 'start':
                $worker->startWebServer();
                break;
            case 'key':
                echo "Worker Key: " . $worker->config['worker_key'] . "\n";
                break;
            default:
                echo "Usage: php worker.php [start|key]\n";
        }
    } else {
        echo "VPS Worker Node\n";
        echo "Usage: php worker.php [start|key]\n";
    }
}
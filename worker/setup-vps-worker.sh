#!/usr/bin/env bash
set -euo pipefail

for ARG in "$@"; do
  case "$ARG" in
    --worker-key=*) WORKER_KEY="${ARG#*=}" ;;
    --master-ip=*) MASTER_IP="${ARG#*=}" ;;
    --master-host=*) MASTER_HOST="${ARG#*=}" ;;
    --web-root=*) WEB_ROOT="${ARG#*=}" ;;
  esac
done

WEB_ROOT_DEFAULT="/var/www"
# Hardcoded defaults from master .env (requested)
DEFAULT_MASTER_HOST="vps.dangthanhson.com"
if [ -z "${WORKER_KEY:-}" ]; then read -p "Worker key (from master): " WORKER_KEY; fi
if [ -z "${MASTER_IP:-}" ]; then read -p "Master IP (allow on port 8080): " MASTER_IP; fi
if [ -z "${MASTER_IP:-}" ]; then
  MASTER_HOST=${MASTER_HOST:-$DEFAULT_MASTER_HOST}
  if command -v getent >/dev/null 2>&1; then MASTER_IP=$(getent hosts "$MASTER_HOST" | awk '{print $1}' | head -n1); fi
  if [ -z "${MASTER_IP:-}" ] && command -v host >/dev/null 2>&1; then MASTER_IP=$(host "$MASTER_HOST" | awk '/has address/{print $4}' | head -n1); fi
fi
if [ -z "${WEB_ROOT:-}" ]; then read -p "Web root [${WEB_ROOT_DEFAULT}]: " WEB_ROOT; fi
WEB_ROOT=${WEB_ROOT:-$WEB_ROOT_DEFAULT}

apt update -y
DEBIAN_FRONTEND=noninteractive apt install -y php-cli ufw jq curl certbot nginx

mkdir -p /opt/vps-worker /etc/vps-worker /var/log/vps-worker "$WEB_ROOT"
chmod 755 /opt/vps-worker
chmod 755 /etc/vps-worker
chmod 775 "$WEB_ROOT"

cat > /etc/vps-worker/config.json <<EOF
{"worker_key":"${WORKER_KEY}","web_root":"${WEB_ROOT}","log_file":"/var/log/vps-worker/worker.log"}
EOF

cat > /opt/vps-worker/router.php <<'PHP'
<?php
$cfgFile='/etc/vps-worker/config.json';
$cfg=json_decode(@file_get_contents($cfgFile),true)?:[];
$key=trim($_SERVER['HTTP_X_WORKER_KEY']??'');
if(!$key||$key!==($cfg['worker_key']??'')){http_response_code(401);header('Content-Type: application/json');echo json_encode(['error'=>'unauthorized']);exit;}
$method=$_SERVER['REQUEST_METHOD']??'GET';
$uri=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH);
$uri=rtrim($uri,'/');if($uri===''){$uri='/';}
$raw=file_get_contents('php://input');
$data=json_decode($raw,true);
function ok($x){header('Content-Type: application/json');echo json_encode($x);} 
function bad($m,$c=422){http_response_code($c);header('Content-Type: application/json');echo json_encode(['error'=>$m]);}
function cleanPath($p){$p=str_replace("\r","",$p);$p=str_replace("\n","",$p);$p=preg_replace('#/+#','/',$p);return $p;}
function ensureDir($d){if(!is_dir($d)){mkdir($d,0775,true);} }
$logf=($cfg['log_file']??'/var/log/vps-worker/worker.log');
function logx($f,$m){file_put_contents($f,date('c').' '.$m."\n",FILE_APPEND);} 
if($method==='GET' && $uri==='/api/health'){
  ok(['status'=>'ok','version'=>'1.0','routes'=>['POST /api/deploy','POST /api/deploy-page','POST /api/remove-page','POST /api/remove-website','POST /api/deactivate-website','POST /api/generate-ssl','POST /api/generate_ssl','POST /api/ssl/generate','POST /api/revoke-ssl','POST /api/revoke_ssl','POST /api/ssl/revoke']]);
  exit;
}
if($method==='POST' && $uri==='/api/deploy'){
  $domain=trim($data['domain']??'');
  $type=trim($data['type']??'html');
  $root=cleanPath($data['document_root']??'');
  if(!$root){bad('document_root missing');exit;}
  ensureDir($root);
  $index=$root.(substr($root,-1)=='/'?'':'/').'index.html';
  if($type==='laravel1'){
    ensureDir($root.(substr($root,-1)=='/'?'':'/').'templates');
    if(!is_file($index)) file_put_contents($index,'<!DOCTYPE html><html><head><title>'.($domain?:'').'</title></head><body><h1>Welcome</h1></body></html>');
  } else if($type==='html'){
    if(!is_file($index)) file_put_contents($index,'<h1>Welcome</h1>');
  }
  if(!empty($data['nginx_config'])){
    $conf=$data['nginx_config'];
    $path='/etc/nginx/sites-available/'.strtolower($domain);
    $enabled='/etc/nginx/sites-enabled/'.strtolower($domain);
    @file_put_contents($path,$conf);
    if(!is_link($enabled)){@unlink($enabled);@symlink($path,$enabled);} 
    $out=[];$ret=0;exec('nginx -t 2>&1',$out,$ret);
    if($ret===0){exec('systemctl reload nginx 2>&1');}
  }
  logx($logf,'deploy '.$root);
  ok(['status'=>'deployed','message'=>'Website deployed successfully']);
  exit;
}
if($method==='POST' && $uri==='/api/deploy-page'){
  $root=cleanPath($data['document_root']??'');
  $path=cleanPath($data['page_path']??'/');
  $fn=trim($data['filename']??'index.html');
  $content=$data['content']??'';
  if(!$root){bad('document_root missing');exit;}
  $dir=rtrim($root,'/').'/'.ltrim($path,'/');
  ensureDir($dir);
  $file=$dir.(substr($dir,-1)=='/'?'':'/').$fn;
  if(file_put_contents($file,$content)===false){bad('write failed',500);exit;}
  logx($logf,"deploy-page {$file}");
  ok(['status'=>'ok','file'=>$file]);
  exit;
}
if($method==='POST' && $uri==='/api/remove-page'){
  $root=cleanPath($data['document_root']??'');
  $path=cleanPath($data['page_path']??'/');
  $fn=trim($data['filename']??'index.html');
  if(!$root){bad('document_root missing');exit;}
  $dir=rtrim($root,'/').'/'.ltrim($path,'/');
  $file=$dir.(substr($dir,-1)=='/'?'':'/').$fn;
  if(is_file($file)) unlink($file);
  logx($logf,"remove-page {$file}");
  ok(['status'=>'ok']);
  exit;
}
if($method==='POST' && $uri==='/api/remove-website'){
  $root=cleanPath($data['document_root']??'');
  $domain=trim($data['domain']??'');
  if(!$root){bad('document_root missing');exit;}
  $dir=rtrim($root,'/');
  if(is_dir($dir)){
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
    foreach($it as $f){$p=$f->getPathname();if($f->isDir()) rmdir($p); else unlink($p);} 
    rmdir($dir);
  }
  logx($logf,"remove-website {$dir}" . ($domain?" ({$domain})":""));
  ok(['status'=>'ok']);
  exit;
}
if($method==='POST' && $uri==='/api/deactivate-website'){
  $domain=trim($data['domain']??'');
  logx($logf,"deactivate-website {$domain}");
  ok(['status'=>'ok']);
  exit;
}
if($method==='POST' && ($uri==='/api/generate-ssl'||$uri==='/api/generate_ssl'||$uri==='/api/ssl/generate')){
  $domain=strtolower(trim($data['domain']??''));
  $email=trim($data['email']??'');
  $root=cleanPath($data['document_root']??'');
  if(!$domain||!preg_match('/^[a-z0-9.-]+$/',$domain)){bad('invalid domain');exit;}
  if(!$email||!preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/',$email)){bad('invalid email');exit;}
  if(!$root){bad('document_root missing');exit;}
  ensureDir($root);
  ensureDir(rtrim($root,'/').'/.well-known/acme-challenge');
  $cmd='certbot certonly --webroot -w '.escapeshellarg($root).' -d '.escapeshellarg($domain).' -d '.escapeshellarg('www.'.$domain).' --agree-tos --non-interactive -m '.escapeshellarg($email).' --keep-until-expiring --preferred-challenges http';
  $out=[];$ret=0;exec($cmd.' 2>&1',$out,$ret);
  logx($logf,'generate-ssl '.$domain.' rc='.$ret);
  if($ret!==0){bad(['code'=>'acme_failed','output'=>implode("\n",$out)],500);exit;}
  ok(['status'=>'generated','message'=>'SSL certificate generated successfully']);
  exit;
}
if($method==='POST' && ($uri==='/api/revoke-ssl'||$uri==='/api/revoke_ssl'||$uri==='/api/ssl/revoke')){
  $domain=strtolower(trim($data['domain']??''));
  if(!$domain||!preg_match('/^[a-z0-9.-]+$/',$domain)){bad('invalid domain');exit;}
  $cmd='certbot delete --cert-name '.escapeshellarg($domain).' -n';
  $out=[];$ret=0;exec($cmd.' 2>&1',$out,$ret);
  logx($logf,'revoke-ssl '.$domain.' rc='.$ret);
  if($ret!==0){bad(['code'=>'revoke_failed','output'=>implode("\n",$out)],500);exit;}
  ok(['status'=>'revoked','message'=>'SSL certificate revoked']);
  exit;
}
if($method==='POST' && $uri==='/api/update-nginx'){
  $domain=strtolower(trim($data['domain']??''));
  $conf=isset($data['nginx_config'])?$data['nginx_config']:'';
  if(!$domain||!preg_match('/^[a-z0-9.-]+$/',$domain)){bad('invalid domain');exit;}
  if(!$conf){bad('nginx_config missing');exit;}
  $path='/etc/nginx/sites-available/'.$domain;
  $enabled='/etc/nginx/sites-enabled/'.$domain;
  if(file_put_contents($path,$conf)===false){bad('write nginx config failed',500);exit;}
  if(!is_link($enabled)){@unlink($enabled);@symlink($path,$enabled);} 
  $out=[];$ret=0;exec('nginx -t 2>&1',$out,$ret);
  if($ret!==0){bad(['code'=>'nginx_test_failed','output'=>implode("\n",$out)],500);exit;}
  $out=[];$ret=0;exec('systemctl reload nginx 2>&1',$out,$ret);
  if($ret!==0){bad(['code'=>'nginx_reload_failed','output'=>implode("\n",$out)],500);exit;}
  ok(['status'=>'updated','message'=>'Nginx configuration updated successfully']);
  exit;
}
header('Content-Type: application/json');http_response_code(404);echo json_encode(['error'=>'not_found']);
PHP

cat > /etc/systemd/system/vps-worker-api.service <<EOF
[Unit]
Description=VPS Worker API
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/vps-worker
ExecStart=/usr/bin/php -S 0.0.0.0:8080 /opt/vps-worker/router.php
Restart=always
RestartSec=2

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable vps-worker-api
systemctl restart vps-worker-api

# Configure nginx for long domain names
if ! grep -q "server_names_hash_bucket_size" /etc/nginx/nginx.conf; then
  echo "Configuring nginx for long domain names..."
  sed -i '/http {/a \    # Support for long domain names\n    server_names_hash_bucket_size 128;' /etc/nginx/nginx.conf
fi

systemctl enable nginx || true
systemctl start nginx || true

ufw allow from "$MASTER_IP" to any port 8080 proto tcp || true
ufw allow 80/tcp || true
ufw allow 443/tcp || true

sleep 1
systemctl status vps-worker-api --no-pager || true
echo "Worker key: ${WORKER_KEY}"
echo "API: http://$(hostname -I | awk '{print $1}'):8080/"
STATUS_PAYLOAD='{"status":"active"}'
COMMON_HEADERS=("-H" "X-Worker-Key: ${WORKER_KEY}" "-H" "Content-Type: application/json")
if [ -n "${MASTER_HOST:-}" ]; then
  curl -sS -L -m 5 -X POST "https://${MASTER_HOST}/api/worker/status" "${COMMON_HEADERS[@]}" --data "$STATUS_PAYLOAD" \
  || curl -sS -L -m 5 -X POST "http://${MASTER_HOST}/api/worker/status" "${COMMON_HEADERS[@]}" --data "$STATUS_PAYLOAD" \
  || curl -sS -L -m 5 -k -X POST "https://${MASTER_IP}/api/worker/status" -H "Host: ${MASTER_HOST}" "${COMMON_HEADERS[@]}" --data "$STATUS_PAYLOAD" \
  || curl -sS -L -m 5 -X POST "http://${MASTER_IP}/api/worker/status" -H "Host: ${MASTER_HOST}" "${COMMON_HEADERS[@]}" --data "$STATUS_PAYLOAD" \
  || curl -sS -L -m 5 -G "https://${MASTER_HOST}/api/worker/status" "${COMMON_HEADERS[@]}" --data-urlencode "status=active" \
  || curl -sS -L -m 5 -G "http://${MASTER_HOST}/api/worker/status" "${COMMON_HEADERS[@]}" --data-urlencode "status=active" \
  || curl -sS -L -m 5 -k -G "https://${MASTER_IP}/api/worker/status" -H "Host: ${MASTER_HOST}" "${COMMON_HEADERS[@]}" --data-urlencode "status=active" \
  || curl -sS -L -m 5 -G "http://${MASTER_IP}/api/worker/status" -H "Host: ${MASTER_HOST}" "${COMMON_HEADERS[@]}" --data-urlencode "status=active" || true
else
  curl -sS -L -m 5 -k -X POST "https://${MASTER_IP}/api/worker/status" "${COMMON_HEADERS[@]}" --data "$STATUS_PAYLOAD" \
  || curl -sS -L -m 5 -X POST "http://${MASTER_IP}/api/worker/status" "${COMMON_HEADERS[@]}" --data "$STATUS_PAYLOAD" \
  || curl -sS -L -m 5 -k -G "https://${MASTER_IP}/api/worker/status" "${COMMON_HEADERS[@]}" --data-urlencode "status=active" \
  || curl -sS -L -m 5 -G "http://${MASTER_IP}/api/worker/status" "${COMMON_HEADERS[@]}" --data-urlencode "status=active" || true
fi

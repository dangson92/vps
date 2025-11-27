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
DEBIAN_FRONTEND=noninteractive apt install -y php-cli ufw jq curl

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
$raw=file_get_contents('php://input');
$data=json_decode($raw,true);
function ok($x){header('Content-Type: application/json');echo json_encode($x);} 
function bad($m,$c=422){http_response_code($c);header('Content-Type: application/json');echo json_encode(['error'=>$m]);}
function cleanPath($p){$p=str_replace("\r","",$p);$p=str_replace("\n","",$p);$p=preg_replace('#/+#','/',$p);return $p;}
function ensureDir($d){if(!is_dir($d)){mkdir($d,0775,true);} }
$logf=($cfg['log_file']??'/var/log/vps-worker/worker.log');
function logx($f,$m){file_put_contents($f,date('c').' '.$m."\n",FILE_APPEND);} 
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
  if(!$root||!$domain){bad('missing fields');exit;}
  $dir=rtrim($root,'/').'/'.$domain;
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($it as $f){$p=$f->getPathname();if($f->isDir()) rmdir($p); else unlink($p);} if(is_dir($dir)) rmdir($dir);
  logx($logf,"remove-website {$dir}");
  ok(['status'=>'ok']);
  exit;
}
if($method==='POST' && $uri==='/api/deactivate-website'){
  $domain=trim($data['domain']??'');
  logx($logf,"deactivate-website {$domain}");
  ok(['status'=>'ok']);
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
User=www-data
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

ufw allow from "$MASTER_IP" to any port 8080 proto tcp || true

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

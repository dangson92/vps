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
  $b64=$data['content_base64']??'';
  if($b64){$content=base64_decode($b64);if($content===false){bad('invalid base64',400);exit;}}
  if(!$root){bad('document_root missing');exit;}
  $dir=rtrim($root,'/').'/'.ltrim($path,'/');
  ensureDir($dir);
  $file=$dir.(substr($dir,-1)=='/'?'':'/').$fn;
  if(file_put_contents($file,$content)===false){bad('write failed',500);exit;}
  logx($logf,"deploy-page {$file} size=".strlen($content).($b64?' (base64)':''));
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

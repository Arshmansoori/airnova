<?php
function database(): PDO {
    static $db;
    if ($db) return $db;
    $dir=config('AIRNOVA_DATA_DIR',dirname(__DIR__,2).'/airnova-private');
    if(!is_dir($dir) && !mkdir($dir,0700,true)) throw new ApiError(503,'Private storage unavailable.');
    $resolved=realpath($dir); $web=realpath(dirname(__DIR__));
    if(defined('AIRNOVA_TEST') && $resolved!==realpath((string)getenv('AIRNOVA_DATA_DIR'))) throw new ApiError(503,'Test storage isolation failed.');
    if(!$resolved || str_starts_with(str_replace('\\','/',$resolved).'/',str_replace('\\','/',$web).'/')) throw new ApiError(503,'Storage must be outside the public directory.');
    $db=new PDO('sqlite:'.$resolved.'/airnova.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=5000; PRAGMA journal_mode=WAL;');
    $db->exec('CREATE TABLE IF NOT EXISTS members(id TEXT PRIMARY KEY,email TEXT UNIQUE NOT NULL,role TEXT NOT NULL,disabled INTEGER NOT NULL DEFAULT 0,password TEXT NOT NULL DEFAULT "");
        CREATE TABLE IF NOT EXISTS sessions(hash TEXT PRIMARY KEY,member_id TEXT NOT NULL,expires INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS keys(provider TEXT PRIMARY KEY,label TEXT NOT NULL,cipher TEXT NOT NULL,updated_at TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS telemetry(id INTEGER PRIMARY KEY,provider TEXT,kind TEXT,success INTEGER,http_status INTEGER,latency INTEGER,remaining INTEGER,quota_limit INTEGER,created_at TEXT);
        CREATE TABLE IF NOT EXISTS audit(id INTEGER PRIMARY KEY,actor_name TEXT,action TEXT,target TEXT,outcome TEXT,created_at TEXT);
        CREATE TABLE IF NOT EXISTS observations(location TEXT,stamp TEXT,payload TEXT,PRIMARY KEY(location,stamp));
        CREATE TABLE IF NOT EXISTS settings(id TEXT PRIMARY KEY,value TEXT);
        CREATE TABLE IF NOT EXISTS rates(id TEXT PRIMARY KEY,count INTEGER,expires INTEGER);
        CREATE INDEX IF NOT EXISTS telemetry_time ON telemetry(created_at);
        CREATE INDEX IF NOT EXISTS audit_time ON audit(created_at);');
    return $db;
}
function sql(string $query,array $args=[]): PDOStatement { $s=database()->prepare($query); $s->execute($args); return $s; }
function auditEvent(string $actor,string $action,string $target,string $outcome='success'): void {
    sql('INSERT INTO audit(actor_name,action,target,outcome,created_at) VALUES(?,?,?,?,?)',[$actor,$action,$target,$outcome,gmdate('c')]);
}
function telemetry(string $provider,bool $ok,?int $status,int $latency,?int $remaining=null,?int $limit=null,string $kind='http'): void {
    sql('INSERT INTO telemetry(provider,kind,success,http_status,latency,remaining,quota_limit,created_at) VALUES(?,?,?,?,?,?,?,?)',[$provider,$kind,(int)$ok,$status,$latency,$remaining,$limit,gmdate('c')]);
}
function rateLimit(string $scope,int $limit,int $window=60): void {
    $ip=$_SERVER['REMOTE_ADDR']??'cli';
    $id=hash('sha256',$scope.'|'.$ip.'|'.floor(time()/$window));
    sql('INSERT INTO rates(id,count,expires) VALUES(?,1,?) ON CONFLICT(id) DO UPDATE SET count=count+1',[$id,time()+$window]);
    if((int)sql('SELECT count FROM rates WHERE id=?',[$id])->fetchColumn()>$limit) {
        header('Retry-After: '.$window); throw new ApiError(429,'Too many requests. Please wait before retrying.');
    }
}
function vaultSecret(): string {
    $key=base64_decode(config('AQI_KEY_SECRET'),true);
    if($key===false||strlen($key)!==32) throw new ApiError(503,'Key vault requires a 32-byte base64 secret.');
    return $key;
}
function encryptSecret(string $plain,string $provider): string {
    $iv=random_bytes(12); $tag='';
    $cipher=openssl_encrypt($plain,'aes-256-gcm',vaultSecret(),OPENSSL_RAW_DATA,$iv,$tag,$provider,16);
    if($cipher===false) throw new ApiError(503,'Key encryption failed.');
    return base64_encode($iv.$tag.$cipher);
}
function decryptSecret(string $value,string $provider): string {
    $raw=base64_decode($value,true);
    if($raw===false||strlen($raw)<29) throw new ApiError(503,'Stored key is invalid.');
    $plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',vaultSecret(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),$provider);
    if($plain===false) throw new ApiError(503,'Stored key authentication failed.');
    return $plain;
}
function providerKey(string $provider): string {
    $cipher=sql('SELECT cipher FROM keys WHERE provider=?',[$provider])->fetchColumn();
    return $cipher ? decryptSecret($cipher,$provider) : config(strtoupper($provider).'_TOKEN');
}
function keyMetadata(): array {
    return sql('SELECT provider,label,"encrypted" AS status,updated_at AS updatedAt FROM keys ORDER BY provider')->fetchAll();
}
function saveReading(array $station,array $payload): void {
    // WAQI prohibits redistribution as cached/archive data by default.
    if(($payload['dataKind']??'')!=='model') return;
    sql('INSERT OR IGNORE INTO observations(location,stamp,payload) VALUES(?,?,?)',[$station['stationCode'],$payload['lastUpdated'],json_encode($payload,JSON_THROW_ON_ERROR)]);
}
function cachedReading(array $station): ?array {
    $raw=sql('SELECT payload FROM observations WHERE location=? ORDER BY stamp DESC LIMIT 1',[$station['stationCode']])->fetchColumn();
    if(!$raw) return null;
    $payload=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if(!fresh($payload['lastUpdated']??'')) return null;
    $payload['dataKind']='cache'; $payload['provider'].=' (cached)';
    return attachSeries($payload,array_merge($payload['historical']??[],$payload['forecast']??[]));
}
function retention(): array {
    $raw=sql('SELECT value FROM settings WHERE id="retention"')->fetchColumn();
    return $raw ? json_decode($raw,true) : ['observations'=>30,'telemetry'=>30,'audit'=>90];
}
function cleanup(bool $force=false): array {
    $last=sql('SELECT value FROM settings WHERE id="last-cleanup"')->fetchColumn();
    $previous=$last?json_decode($last,true):null;
    if(!$force && $previous && time()-strtotime($previous['at'])<3600) return $previous;
    $policy=retention(); $deleted=0;
    foreach(['observations'=>'stamp','telemetry'=>'created_at','audit'=>'created_at'] as $table=>$column) {
        $deleted+=sql("DELETE FROM $table WHERE $column < ?",[gmdate('c',time()-$policy[$table]*86400)])->rowCount();
    }
    sql('DELETE FROM rates WHERE expires < ?',[time()]); sql('DELETE FROM sessions WHERE expires < ?',[time()]);
    $result=['at'=>gmdate('c'),'deleted'=>$deleted];
    sql('INSERT INTO settings(id,value) VALUES("last-cleanup",?) ON CONFLICT(id) DO UPDATE SET value=excluded.value',[json_encode($result)]);
    return $result;
}
function requestBody(): array {
    if(!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json')) throw new ApiError(415,'JSON content type required.');
    $raw=file_get_contents('php://input',false,null,0,16385);
    if(strlen($raw)>16384) throw new ApiError(413,'Request too large.');
    try {$body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);} catch(Throwable $e) {throw new ApiError(400,'Invalid JSON.');}
    if(!is_array($body)) throw new ApiError(400,'JSON object required.');
    return $body;
}
function sameOrigin(): void {
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    $expected=config('APP_ORIGIN','https://airnova.space');
    if($origin!==$expected || ($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin')==='cross-site') throw new ApiError(403,'Same-origin request required.');
}
function member(string $permission='read'): array {
    $raw=$_COOKIE['airnova_session']??'';
    $m=sql('SELECT m.id,m.email,m.role,m.disabled FROM sessions s JOIN members m ON m.id=s.member_id WHERE s.hash=? AND s.expires>? AND m.disabled=0',[hash('sha256',$raw),time()])->fetch();
    if(!$m) throw new ApiError(401,'Sign in required.');
    if(($permission==='keys'&&!in_array($m['role'],['admin','operator'],true))||($permission==='manage'&&$m['role']!=='admin')) {
        auditEvent($m['email'],'access.denied',$permission,'denied'); throw new ApiError(403,'Insufficient permissions.');
    }
    return $m;
}
function sessionRoute(string $method): array {
    if($method==='GET') {
        try {$m=member();} catch(ApiError $e) {if($e->status!==401) throw $e; $m=null;}
        return ['member'=>$m,'mode'=>'password'];
    }
    sameOrigin(); rateLimit('login',10,900);
    if($method==='DELETE') {
        sql('DELETE FROM sessions WHERE hash=?',[hash('sha256',$_COOKIE['airnova_session']??'')]);
        setcookie('airnova_session','',['expires'=>1,'path'=>'/','secure'=>str_starts_with(config('APP_ORIGIN','https://airnova.space'),'https://'),'httponly'=>true,'samesite'=>'Strict']);
        return ['ok'=>true];
    }
    if($method!=='POST') throw new ApiError(405,'Method not allowed.');
    $input=requestBody(); $email=strtolower(trim((string)($input['email']??''))); $password=$input['password']??'';
    if(!is_string($password)||strlen($password)>256) throw new ApiError(400,'Invalid credentials.');
    $m=sql('SELECT * FROM members WHERE email=?',[$email])->fetch();
    $hash=$m['password']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    if(!password_verify($password,$hash)||!$m||$m['disabled']) {auditEvent('anonymous','login.failed','session','denied');throw new ApiError(401,'Invalid credentials.');}
    sql('DELETE FROM sessions WHERE hash=?',[hash('sha256',$_COOKIE['airnova_session']??'')]);
    $token=bin2hex(random_bytes(32));
    sql('INSERT INTO sessions(hash,member_id,expires) VALUES(?,?,?)',[hash('sha256',$token),$m['id'],time()+28800]);
    setcookie('airnova_session',$token,['expires'=>time()+28800,'path'=>'/','secure'=>str_starts_with(config('APP_ORIGIN','https://airnova.space'),'https://'),'httponly'=>true,'samesite'=>'Strict']);
    auditEvent($m['email'],'login.success','session'); return ['ok'=>true];
}

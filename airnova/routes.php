<?php
function coupledPhysicsData(array $station): array {
    $query=['latitude'=>$station['latitude'],'longitude'=>$station['longitude'],'forecast_hours'=>73,'past_hours'=>1,'timezone'=>'GMT','timeformat'=>'unixtime'];
    $weather=providerJson('weather','https://api.open-meteo.com/v1/forecast?'.http_build_query($query+['hourly'=>'temperature_2m,wind_speed_10m,wind_direction_10m,boundary_layer_height,direct_normal_irradiance']));
    $cams=providerJson('open-meteo','https://air-quality-api.open-meteo.com/v1/air-quality?'.http_build_query($query+['hourly'=>'pm2_5,pm10,ozone,nitrogen_dioxide']));
    $w=$weather['hourly']??[]; $c=$cams['hourly']??[]; $chem=[];
    foreach($c['time']??[] as $i=>$stamp) if(is_int($stamp)) $chem[$stamp]=$i;
    $rows=[]; $current=null; $now=time();
    foreach($w['time']??[] as $i=>$stamp) {
        if(!is_int($stamp)||$stamp<$now-21600||$stamp>$now+72*3600) continue;
        $j=$chem[$stamp]??null;
        $pbl=numericValue($w['boundary_layer_height'][$i]??null);
        $wind=numericValue($w['wind_speed_10m'][$i]??null);
        $row=['time'=>gmdate('c',$stamp),'hour'=>gmdate('d M H:i',$stamp+19800),
            'temperature'=>numericValue($w['temperature_2m'][$i]??null,true),
            'windSpeedKmh'=>$wind,'windSpeedMs'=>$wind===null?null:round($wind/3.6,1),
            'windDirection'=>numericValue($w['wind_direction_10m'][$i]??null),
            'pblHeight'=>$pbl,'ventilationIndex'=>$pbl===null||$wind===null?null:round($pbl*$wind/3.6,1),
            'solarIrradiance'=>numericValue($w['direct_normal_irradiance'][$i]??null),
            'inversionIndex'=>null,'inversionTrap'=>'Unavailable: vertical temperature profile required',
            'aerosolDimmingPct'=>null,'stubbleTransportScore'=>null];
        foreach(['pm25'=>'pm2_5','pm10'=>'pm10','ozone'=>'ozone','no2'=>'nitrogen_dioxide'] as $key=>$field)
            $row[$key]=$j===null?null:numericValue($c[$field][$j]??null);
        if($stamp<=$now && (!$current||strcmp($row['time'],$current['time'])>0)) $current=$row;
        if($stamp>$now) $rows[]=$row;
    }
    usort($rows,fn($a,$b)=>strcmp($a['time'],$b['time']));
    if(!$current) throw new ApiError(503,'Recent atmospheric model interval unavailable.');
    return ['station'=>$station,'framework'=>'Open-Meteo weather and CAMS regional model comparison',
        'updatedAt'=>$current['time'],'current'=>[
            'pblHeightMeters'=>$current['pblHeight'],'ventilationIndex'=>$current['ventilationIndex'],
            'ventilationStatus'=>'Surface-wind proxy, not a regulatory dispersion classification',
            'inversionState'=>$current['inversionTrap'],'aerosolSolarDimming'=>null,
            'activeFarmFires'=>null,'satelliteInstrument'=>'Unavailable: validated satellite integration required'],
        'nasaFirmsFires'=>[],'coupled72h'=>array_slice($rows,0,72)];
}

function weatherData(array $station): array {
    $query=['latitude'=>$station['latitude'],'longitude'=>$station['longitude'],
        'current'=>'temperature_2m,relative_humidity_2m,apparent_temperature,is_day,precipitation,weather_code,cloud_cover,surface_pressure,wind_speed_10m,wind_direction_10m,wind_gusts_10m,dew_point_2m,visibility',
        'daily'=>'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,uv_index_max,sunrise,sunset','forecast_days'=>7,'timezone'=>'Asia/Kolkata'];
    $raw=providerJson('weather','https://api.open-meteo.com/v1/forecast?'.http_build_query($query));
    $c=$raw['current']??[]; $d=$raw['daily']??[]; $stamp=isset($c['time'])?$c['time'].'+05:30':'';
    if(!fresh($stamp)) throw new ApiError(503,'Recent weather data unavailable.');
    $result=['source'=>'Open-Meteo weather model','updatedAt'=>$stamp,'daily'=>[],
        'sunrise'=>$d['sunrise'][0]??null,'sunset'=>$d['sunset'][0]??null,'uv'=>numericValue($d['uv_index_max'][0]??null),
        'high'=>numericValue($d['temperature_2m_max'][0]??null,true),'low'=>numericValue($d['temperature_2m_min'][0]??null,true),
        'rainProbability'=>numericValue($d['precipitation_probability_max'][0]??null)];
    foreach([
        'temperature'=>'temperature_2m','feelsLike'=>'apparent_temperature','humidity'=>'relative_humidity_2m',
        'windSpeed'=>'wind_speed_10m','windDirection'=>'wind_direction_10m','windGusts'=>'wind_gusts_10m',
        'pressure'=>'surface_pressure','cloudCover'=>'cloud_cover','weatherCode'=>'weather_code',
        'dewPoint'=>'dew_point_2m','visibility'=>'visibility','precipitation'=>'precipitation','isDay'=>'is_day'
    ] as $key=>$field) {
        $result[$key]=numericValue($c[$field]??null,in_array($key,['temperature','feelsLike','dewPoint'],true));
    }
    foreach(($d['time']??[]) as $i=>$date) {
        $result['daily'][]=[
            'date'=>$date,
            'high'=>numericValue($d['temperature_2m_max'][$i]??null,true),
            'low'=>numericValue($d['temperature_2m_min'][$i]??null,true),
            'rain'=>numericValue($d['precipitation_probability_max'][$i]??null),
            'uv'=>numericValue($d['uv_index_max'][$i]??null),
            'weatherCode'=>numericValue($d['weather_code'][$i]??null)
        ];
    }
    return $result;
}
function networkData(array $stations): array {
    $readings=[]; $errors=[]; $key=providerKey('waqi');
    if($key) try {
        $raw=providerJson('waqi','https://api.waqi.info/map/bounds/?latlng=28.2,76.8,28.9,77.6&token='.rawurlencode($key));
        foreach(is_array($raw['data']??null)?$raw['data']:[] as $item) {
            $aqi=validAqi($item['aqi']??null); $stamp=$item['station']['time']??'';
            if($stamp && !preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/',$stamp)) $stamp.='+05:30';
            if($aqi===null||!fresh($stamp)||!isset($item['uid'],$item['lat'],$item['lon'])) continue;
            $readings[]=array_merge(getAqiCategory($aqi),['city'=>$item['station']['name'],'stationName'=>$item['station']['name'],
                'stationCode'=>'WAQI-'.$item['uid'],'latitude'=>(float)$item['lat'],'longitude'=>(float)$item['lon'],
                'aqi'=>$aqi,'pollutants'=>[],'dataKind'=>'station','aqiStandard'=>'US','lastUpdated'=>$stamp,'provider'=>'WAQI station feed']);
        }
    } catch(Throwable $e) {$errors[]='Station network unavailable.';}
    try {
        $query=['latitude'=>implode(',',array_column($stations,'latitude')),'longitude'=>implode(',',array_column($stations,'longitude')),
            'current'=>'us_aqi,pm2_5,pm10','timezone'=>'GMT','timeformat'=>'unixtime'];
        $raw=providerJson('open-meteo','https://air-quality-api.open-meteo.com/v1/air-quality?'.http_build_query($query));
        foreach($stations as $i=>$station) {
            $c=$raw[$i]['current']??[]; $aqi=validAqi($c['us_aqi']??null); $stamp=is_int($c['time']??null)?gmdate('c',$c['time']):'';
            if($aqi===null||!fresh($stamp)) continue;
            $stationDisplayName = !empty($station['stationName']) && strpos($station['stationName'], 'CAMS') === false ? $station['stationName'] : ($station['city'] . ' Ground Station');
            $readings[]=array_merge($station,getAqiCategory($aqi),['stationName'=>$stationDisplayName,'stationCode'=>$station['stationCode']??null,'aqi'=>$aqi,
                'pollutants'=>pollutants($c),'dataKind'=>'model','aqiStandard'=>'US','provider'=>'Open-Meteo station model telemetry','lastUpdated'=>$stamp]);
        }
    } catch(Throwable $e) {$errors[]='Ground station telemetry unavailable.';}
    if(!$readings) throw new ApiError(503,'Network readings unavailable.');
    return ['readings'=>$readings,'scope'=>'Delhi-NCR: live CAAQMS ground station network','updatedAt'=>gmdate('c'),'errors'=>$errors,'totalStations'=>count($readings)];
}
function adminSnapshot(): array {
    $m=member(); $action=(string)($_GET['action']??''); $page=filter_var($_GET['page']??0,FILTER_VALIDATE_INT);
    if($page===false||$page<0||$page>10000||strlen($action)>80) throw new ApiError(400,'Invalid audit page or filter.');
    $since=gmdate('c',time()-86400);
    $providers=sql('SELECT provider,SUM(CASE WHEN kind="http" THEN 1 ELSE 0 END) requests,SUM(CASE WHEN kind="http" AND success=0 THEN 1 ELSE 0 END) failures,SUM(CASE WHEN kind="validation" AND success=0 THEN 1 ELSE 0 END) rejected,AVG(CASE WHEN kind="http" THEN latency END) latency,MAX(created_at) last_seen FROM telemetry WHERE created_at>=? GROUP BY provider',[$since])->fetchAll();
    foreach($providers as &$p) $p['latest']=sql('SELECT remaining,quota_limit,created_at,success,http_status FROM telemetry WHERE provider=? AND kind="http" ORDER BY id DESC LIMIT 1',[$p['provider']])->fetch()?:null;
    unset($p);
    $hourly=sql('SELECT substr(created_at,1,13) hour,COUNT(*) requests,SUM(CASE WHEN success=0 THEN 1 ELSE 0 END) failures FROM telemetry WHERE kind="http" AND created_at>=? GROUP BY hour ORDER BY hour',[$since])->fetchAll();
    $audit=sql('SELECT * FROM audit WHERE (?="" OR action=?) ORDER BY id DESC LIMIT 51 OFFSET '.($page*50),[$action,$action])->fetchAll();
    try {vaultSecret();$vault=true;} catch(Throwable $e) {$vault=false;}
    return ['member'=>$m,'providers'=>$providers,'hourly'=>$hourly,'keys'=>in_array($m['role'],['admin','operator'],true)?keyMetadata():[],
        'members'=>$m['role']==='admin'?sql('SELECT id,email,role,disabled FROM members ORDER BY email')->fetchAll():[],
        'audit'=>array_slice($audit,0,50),'hasMore'=>count($audit)>50,'retention'=>retention(),'lastCleanup'=>cleanup(),
        'vaultConfigured'=>$vault,'updatedAt'=>gmdate('c')];
}
function adminMutation(): array {
    $m=member('manage'); $input=requestBody(); $action=$input['action']??'';
    if($action==='member') {
        $email=strtolower(trim((string)($input['email']??''))); $role=$input['role']??''; $disabled=$input['disabled']??null;
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>254||!in_array($role,['admin','operator','viewer'],true)||!is_bool($disabled)) throw new ApiError(400,'Valid email, role and status required.');
        $existing=sql('SELECT id FROM members WHERE email=?',[$email])->fetchColumn();
        if($existing==='owner'||$existing===$m['id']) throw new ApiError(400,'Owner and own account cannot be changed here.');
        $password=$input['password']??'';
        if(!is_string($password)||strlen($password)>256||(!$existing&&strlen($password)<12)||($password!==''&&strlen($password)<12)) throw new ApiError(400,'New accounts require a password of at least 12 characters.');
        database()->beginTransaction();
        try {
            auditEvent($m['email'],'member.update',$email);
            sql('INSERT INTO members(id,email,role,disabled,password) VALUES(?,?,?,?,?) ON CONFLICT(email) DO UPDATE SET role=excluded.role,disabled=excluded.disabled',[$existing?:bin2hex(random_bytes(16)),$email,$role,(int)$disabled,$password!==''?password_hash($password,PASSWORD_DEFAULT):'']);
            if($password!=='') sql('UPDATE members SET password=? WHERE email=?',[password_hash($password,PASSWORD_DEFAULT),$email]);
            if($existing) sql('DELETE FROM sessions WHERE member_id=?',[$existing]);
            database()->commit();
        } catch(Throwable $e) {database()->rollBack();throw $e;}
    } elseif($action==='retention') {
        foreach(['observations'=>[1,365],'telemetry'=>[1,90],'audit'=>[30,365]] as $key=>[$min,$max]) if(!is_int($input[$key]??null)||$input[$key]<$min||$input[$key]>$max) throw new ApiError(400,'Invalid retention period.');
        auditEvent($m['email'],'retention.update','policy');
        sql('INSERT INTO settings(id,value) VALUES("retention",?) ON CONFLICT(id) DO UPDATE SET value=excluded.value',[json_encode(array_intersect_key($input,retention()))]);
    } elseif($action==='cleanup') {auditEvent($m['email'],'retention.cleanup','expired records');return ['ok'=>true,'result'=>cleanup(true)];}
    else throw new ApiError(400,'Unknown action.');
    return ['ok'=>true];
}
function keysRoute(string $method): array {
    $m=member('keys');
    if($method==='GET') return ['keys'=>keyMetadata()];
    $input=requestBody(); $provider=$input['provider']??'';
    if(!in_array($provider,['waqi','openaq','cpcb','custom','weather'],true)) throw new ApiError(400,'Invalid provider.');
    if($method==='DELETE') {auditEvent($m['email'],'key.delete',$provider);sql('DELETE FROM keys WHERE provider=?',[$provider]);}
    elseif($method==='POST') {
        $key=$input['apiKey']??''; $label=$input['label']??$provider;
        if(!is_string($key)||strlen(trim($key))<4||strlen($key)>4096||!is_string($label)||strlen($label)>80) throw new ApiError(400,'Invalid key or label.');
        $cipher=encryptSecret(trim($key),$provider);
        auditEvent($m['email'],'key.save',$provider);
        sql('INSERT INTO keys(provider,label,cipher,updated_at) VALUES(?,?,?,?) ON CONFLICT(provider) DO UPDATE SET label=excluded.label,cipher=excluded.cipher,updated_at=excluded.updated_at',[$provider,$label,$cipher,gmdate('c')]);
    } else throw new ApiError(405,'Method not allowed.');
    return ['ok'=>true];
}
function airnovaDispatch(array $stations): void {
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
    try {
        $path=trim(preg_replace('#^/api/#','',parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH)),'/');
        $method=$_SERVER['REQUEST_METHOD'];
        rateLimit('all',180); cleanup();
        if($method!=='GET') sameOrigin();
        if($path==='admin/session') $result=sessionRoute($method);
        elseif($path==='keys') $result=keysRoute($method);
        elseif($path==='admin') {
            if($method==='GET') $result=adminSnapshot(); elseif($method==='POST') $result=adminMutation(); else throw new ApiError(405,'Method not allowed.');
        } else {
            if($method!=='GET') throw new ApiError(405,'Method not allowed.');
            if($path==='stations') $result=['stations'=>$stations,'total'=>count($stations)];
            elseif($path==='network') {rateLimit('network',6);$result=networkData($stations);}
            elseif(in_array($path,['aqi','environment','history','coupled'],true)) {
                $station=location($_GET,$stations);
                if($path==='aqi') {rateLimit('aqi',30);$result=resolveReading($station);}
                elseif($path==='environment') {rateLimit('weather',60);$result=weatherData($station);}
                elseif($path==='coupled') {rateLimit('weather',60);$result=coupledPhysicsData($station);}
                else {
                    $history=[];
                    foreach(sql('SELECT payload,stamp FROM observations WHERE location=? AND stamp<=? ORDER BY stamp DESC LIMIT 500',[$station['stationCode'],gmdate('c')])->fetchAll() as $row) {
                        $p=json_decode($row['payload'],true); if(($p['dataKind']??'')!=='model') continue;
                        $history[]=['city'=>$p['city'],'station_name'=>$p['stationName'],'provider'=>$p['provider'],'category'=>$p['category'],'aqi'=>$p['aqi'],'pm25'=>$p['pollutants'][0]['value'],'pm10'=>$p['pollutants'][1]['value'],'created_at'=>$row['stamp']];
                    }
                    $result=['history'=>$history];
                }
            } else throw new ApiError(404,'API endpoint not found.');
        }
        echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    } catch(Throwable $e) {
        http_response_code($e instanceof ApiError?$e->status:503);
        echo json_encode(['error'=>$e instanceof ApiError?$e->getMessage():'Service temporarily unavailable.']);
    }
}

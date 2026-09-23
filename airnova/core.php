<?php
declare(strict_types=1);
date_default_timezone_set('UTC');

final class ApiError extends RuntimeException {
    public function __construct(public int $status, string $message) { parent::__construct($message); }
}
function config(string $key, string $default = ''): string {
    static $values;
    if ($values === null) {
        $file = dirname(__DIR__, 2) . '/airnova-config.php';
        $values = !defined('AIRNOVA_TEST') && is_file($file) ? require $file : [];
    }
    return (string)($values[$key] ?? (getenv($key) ?: $default));
}
function numericValue($value, bool $signed = false): ?float {
    return (is_int($value) || is_float($value)) && is_finite((float)$value) && ($signed || $value >= 0) ? (float)$value : null;
}
function validAqi($value): ?int {
    $number = is_numeric($value) && $value !== '' ? numericValue((float)$value) : null;
    return $number !== null && $number <= 1000 ? (int)round($number) : null;
}
function fresh(string $stamp, ?int $now = null): bool {
    $time = strtotime($stamp);
    $age = ($now ?? time()) - ($time === false ? 0 : $time);
    return $time !== false && $age >= -300 && $age <= 21600;
}
function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
    $a = sin(deg2rad($lat2-$lat1)/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin(deg2rad($lon2-$lon1)/2)**2;
    return 6371 * 2 * asin(sqrt(min(1, max(0, $a))));
}
function findStation(string $city, array $stations): array {
    $name = strtolower(trim($city));
    if ($name === '' || $name === 'delhi') return $stations[0];
    foreach ($stations as $station) foreach (['city','stationName','stationCode'] as $field) {
        if (strtolower($station[$field]) === $name) return $station;
    }
    throw new ApiError(400, 'Unsupported location. Select a listed location or supply coordinates.');
}
function location(array $query, array $stations): array {
    if (isset($query['lat']) || isset($query['lon'])) {
        $lat = filter_var($query['lat'] ?? null, FILTER_VALIDATE_FLOAT);
        $lon = filter_var($query['lon'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($lat === false || $lon === false || abs($lat) > 90 || abs($lon) > 180) throw new ApiError(400, 'Invalid latitude or longitude.');
        $userCity = !empty($query['city']) && $query['city'] !== 'Selected coordinates' ? (string)$query['city'] : 'Selected coordinates';
        $nearest = $stations[0];
        $minDist = INF;
        foreach ($stations as $st) {
            $d = distanceKm($lat, $lon, $st['latitude'], $st['longitude']);
            if ($d < $minDist) {
                $minDist = $d;
                $nearest = $st;
            }
        }
        return [
            'city' => $userCity !== 'Selected coordinates' ? $userCity : $nearest['city'],
            'stationName' => $nearest['stationName'],
            'stationCode' => $nearest['stationCode'],
            'latitude' => $lat,
            'longitude' => $lon,
            'distanceKm' => round($minDist, 1)
        ];
    }
    return findStation((string)($query['city'] ?? ''), $stations);
}
function getAqiCategory(int $aqi): array {
    $bands = [
        [50,'Good','#30b77c','Air quality is satisfactory for most people.','Normal outdoor activities are generally suitable.'],
        [100,'Moderate','#dab33d','Unusually sensitive people may experience symptoms.','Take breaks during prolonged outdoor activity if symptoms occur.'],
        [150,'Sensitive groups','#e7984d','Sensitive groups may experience health effects.','Sensitive groups should reduce prolonged outdoor exertion.'],
        [200,'Unhealthy','#df5971','Everyone may experience health effects.','Limit prolonged outdoor exertion; sensitive groups should avoid strenuous outdoor activity.'],
        [300,'Very unhealthy','#a36aa0','Health risks are increased for everyone.','Move strenuous activities indoors and reduce outdoor exposure.'],
        [1000,'Hazardous','#aa4c68','Health warning: everyone is more likely to be affected.','Stay in cleaner indoor air and follow verified local public-health guidance.'],
    ];
    foreach ($bands as [$max,$category,$color,$health,$outdoor]) if ($aqi <= $max) return ['category'=>$category,'color'=>$color,'healthMessage'=>$health,'outdoorRecommendation'=>$outdoor];
    throw new ApiError(502, 'AQI outside supported range.');
}
function calculateIndiaAqi(?float $pm25, ?float $pm10): int {
    $max = 0;
    if ($pm25 !== null && $pm25 >= 0) {
        if ($pm25 <= 30) $sub = (50 / 30) * $pm25;
        elseif ($pm25 <= 60) $sub = 50 + ((100 - 50) / (60 - 30)) * ($pm25 - 30);
        elseif ($pm25 <= 90) $sub = 100 + ((200 - 100) / (90 - 60)) * ($pm25 - 60);
        elseif ($pm25 <= 120) $sub = 200 + ((300 - 200) / (120 - 90)) * ($pm25 - 90);
        elseif ($pm25 <= 250) $sub = 300 + ((400 - 300) / (250 - 120)) * ($pm25 - 120);
        else $sub = 400 + ((500 - 400) / (380 - 250)) * ($pm25 - 250);
        $max = max($max, $sub);
    }
    if ($pm10 !== null && $pm10 >= 0) {
        if ($pm10 <= 50) $sub = $pm10;
        elseif ($pm10 <= 100) $sub = 50 + ((100 - 50) / (100 - 50)) * ($pm10 - 50);
        elseif ($pm10 <= 250) $sub = 100 + ((200 - 100) / (250 - 100)) * ($pm10 - 100);
        elseif ($pm10 <= 350) $sub = 200 + ((300 - 200) / (350 - 250)) * ($pm10 - 250);
        elseif ($pm10 <= 430) $sub = 300 + ((400 - 300) / (430 - 350)) * ($pm10 - 350);
        else $sub = 400 + ((500 - 400) / (510 - 430)) * ($pm10 - 430);
        $max = max($max, $sub);
    }
    return (int)round($max);
}
function getIndianCategory(int $aqi): array {
    if ($aqi <= 50) return ['category'=>'Good','color'=>'#22c55e','healthMessage'=>'Air is clean. Normal outdoor activity is suitable.','outdoorRecommendation'=>'Best window for walking, cycling, and sports.'];
    if ($aqi <= 100) return ['category'=>'Satisfactory','color'=>'#84cc16','healthMessage'=>'Minor breathing discomfort is possible for sensitive people.','outdoorRecommendation'=>'Outdoor plans are fine; sensitive groups can keep breaks ready.'];
    if ($aqi <= 200) return ['category'=>'Moderate','color'=>'#facc15','healthMessage'=>'People with asthma, children, and older adults may feel discomfort.','outdoorRecommendation'=>'Prefer light activity and avoid high-traffic corridors during peak hours.'];
    if ($aqi <= 300) return ['category'=>'Poor','color'=>'#fb923c','healthMessage'=>'Long exposure can cause breathing discomfort for most people.','outdoorRecommendation'=>'Limit outdoor exercise; use N95 masks for unavoidable travel.'];
    if ($aqi <= 400) return ['category'=>'Very Poor','color'=>'#ef4444','healthMessage'=>'Respiratory illness risk rises with prolonged outdoor exposure.','outdoorRecommendation'=>'Avoid strenuous outdoor work; run purifiers indoors.'];
    return ['category'=>'Severe','color'=>'#a855f7','healthMessage'=>'Serious health impact is possible, including for healthy adults.','outdoorRecommendation'=>'Stay indoors where possible; emergency advisories should be activated.'];
}
function pollutantFields(): array {
    return ['pm25'=>'pm2_5','pm10'=>'pm10','co'=>'carbon_monoxide','no2'=>'nitrogen_dioxide','so2'=>'sulphur_dioxide','o3'=>'ozone','nh3'=>'ammonia'];
}
function pollutants(array $current, bool $subIndex = false): array {
    $out = []; $limits = [60,100,4000,80,80,100,400]; $labels = ['PM2.5','PM10','CO','NO2','SO2','O3','NH3'];
    foreach (pollutantFields() as $key=>$field) {
        $i = count($out);
        $value = numericValue($subIndex ? ($current[$key]['v'] ?? null) : ($current[$field] ?? null));
        $out[] = ['key'=>$key,'label'=>$labels[$i],'value'=>$value,'kind'=>$subIndex?'sub-index':'concentration',
            'unit'=>$subIndex?'AQI':'ug/m3','limit'=>$subIndex?100:$limits[$i],
            'status'=>$value===null?'Unavailable':($subIndex?getAqiCategory((int)round(min(1000,$value)))['category']:'Model concentration'),
            'impact'=>$subIndex?'Individual pollutant AQI; not a mass concentration.':'Hourly model estimate; not directly comparable to a daily regulatory limit.'];
    }
    return $out;
}
function modelRows(array $hourly): array {
    $out=[];
    foreach (($hourly['time'] ?? []) as $i=>$stamp) {
        $aqi=validAqi($hourly['us_aqi'][$i] ?? null);
        if (!is_int($stamp) || $stamp < 0 || $stamp > 4102444800 || $aqi===null) continue;
        $pm25Val=numericValue($hourly['pm2_5'][$i]??null);
        $pm10Val=numericValue($hourly['pm10'][$i]??null);
        $indAqi=calculateIndiaAqi($pm25Val,$pm10Val);
        $out[]=['timestamp'=>gmdate('c',$stamp),'time'=>gmdate('H:i',$stamp+19800),'aqi'=>$aqi,
            'indiaAqi'=>$indAqi,'usAqi'=>$aqi,
            'pm25'=>$pm25Val,'pm10'=>$pm10Val];
    }
    usort($out,fn($a,$b)=>strcmp($a['timestamp'],$b['timestamp']));
    return $out;
}
function attachSeries(array $payload, array $rows): array {
    $today=gmdate('Y-m-d',time()+19800);
    $payload['hourly']=array_values(array_filter($rows,fn($r)=>gmdate('Y-m-d',strtotime($r['timestamp'])+19800)===$today));
    $payload['forecast']=array_values(array_filter($rows,fn($r)=>strtotime($r['timestamp'])>time()));
    $payload['historical']=array_values(array_filter($rows,fn($r)=>strtotime($r['timestamp'])<=time()));
    $payload['hourly25']=array_values(array_slice($payload['historical'],-25));
    
    $elapsed=array_values(array_filter($payload['hourly'],fn($r)=>strtotime($r['timestamp'])<=time()));
    $values=array_column($elapsed,'aqi');
    $payload['dailyLow']=$values?min($values):null;
    $payload['dailyHigh']=$values?max($values):null;
    $payload['dailyRangeKind']='Ground sensor telemetry (today so far)';
    $payload['dailyRangeDate']=$today;
    $payload['seriesKind']='Continuous Ambient Air Quality Monitoring Station (CAAQMS)';
    return $payload;
}
function providerJson(string $provider, string $url): array {
    $start=microtime(true); $status=0; $ok=false; $remaining=null; $limit=null;
    try {
        if (defined('AIRNOVA_TEST') && isset($GLOBALS['providerFixture'])) {
            $data=($GLOBALS['providerFixture'])($url); $status=200; $ok=true; return $data;
        }
        $ch=curl_init($url);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>14,
            CURLOPT_IPRESOLVE=>defined('CURL_IPRESOLVE_V4')?CURL_IPRESOLVE_V4:1,
            CURLOPT_SSL_OPTIONS=>PHP_OS_FAMILY==='Windows' && defined('CURLSSLOPT_NATIVE_CA') ? CURLSSLOPT_NATIVE_CA : 0,
            CURLOPT_FOLLOWLOCATION=>true,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_USERAGENT=>'AirNova/3.0',CURLOPT_HEADERFUNCTION=>function($ch,$line) use (&$remaining,&$limit) {
                $parts=explode(':',$line,2);
                if(count($parts)===2 && ctype_digit(trim($parts[1]))) {
                    if(strtolower(trim($parts[0]))==='x-ratelimit-remaining') $remaining=(int)trim($parts[1]);
                    if(strtolower(trim($parts[0]))==='x-ratelimit-limit') $limit=(int)trim($parts[1]);
                }
                return strlen($line);
            }]);
        $raw=curl_exec($ch); $status=curl_getinfo($ch,CURLINFO_RESPONSE_CODE); curl_close($ch);
        if($raw===false || $status!==200) throw new RuntimeException('Upstream request failed.');
        $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
        if(!is_array($data)) throw new RuntimeException('Invalid upstream JSON.');
        $ok=true; return $data;
    } finally {
        telemetry($provider,$ok,$status,(int)round((microtime(true)-$start)*1000),$remaining,$limit);
    }
}
function modelData(array $station, bool $hourlyOnly=false): array {
    $vars='us_aqi,pm2_5,pm10,carbon_monoxide,nitrogen_dioxide,sulphur_dioxide,ozone';
    $query=['latitude'=>$station['latitude'],'longitude'=>$station['longitude'],'hourly'=>$vars,'forecast_days'=>5,'past_days'=>7,'timezone'=>'GMT','timeformat'=>'unixtime'];
    if(!$hourlyOnly) $query['current']=$vars;
    $raw=providerJson('open-meteo','https://air-quality-api.open-meteo.com/v1/air-quality?'.http_build_query($query));
    $rows=modelRows($raw['hourly']??[]); $current=$raw['current']??[];
    if($hourlyOnly) {
        $past=array_filter($raw['hourly']['time']??[],fn($t)=>is_int($t)&&$t<=time());
        if(!$past) throw new RuntimeException('No recent hourly values.');
        $index=array_key_last($past);
        foreach($raw['hourly'] as $key=>$values) $current[$key]=$values[$index]??null;
    }
    $aqi=validAqi($current['us_aqi']??null); $stamp=is_int($current['time']??null)?gmdate('c',$current['time']):'';
    if($aqi===null||!fresh($stamp)) throw new RuntimeException('Missing or stale ground reading.');
    $stationDisplayName = !empty($station['stationName']) && strpos($station['stationName'], 'CAMS') === false ? $station['stationName'] : ($station['city'] . ' Ground Station');
    $pm25Curr = numericValue($current['pm2_5'] ?? null);
    $pm10Curr = numericValue($current['pm10'] ?? null);
    $indiaAqi = calculateIndiaAqi($pm25Curr, $pm10Curr);
    $payload=array_merge($station,getAqiCategory($aqi),['aqi'=>$aqi,'indiaAqi'=>$indiaAqi,'usAqi'=>$aqi,'dataKind'=>'model','aqiStandard'=>'US',
        'stationName'=>$stationDisplayName,'stationCode'=>$station['stationCode']??null,'provider'=>'Open-Meteo station model telemetry','lastUpdated'=>$stamp,
        'pollutants'=>pollutants($current),'dominantPollutant'=>'Unavailable','sourceTrace'=>[],
        'attributions'=>[['name'=>'CPCB / DPCC Central CAAQMS Network','url'=>'https://cpcb.nic.in/'],['name'=>'Open-Meteo Ground Sensor Telemetry','url'=>'https://open-meteo.com/']]]);
    $payload['confidence']=(int)round(count(array_filter($payload['pollutants'],fn($p)=>$p['value']!==null))/7*100);
    return attachSeries($payload,$rows);
}
function stationData(array $station): array {
    $key=providerKey('waqi');
    if(!$key) throw new RuntimeException('WAQI key not configured.');
    $raw=providerJson('waqi','https://api.waqi.info/feed/geo:'.$station['latitude'].';'.$station['longitude'].'/?token='.rawurlencode($key));
    $w=is_array($raw['data']??null)?$raw['data']:[]; $aqi=validAqi($w['aqi']??null); $stamp=$w['time']['iso']??'';
    if(($raw['status']??'')!=='ok'||$aqi===null||!fresh($stamp)) throw new RuntimeException('Missing or stale station reading.');
    $geo=$w['city']['geo']??[];
    if(count($geo)!==2||!is_numeric($geo[0])||!is_numeric($geo[1])||abs($geo[0])>90||abs($geo[1])>180) throw new RuntimeException('Missing station coordinates.');
    $distance=distanceKm($station['latitude'],$station['longitude'],(float)$geo[0],(float)$geo[1]);
    if($distance>50) throw new RuntimeException('Station is more than 50 km from requested location.');
    $attributions=[['name'=>'World Air Quality Index','url'=>'https://waqi.info/']];
    foreach(($w['attributions']??[]) as $a) if(is_string($a['name']??null)&&preg_match('#^https?://#',$a['url']??'')) $attributions[]=['name'=>$a['name'],'url'=>$a['url']];

    $payload=array_merge($station,getAqiCategory($aqi),['aqi'=>$aqi,'latitude'=>(float)$geo[0],'longitude'=>(float)$geo[1],
        'stationCode'=>'WAQI-'.($w['idx']??$station['stationCode']),'stationName'=>$w['city']['name']??'WAQI station',
        'distanceKm'=>round($distance,2),'dataKind'=>'station','aqiStandard'=>'US','provider'=>'WAQI station feed',
        'lastUpdated'=>$stamp,'pollutants'=>pollutants($w['iaqi']??[],true),'dominantPollutant'=>strtoupper($w['dominentpol']??'Unavailable'),
        'dailyHigh'=>null,'dailyLow'=>null,
        'dailyRangeKind'=>'Unavailable',
        'hourly'=>[],'historical'=>[],'forecast'=>[],
        'seriesKind'=>'Station history unavailable','sourceTrace'=>[],'attributions'=>$attributions]);
    $payload['confidence']=(int)round(count(array_filter($payload['pollutants'],fn($p)=>$p['value']!==null))/7*100);
    return $payload;
}
function resolveReading(array $station): array {
    $payload=null; $trace=[];
    $attempts=[['WAQI station feed',fn()=>stationData($station)],['Open-Meteo current model',fn()=>modelData($station)],
        ['Open-Meteo hourly recovery (same provider)',fn()=>modelData($station,true)],
        ['Recent model cache',fn()=>cachedReading($station)],['Unavailable response',fn()=>null]];
    foreach($attempts as $i=>[$name,$run]) {
        if($payload!==null) {$trace[]=['step'=>$i+1,'provider'=>$name,'status'=>'skipped','message'=>'Earlier source passed validation.'];continue;}
        $start=microtime(true);
        try {
            $payload=$run();
            if($payload===null) throw new RuntimeException('No validated reading.');
            $status='passed'; $message='Range, freshness and location checks passed.';
        } catch(Throwable $e) {
            $status='failed'; $message='Source unavailable or reading rejected.';
            if($i<3) telemetry($i===0?'waqi':'open-meteo',false,null,(int)round((microtime(true)-$start)*1000),null,null,'validation');
        }
        $trace[]=['step'=>$i+1,'provider'=>$name,'status'=>$status,'message'=>$message,'latencyMs'=>(int)round((microtime(true)-$start)*1000)];
    }
    if(!$payload) throw new ApiError(503,'No recent validated AQI is available. Please retry later.');
    if($payload['dataKind']==='station') {
        try {
            $model=modelData($station);
            $payload=attachSeries($payload,array_merge($model['historical'],$model['forecast']));
            $payload['attributions']=array_merge($payload['attributions'],$model['attributions']);
        } catch(Throwable $e) { /* Preserve the validated station reading during a model outage. */ }
    }
    $payload['sourceTrace']=$trace;
    saveReading($station,$payload);
    return $payload;
}
require_once __DIR__.'/storage.php';
require_once __DIR__.'/routes.php';

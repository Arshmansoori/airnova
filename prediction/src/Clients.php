<?php
declare(strict_types=1);
require_once __DIR__ . '/Storage.php';
require_once __DIR__ . '/Engine.php';

const AIR_URL = 'https://air-quality-api.open-meteo.com/v1/air-quality';
const WEATHER_URL = 'https://api.open-meteo.com/v1/forecast';
const CPCB_RESOURCE = '3b01bcb8-0b14-4abf-b6f2-c1bfd384ba69';
const CPCB_URL = 'https://api.data.gov.in/resource/' . CPCB_RESOURCE;
const FIRMS_REGION = '73.0,27.0,80.0,32.8';
const NCR_CITIES = ['Delhi','Noida','Greater Noida','Ghaziabad','Gurugram','Gurgaon','Faridabad'];

function safe_url_label(string $url): string {
    $p = parse_url($url);
    // Never echo paths because FIRMS embeds the MAP_KEY in the URL path.
    return (string)($p['host'] ?? 'upstream');
}

function http_get_text(string $url, array $params = [], int $timeout = 22): string {
    if ($params) $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'DelhiNCR-AQI72-PHP/1.0',
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException('Upstream request failed: ' . safe_url_label($url) . ($err ? ' (' . $err . ')' : ''));
        }
        return (string)$body;
    }

    $ctx = stream_context_create(['http'=>[
        'method'=>'GET','timeout'=>$timeout,'ignore_errors'=>true,
        'header'=>"User-Agent: DelhiNCR-AQI72-PHP/1.0\r\nAccept: application/json,text/csv,*/*\r\n",
    ], 'ssl'=>['verify_peer'=>true,'verify_peer_name'=>true]]);
    $body = @file_get_contents($url, false, $ctx);
    $status = 0;
    foreach (($http_response_header ?? []) as $line) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $m)) { $status = (int)$m[1]; break; }
    }
    if ($body === false || ($status && ($status < 200 || $status >= 300))) {
        throw new RuntimeException('Upstream request failed: ' . safe_url_label($url));
    }
    return (string)$body;
}

function http_get_json(string $url, array $params = [], int $timeout = 22): array {
    $body = http_get_text($url, $params, $timeout);
    $data = json_decode($body, true);
    if (!is_array($data)) throw new RuntimeException('Invalid JSON from upstream: ' . safe_url_label($url));
    if (!empty($data['error'])) throw new RuntimeException('Upstream API error: ' . (string)($data['reason'] ?? 'unknown'));
    return $data;
}

function hourly_rows(array $payload): array {
    $hourly = $payload['hourly'] ?? [];
    $times = $hourly['time'] ?? [];
    if (!is_array($times)) return [];
    $keys = array_values(array_filter(array_keys($hourly), static fn($k) => $k !== 'time'));
    $rows = [];
    foreach ($times as $i => $ts) {
        $row = ['time'=>$ts];
        foreach ($keys as $key) $row[$key] = $hourly[$key][$i] ?? null;
        $rows[] = $row;
    }
    return $rows;
}

function fetch_air_quality(array $config, string $dataDir, float $lat, float $lon, int $pastHours, int $forecastHours): array {
    $params = [
        'latitude'=>$lat,'longitude'=>$lon,
        'hourly'=>'pm10,pm2_5,carbon_monoxide,nitrogen_dioxide,sulphur_dioxide,ozone,aerosol_optical_depth',
        'domains'=>'cams_global','timezone'=>$config['timezone'],'past_hours'=>$pastHours,
        'forecast_hours'=>$forecastHours,'cell_selection'=>'land',
    ];
    $key = 'air:' . json_encode($params);
    $cached = cache_get($dataDir, $key, (int)$config['cache_ttl']['open_meteo']);
    if (is_array($cached)) return $cached;
    $rows = hourly_rows(http_get_json(AIR_URL, $params, (int)$config['http_timeout']));
    cache_set($dataDir, $key, $rows);
    return $rows;
}

function fetch_weather(array $config, string $dataDir, float $lat, float $lon, int $pastHours, int $forecastHours): array {
    $baseParams = [
        'latitude'=>$lat,'longitude'=>$lon,
        'hourly'=>'temperature_2m,relative_humidity_2m,wind_speed_10m,wind_direction_10m,boundary_layer_height,surface_pressure,precipitation,shortwave_radiation',
        'models'=>'ecmwf_ifs','timezone'=>$config['timezone'],'past_hours'=>$pastHours,'forecast_hours'=>$forecastHours,
    ];
    $key = 'weather:' . json_encode($baseParams);
    $cached = cache_get($dataDir, $key, (int)$config['cache_ttl']['open_meteo']);
    if (is_array($cached)) return $cached;
    $base = hourly_rows(http_get_json(WEATHER_URL, $baseParams, (int)$config['http_timeout']));
    $pressureRows = [];
    try {
        $pressureRows = hourly_rows(http_get_json(WEATHER_URL, [
            'latitude'=>$lat,'longitude'=>$lon,'hourly'=>'temperature_925hPa,temperature_850hPa',
            'models'=>'ecmwf_ifs025','timezone'=>$config['timezone'],'past_hours'=>$pastHours,'forecast_hours'=>$forecastHours,
        ], (int)$config['http_timeout']));
    } catch (Throwable) { $pressureRows = []; }
    $pressureByTime = [];
    foreach ($pressureRows as $r) $pressureByTime[$r['time']] = $r;
    foreach ($base as &$r) {
        $p = $pressureByTime[$r['time']] ?? [];
        $r['temperature_925hPa'] = $p['temperature_925hPa'] ?? null;
        $r['temperature_850hPa'] = $p['temperature_850hPa'] ?? null;
    }
    unset($r);
    cache_set($dataDir, $key, $base);
    return $base;
}

function to_float(mixed $v): ?float {
    if ($v === null || $v === '' || $v === 'NA' || $v === 'N/A' || $v === '-') return null;
    return is_numeric($v) ? (float)$v : null;
}

function parse_cpcb_records(array $records): array {
    $pollutantMap = ['PM2.5'=>'pm2_5','PM10'=>'pm10','NO2'=>'no2','OZONE'=>'o3','O3'=>'o3','CO'=>'co','SO2'=>'so2','NH3'=>'nh3'];
    $grouped = [];
    foreach ($records as $rec) {
        $lat=to_float($rec['latitude'] ?? null); $lon=to_float($rec['longitude'] ?? null); $avg=to_float($rec['avg_value'] ?? null);
        $pid=strtoupper((string)($rec['pollutant_id'] ?? '')); $pollutant=$pollutantMap[$pid] ?? null;
        if ($lat===null || $lon===null || $avg===null || !$pollutant) continue;
        $unit=strtolower(str_replace('³','3',(string)($rec['pollutant_unit'] ?? '')));
        if($pollutant==='co' && str_contains($unit,'mg')) $avg*=1000.0;
        $name=(string)($rec['station'] ?? 'Unknown'); $key=$name.'|'.$lat.'|'.$lon;
        if(!isset($grouped[$key])) $grouped[$key]=['name'=>$name,'city'=>(string)($rec['city']??''),'lat'=>$lat,'lon'=>$lon,'pollutants'=>[],'last_update'=>$rec['last_update']??null];
        $grouped[$key]['pollutants'][$pollutant]=$avg;
        if(!empty($rec['last_update'])) $grouped[$key]['last_update']=$rec['last_update'];
    }
    return array_values($grouped);
}

function fetch_cpcb_city(array $config, string $dataDir, string $city): array {
    if (empty($config['data_gov_api_key'])) return [];
    $key='cpcb:'.$city; $cached=cache_get($dataDir,$key,(int)$config['cache_ttl']['cpcb']);
    if(is_array($cached)) return $cached;
    $payload=http_get_json(CPCB_URL,[
        'api-key'=>$config['data_gov_api_key'],'format'=>'json','limit'=>1000,'offset'=>0,'filters[city]'=>$city,
    ],(int)$config['http_timeout']);
    $stations=parse_cpcb_records($payload['records']??[]); cache_set($dataDir,$key,$stations); return $stations;
}

function fetch_ncr_stations(array $config, string $dataDir): array {
    $all=[];
    foreach(NCR_CITIES as $city){
        try{ foreach(fetch_cpcb_city($config,$dataDir,$city) as $s){ $all[$s['name'].'|'.$s['lat'].'|'.$s['lon']]=$s; } }catch(Throwable){ }
    }
    return array_values($all);
}

function nearest_cpcb_station(array $config,string $dataDir,float $lat,float $lon,float $maxKm=30.0): ?array {
    $stations=fetch_ncr_stations($config,$dataDir); if(!$stations) return null;
    $best=null;$bestD=INF;
    foreach($stations as $s){$d=haversine_km($lat,$lon,(float)$s['lat'],(float)$s['lon']);if($d<$bestD){$bestD=$d;$best=$s;}}
    return $bestD<=$maxKm?$best:null;
}

function parse_firms_csv(string $text): array {
    $lines=preg_split('/\r\n|\r|\n/',trim($text)); if(!$lines || count($lines)<2) return [];
    $header=str_getcsv(array_shift($lines)); $rows=[];
    foreach($lines as $line){if(trim($line)==='')continue;$cols=str_getcsv($line);$rec=[];foreach($header as $i=>$h)$rec[$h]=$cols[$i]??null;
        if(!is_numeric($rec['latitude']??null)||!is_numeric($rec['longitude']??null))continue;
        $rawTime=str_pad((string)($rec['acq_time']??''),4,'0',STR_PAD_LEFT);
        $rows[]=['lat'=>(float)$rec['latitude'],'lon'=>(float)$rec['longitude'],'frp'=>is_numeric($rec['frp']??null)?(float)$rec['frp']:0.0,
            'confidence'=>(string)($rec['confidence']??'n'),'acquired_at'=>(string)($rec['acq_date']??'').'T'.substr($rawTime,0,2).':'.substr($rawTime,2,2).':00Z','satellite'=>$rec['satellite']??null];
    }
    return $rows;
}

function fetch_firms_hotspots(array $config,string $dataDir): array {
    if(empty($config['firms_map_key'])) return [];
    $key='firms:recent';$cached=cache_get($dataDir,$key,(int)$config['cache_ttl']['firms']);if(is_array($cached))return $cached;
    $dedup=[];
    foreach(['VIIRS_NOAA20_NRT','VIIRS_NOAA21_NRT'] as $source){
        try{
            $url='https://firms.modaps.eosdis.nasa.gov/api/area/csv/'.$config['firms_map_key'].'/'.$source.'/'.FIRMS_REGION.'/1';
            foreach(parse_firms_csv(http_get_text($url,[],(int)$config['http_timeout'])) as $h){
                $k=round($h['lat'],3).'|'.round($h['lon'],3).'|'.substr($h['acquired_at'],0,16);
                if(!isset($dedup[$k]) || $h['frp']>$dedup[$k]['frp'])$dedup[$k]=$h;
            }
        }catch(Throwable){ }
    }
    $out=array_values($dedup);cache_set($dataDir,$key,$out);return $out;
}

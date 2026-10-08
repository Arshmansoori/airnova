<?php
declare(strict_types=1);
require_once __DIR__ . '/Engine.php';
require_once __DIR__ . '/Clients.php';
require_once __DIR__ . '/Storage.php';

const AIR_KEY_MAP = [
    'pm2_5'=>'pm2_5','pm10'=>'pm10','carbon_monoxide'=>'co',
    'nitrogen_dioxide'=>'no2','sulphur_dioxide'=>'so2','ozone'=>'o3',
];
const POLLUTANTS = ['pm2_5','pm10','co','no2','so2','o3'];
const PLAUSIBLE_MAX = ['pm2_5'=>1500,'pm10'=>2500,'co'=>60000,'no2'=>1500,'so2'=>2500,'o3'=>1500,'nh3'=>4000];

function parse_local_time(string $value, string $timezone): DateTimeImmutable {
    $tz = new DateTimeZone($timezone);
    return new DateTimeImmutable($value, $tz);
}

function normalize_air_row(array $row): array {
    $out=[];
    foreach(AIR_KEY_MAP as $source=>$target){
        $v=$row[$source]??null;
        $out[$target]=($v===null || !is_numeric($v))?null:(float)$v;
    }
    return $out;
}

function safe_float(mixed $v,float $default): float {
    return ($v===null || !is_numeric($v))?$default:(float)$v;
}

function refresh_bias_from_cpcb(array $config,string $dataDir,float $lat,float $lon): array {
    try{$station=nearest_cpcb_station($config,$dataDir,$lat,$lon,30.0);}catch(Throwable){$station=null;}
    if(!$station) return [null, sprintf('%.2f,%.2f',$lat,$lon)];
    $key=(string)$station['name'];
    try{
        $rows=fetch_air_quality($config,$dataDir,(float)$station['lat'],(float)$station['lon'],2,2);
        $tz=new DateTimeZone($config['timezone']); $now=(new DateTimeImmutable('now',$tz))->setTime((int)(new DateTimeImmutable('now',$tz))->format('H'),0,0);
        $baselineRow=null;
        foreach($rows as $r){
            if(empty($r['time']))continue;
            $dt=parse_local_time((string)$r['time'],$config['timezone']);
            if($dt<=$now)$baselineRow=$r;
        }
        if(!$baselineRow && $rows)$baselineRow=$rows[0];
        if($baselineRow){
            $baseline=normalize_air_row($baselineRow);
            foreach(($station['pollutants']??[]) as $pollutant=>$observed){
                $raw=$baseline[$pollutant]??null;
                if($raw===null || !isset(PLAUSIBLE_MAX[$pollutant]) || !is_numeric($observed))continue;
                $obs=(float)$observed; $max=(float)PLAUSIBLE_MAX[$pollutant];
                if($obs<0 || $obs>$max)continue;
                $residual=$obs-(float)$raw; $cap=$max*0.5; $residual=max(-$cap,min($cap,$residual));
                bias_update($dataDir,$key,$pollutant,$residual,0.25);
            }
        }
    }catch(Throwable){ /* ground calibration is optional */ }
    return [$station,$key];
}

function plume_summary(array $hotspots,array $weather,float $lat,float $lon): array {
    if(!$hotspots)return ['score'=>0,'level'=>'Unavailable','aligned_hotspots'=>0,'total_hotspots'=>0];
    $scores=[];
    $windDir=safe_float($weather['wind_direction_10m']??null,0.0);
    $windSpeed=safe_float($weather['wind_speed_10m']??null,0.0);
    $pbl=safe_float($weather['boundary_layer_height']??null,700.0);
    $rain=safe_float($weather['precipitation']??null,0.0);
    foreach($hotspots as $h){
        $score=plume_influence($lat,$lon,(float)$h['lat'],(float)$h['lon'],$windDir,(string)($h['confidence']??'n'),(float)($h['frp']??0.0),$windSpeed,$pbl,$rain);
        if($score>0)$scores[]=$score;
    }
    rsort($scores,SORT_NUMERIC); $top=array_slice($scores,0,20);
    $combined=$top?1.0-exp(-array_sum($top)/4.0):0.0; $scoreI=(int)round($combined*100);
    $level=$scoreI>=70?'Very High':($scoreI>=45?'High':($scoreI>=20?'Moderate':'Low'));
    $aligned=0;foreach($scores as $s)if($s>=0.05)$aligned++;
    return ['score'=>$scoreI,'level'=>$level,'aligned_hotspots'=>$aligned,'total_hotspots'=>count($hotspots)];
}

function build_forecast(array $config,string $dataDir,float $lat,float $lon,int $hours=72): array {
    if(!is_ncr_coordinate($lat,$lon))throw new InvalidArgumentException('This service is intentionally limited to Delhi NCR coordinates.');
    $hours=max(1,min(72,$hours));
    $tz=new DateTimeZone($config['timezone']); $nowObj=new DateTimeImmutable('now',$tz); $now=$nowObj->setTime((int)$nowObj->format('H'),0,0);

    $airRows=fetch_air_quality($config,$dataDir,$lat,$lon,24,$hours+1);
    $weatherRows=fetch_weather($config,$dataDir,$lat,$lon,24,$hours+1);
    try{$hotspots=fetch_firms_hotspots($config,$dataDir);}catch(Throwable){$hotspots=[];}
    [$station,$biasKey]=refresh_bias_from_cpcb($config,$dataDir,$lat,$lon);

    $weatherByTime=[];foreach($weatherRows as $r)if(isset($r['time']))$weatherByTime[(string)$r['time']]=$r;
    $times=[];$rawRows=[];
    foreach($airRows as $r){if(!isset($r['time']))continue;$times[]=(string)$r['time'];$rawRows[]=normalize_air_row($r);}
    if(!$times)throw new RuntimeException('No air-quality forecast rows returned.');

    $corrected=[];foreach(POLLUTANTS as $p)$corrected[$p]=[];
    foreach($rawRows as $i=>$row){
        $ts=$times[$i]; $relative=(int)round((parse_local_time($ts,$config['timezone'])->getTimestamp()-$now->getTimestamp())/3600);
        foreach(POLLUTANTS as $p){
            $raw=$row[$p]??null;if($raw===null){$corrected[$p][]=null;continue;}
            $bias=bias_get($dataDir,$biasKey,$p); $corrected[$p][]=round(adaptive_correct((float)$raw,$bias,max(0,$relative)),3);
        }
    }

    $futureIndices=[];foreach($times as $i=>$ts){if(parse_local_time($ts,$config['timezone']) >= $now)$futureIndices[]=$i;if(count($futureIndices)>=$hours)break;}
    $out=[];
    foreach($futureIndices as $horizon=>$i){
        $ts=$times[$i];$w=$weatherByTime[$ts]??[];$instant=[];foreach(POLLUTANTS as $p)$instant[$p]=$corrected[$p][$i]??null;
        $aqiInputs=[
            'pm2_5'=>rolling_mean_at($corrected['pm2_5'],$i,24),
            'pm10'=>rolling_mean_at($corrected['pm10'],$i,24),
            'no2'=>rolling_mean_at($corrected['no2'],$i,24),
            'so2'=>rolling_mean_at($corrected['so2'],$i,24),
            'o3'=>rolling_mean_at($corrected['o3'],$i,8),
            'co'=>null,
        ];
        $coUg=rolling_mean_at($corrected['co'],$i,8);if($coUg!==null)$aqiInputs['co']=$coUg/1000.0;
        $aqi=overall_aqi($aqiInputs);
        $inv=inversion_diagnostics(
            safe_float($w['temperature_2m']??null,25.0),
            isset($w['temperature_925hPa'])&&is_numeric($w['temperature_925hPa'])?(float)$w['temperature_925hPa']:null,
            safe_float($w['boundary_layer_height']??null,700.0),
            safe_float($w['wind_speed_10m']??null,8.0),
            safe_float($w['shortwave_radiation']??null,0.0)
        );
        $plume=plume_summary($hotspots,$w,$lat,$lon);
        $rollingOut=[];foreach($aqiInputs as $k=>$v)$rollingOut[$k]=$v===null?null:round((float)$v,3);
        $out[]=[
            'time'=>$ts,'horizon_h'=>$horizon,'aqi'=>$aqi['aqi'],'category'=>$aqi['category'],'dominant_pollutant'=>$aqi['dominant_pollutant'],'subindices'=>$aqi['subindices'],
            'pollutants_ugm3'=>$instant,'aqi_rolling_inputs'=>$rollingOut,
            'weather'=>[
                'temperature_2m_c'=>$w['temperature_2m']??null,'relative_humidity_2m_pct'=>$w['relative_humidity_2m']??null,
                'wind_speed_10m_kmh'=>$w['wind_speed_10m']??null,'wind_direction_10m_deg'=>$w['wind_direction_10m']??null,
                'pbl_height_m'=>$w['boundary_layer_height']??null,'surface_pressure_hpa'=>$w['surface_pressure']??null,
                'precipitation_mm'=>$w['precipitation']??null,'shortwave_radiation_wm2'=>$w['shortwave_radiation']??null,
            ],
            'inversion'=>$inv,'stubble_plume'=>$plume,
        ];
    }
    if(!$out)throw new RuntimeException('No future forecast hours returned by upstream data sources.');
    $maxAqi=$out[0];$maxInv=$out[0];$maxPlume=$out[0];foreach($out as $r){if($r['aqi']>$maxAqi['aqi'])$maxAqi=$r;if($r['inversion']['score']>$maxInv['inversion']['score'])$maxInv=$r;if($r['stubble_plume']['score']>$maxPlume['stubble_plume']['score'])$maxPlume=$r;}
    return [
        'location'=>['lat'=>$lat,'lon'=>$lon],'generated_at'=>(new DateTimeImmutable('now',$tz))->format(DateTimeInterface::ATOM),
        'summary'=>[
            'current_aqi'=>$out[0]['aqi'],'current_category'=>$out[0]['category'],'peak_aqi'=>$maxAqi['aqi'],'peak_aqi_time'=>$maxAqi['time'],
            'max_inversion_score'=>$maxInv['inversion']['score'],'max_inversion_time'=>$maxInv['time'],'max_plume_score'=>$maxPlume['stubble_plume']['score'],'max_plume_time'=>$maxPlume['time'],
        ],
        'metadata'=>[
            'scope'=>'Delhi NCR only','air_quality_source'=>'CAMS Global via Open-Meteo','weather_source'=>'ECMWF IFS via Open-Meteo',
            'ground_calibration'=>$station?'CPCB adaptive calibration active':'CPCB calibration unavailable for this request',
            'cpcb_station'=>$station?['name'=>$station['name'],'city'=>$station['city'],'lat'=>$station['lat'],'lon'=>$station['lon'],'last_update'=>$station['last_update']]:null,
            'fire_source'=>$hotspots?'NASA FIRMS VIIRS NOAA-20/NOAA-21':'FIRMS plume diagnostics unavailable',
            'method'=>'CAMS chemistry baseline + ECMWF weather/PBL + CPCB rolling AQI + CPCB residual correction + FIRMS plume diagnostics',
            'wrf_chem'=>'Not simulated on shared hosting; adapter-ready for later coupled-model output',
        ],
        'hours'=>$out,
    ];
}

function presets(): array {
    return [
        ['name'=>'Jamia Hamdard','lat'=>28.5132,'lon'=>77.2490],['name'=>'Anand Vihar','lat'=>28.6469,'lon'=>77.3160],
        ['name'=>'R.K. Puram','lat'=>28.5660,'lon'=>77.1767],['name'=>'Dwarka','lat'=>28.5921,'lon'=>77.0460],
        ['name'=>'Noida Sector 62','lat'=>28.6273,'lon'=>77.3725],['name'=>'Gurugram','lat'=>28.4595,'lon'=>77.0266],
        ['name'=>'Faridabad','lat'=>28.4089,'lon'=>77.3178],['name'=>'Ghaziabad','lat'=>28.6692,'lon'=>77.4538],
    ];
}

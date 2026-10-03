<?php
declare(strict_types=1);

const AQI_BANDS = [[0,50],[51,100],[101,200],[201,300],[301,400],[401,500]];
const AQI_BREAKPOINTS = [
    'pm2_5' => [[0,30],[31,60],[61,90],[91,120],[121,250],[251,380]],
    'pm10'  => [[0,50],[51,100],[101,250],[251,350],[351,430],[431,510]],
    'no2'   => [[0,40],[41,80],[81,180],[181,280],[281,400],[401,520]],
    'o3'    => [[0,50],[51,100],[101,168],[169,208],[209,748],[749,1000]],
    'co'    => [[0,1.0],[1.1,2.0],[2.1,10.0],[10.1,17.0],[17.1,34.0],[34.1,50.0]],
    'so2'   => [[0,40],[41,80],[81,380],[381,800],[801,1600],[1601,2620]],
    'nh3'   => [[0,200],[201,400],[401,800],[801,1200],[1201,1800],[1801,2400]],
];

function clampf(float $value, float $lo = 0.0, float $hi = 100.0): float {
    return max($lo, min($hi, $value));
}

function cpcb_category(int $aqi): string {
    return match (true) {
        $aqi <= 50 => 'Good',
        $aqi <= 100 => 'Satisfactory',
        $aqi <= 200 => 'Moderately Polluted',
        $aqi <= 300 => 'Poor',
        $aqi <= 400 => 'Very Poor',
        default => 'Severe',
    };
}

function cpcb_subindex(string $pollutant, float $concentration): int {
    if (!isset(AQI_BREAKPOINTS[$pollutant])) {
        throw new InvalidArgumentException("Unsupported pollutant: {$pollutant}");
    }
    $c = max(0.0, $concentration);
    $bands = AQI_BREAKPOINTS[$pollutant];
    if ($c >= $bands[count($bands)-1][1]) return 500;

    foreach (AQI_BANDS as $i => [$aqiLo, $aqiHi]) {
        [$cLo, $cHi] = $bands[$i];
        if ($c >= $cLo && $c <= $cHi) {
            if ($cHi == $cLo) return (int)round($aqiHi);
            $value = (($aqiHi-$aqiLo)/($cHi-$cLo))*($c-$cLo)+$aqiLo;
            return (int)round($value);
        }
    }
    // Handle tiny decimal gaps such as CO 1.0 -> 1.1.
    for ($i=1; $i<count($bands); $i++) {
        if ($c > $bands[$i-1][1] && $c < $bands[$i][0]) return (int)AQI_BANDS[$i][0];
    }
    return 500;
}

function overall_aqi(array $values): array {
    $sub = [];
    foreach ($values as $pollutant => $value) {
        if ($value === null || !isset(AQI_BREAKPOINTS[$pollutant])) continue;
        $sub[$pollutant] = cpcb_subindex($pollutant, (float)$value);
    }
    if (!$sub) return ['aqi'=>0,'category'=>'Unknown','dominant_pollutant'=>null,'subindices'=>[]];
    arsort($sub);
    $dominant = array_key_first($sub);
    $aqi = (int)$sub[$dominant];
    return ['aqi'=>$aqi,'category'=>cpcb_category($aqi),'dominant_pollutant'=>$dominant,'subindices'=>$sub];
}

function rolling_mean_at(array $values, int $index, int $window, float $minCoverage = 0.75): ?float {
    if ($window <= 0) throw new InvalidArgumentException('window must be positive');
    $start = max(0, $index - $window + 1);
    $slice = array_slice($values, $start, $index-$start+1);
    if (!$slice) return null;
    $valid = array_values(array_filter($slice, static fn($v) => $v !== null));
    $required = max(1, (int)round(count($slice)*$minCoverage + 1e-9));
    if (count($valid) < $required) return null;
    return round(array_sum(array_map('floatval',$valid))/count($valid), 4);
}

function inversion_diagnostics(float $surfaceTempC, ?float $temp925C, float $pblHeightM, float $windSpeedKmh, float $shortwaveWm2): array {
    $inversion = $temp925C === null ? 0.0 : $temp925C - $surfaceTempC;
    $tempComponent = clampf($inversion/8.0*45.0);
    $pblComponent = clampf((900.0-$pblHeightM)/800.0*35.0);
    $windComponent = clampf((12.0-$windSpeedKmh)/12.0*15.0);
    $radiationComponent = clampf((150.0-$shortwaveWm2)/150.0*5.0);
    $score = $tempComponent+$pblComponent+$windComponent+$radiationComponent;
    if ($inversion <= 0.0) $score = min($score, 25.0);
    $scoreI = (int)round(clampf($score));
    $level = $scoreI >= 85 ? 'Severe' : ($scoreI >= 65 ? 'Strong' : ($scoreI >= 35 ? 'Moderate' : 'Weak'));
    $mixingRisk = $scoreI >= 85 ? 'Very High' : ($scoreI >= 65 ? 'High' : ($scoreI >= 35 ? 'Moderate' : 'Low'));
    return [
        'score'=>$scoreI,
        'level'=>$level,
        'temperature_inversion_c'=>round($inversion,2),
        'pbl_height_m'=>round($pblHeightM,1),
        'mixing_risk'=>$mixingRisk,
    ];
}

function haversine_km(float $lat1,float $lon1,float $lat2,float $lon2): float {
    $r=6371.0; $p1=deg2rad($lat1); $p2=deg2rad($lat2); $dphi=deg2rad($lat2-$lat1); $dl=deg2rad($lon2-$lon1);
    $a=sin($dphi/2)**2 + cos($p1)*cos($p2)*sin($dl/2)**2;
    return 2*$r*atan2(sqrt($a),sqrt(max(0.0,1-$a)));
}

function bearing_deg(float $lat1,float $lon1,float $lat2,float $lon2): float {
    $p1=deg2rad($lat1); $p2=deg2rad($lat2); $dl=deg2rad($lon2-$lon1);
    $y=sin($dl)*cos($p2); $x=cos($p1)*sin($p2)-sin($p1)*cos($p2)*cos($dl);
    return fmod(rad2deg(atan2($y,$x))+360.0,360.0);
}

function angle_diff(float $a,float $b): float {
    $d = abs(fmod(($a-$b+540.0),360.0)-180.0);
    return $d;
}

function plume_influence(float $targetLat,float $targetLon,float $fireLat,float $fireLon,float $windFromDeg,string $confidence,float $frp,float $windSpeedKmh,float $pblHeightM,float $precipitationMm): float {
    $distance=haversine_km($fireLat,$fireLon,$targetLat,$targetLon);
    if($distance>600.0) return 0.0;
    $fireToTarget=bearing_deg($fireLat,$fireLon,$targetLat,$targetLon);
    $transportTo=fmod($windFromDeg+180.0,360.0);
    $alignment=max(0.0,cos(deg2rad(angle_diff($fireToTarget,$transportTo))))**3;
    $distanceFactor=exp(-(($distance-250.0)/220.0)**2);
    $frpFactor=min(1.0,max(0.05,$frp/70.0));
    $lc=strtolower($confidence);
    $confidenceFactor=str_starts_with($lc,'h')?1.0:(str_starts_with($lc,'n')?0.8:0.55);
    $windFactor=min(1.0,max(0.1,$windSpeedKmh/15.0));
    $pblFactor=min(1.0,max(0.3,700.0/max(150.0,$pblHeightM)));
    $rainFactor=exp(-0.45*max(0.0,$precipitationMm));
    return round(clampf($alignment*$distanceFactor*$frpFactor*$confidenceFactor*$windFactor*$pblFactor*$rainFactor,0.0,1.0),4);
}

function horizon_observation_weight(int $horizonH): float {
    $h=max(0,$horizonH);
    return min(0.80,max(0.05,0.72*exp(-$h/18.0)+0.05));
}

function adaptive_correct(float $rawForecast,float $recentBias,int $horizonH): float {
    return max(0.0,$rawForecast+$recentBias*horizon_observation_weight($horizonH));
}

function is_ncr_coordinate(float $lat,float $lon): bool {
    return $lat>=27.2 && $lat<=29.95 && $lon>=76.2 && $lon<=78.95;
}

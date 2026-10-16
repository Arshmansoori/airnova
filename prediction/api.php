<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$config = require __DIR__ . '/config.php';
$dataDir = __DIR__ . '/data';
require_once __DIR__ . '/src/ForecastService.php';

function respond(mixed $data, int $status=200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

$action = (string)($_GET['action'] ?? 'health');
try {
    if ($action === 'health') {
        respond([
            'status'=>'ok','scope'=>'Delhi NCR','runtime'=>'PHP '.PHP_VERSION,
            'cpcb_key_configured'=>!empty($config['data_gov_api_key']),
            'firms_key_configured'=>!empty($config['firms_map_key']),
            'data_dir_writable'=>is_writable($dataDir) || (!file_exists($dataDir) && is_writable(__DIR__)),
        ]);
    }
    if ($action === 'presets') respond(presets());
    if ($action === 'upstreams') {
        $result=[];
        try { $a=fetch_air_quality($config,$dataDir,28.6139,77.2090,0,1); $w=fetch_weather($config,$dataDir,28.6139,77.2090,0,1); $result['open_meteo']=['ok'=>(bool)$a && (bool)$w,'detail'=>'CAMS + ECMWF reachable']; }
        catch(Throwable $e){$result['open_meteo']=['ok'=>false,'detail'=>$e->getMessage()];}
        try { $c=fetch_cpcb_city($config,$dataDir,'Delhi'); $result['cpcb']=['ok'=>(bool)$c,'detail'=>$c?count($c).' Delhi stations parsed':'No Delhi station rows']; }
        catch(Throwable $e){$result['cpcb']=['ok'=>false,'detail'=>$e->getMessage()];}
        try { $f=fetch_firms_hotspots($config,$dataDir); $result['firms']=['ok'=>true,'detail'=>count($f).' recent VIIRS hotspots parsed']; }
        catch(Throwable $e){$result['firms']=['ok'=>false,'detail'=>$e->getMessage()];}
        respond($result);
    }
    if ($action === 'forecast') {
        if (!isset($_GET['lat'],$_GET['lon'])) respond(['error'=>'lat and lon are required'],400);
        $lat=filter_var($_GET['lat'],FILTER_VALIDATE_FLOAT);$lon=filter_var($_GET['lon'],FILTER_VALIDATE_FLOAT);
        if($lat===false||$lon===false)respond(['error'=>'invalid coordinates'],400);
        $hours=isset($_GET['hours'])?(int)$_GET['hours']:72;
        respond(build_forecast($config,$dataDir,(float)$lat,(float)$lon,$hours));
    }
    respond(['error'=>'unknown action'],404);
} catch (InvalidArgumentException $e) {
    respond(['error'=>$e->getMessage()],400);
} catch (Throwable $e) {
    respond(['error'=>'Forecast upstream error','detail'=>$e->getMessage()],502);
}

<?php
declare(strict_types=1);

function ensure_data_dir(string $dataDir): void {
    if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
        throw new RuntimeException('Cannot create data directory.');
    }
}

function cache_file(string $dataDir, string $key): string {
    ensure_data_dir($dataDir . '/cache');
    return $dataDir . '/cache/' . hash('sha256', $key) . '.json';
}

function cache_get(string $dataDir, string $key, int $ttl): mixed {
    $file = cache_file($dataDir, $key);
    if (!is_file($file) || (time() - (int)filemtime($file)) > $ttl) return null;
    $raw = @file_get_contents($file);
    if ($raw === false) return null;
    return json_decode($raw, true);
}

function cache_set(string $dataDir, string $key, mixed $value): void {
    $file = cache_file($dataDir, $key);
    $tmp = $file . '.' . getmypid() . '.tmp';
    $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) return;
    if (@file_put_contents($tmp, $json, LOCK_EX) !== false) @rename($tmp, $file);
}

function bias_file(string $dataDir): string {
    ensure_data_dir($dataDir);
    return $dataDir . '/bias.json';
}

function bias_read_all(string $dataDir): array {
    $file = bias_file($dataDir);
    if (!is_file($file)) return [];
    $fp = @fopen($file, 'rb');
    if (!$fp) return [];
    flock($fp, LOCK_SH);
    $raw = stream_get_contents($fp) ?: '';
    flock($fp, LOCK_UN);
    fclose($fp);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function bias_get(string $dataDir, string $locationKey, string $pollutant): float {
    $all = bias_read_all($dataDir);
    return (float)($all[$locationKey][$pollutant]['bias'] ?? 0.0);
}

function bias_update(string $dataDir, string $locationKey, string $pollutant, float $residual, float $alpha = 0.25): float {
    $file = bias_file($dataDir);
    $fp = @fopen($file, 'c+');
    if (!$fp) return 0.0;
    flock($fp, LOCK_EX);
    rewind($fp);
    $raw = stream_get_contents($fp) ?: '';
    $all = json_decode($raw, true);
    if (!is_array($all)) $all = [];
    $old = (float)($all[$locationKey][$pollutant]['bias'] ?? 0.0);
    $n = (int)($all[$locationKey][$pollutant]['n'] ?? 0);
    $new = $n === 0 ? $residual : ($alpha*$residual + (1.0-$alpha)*$old);
    $all[$locationKey][$pollutant] = [
        'bias' => round($new, 6),
        'n' => $n + 1,
        'updated_at' => gmdate('c'),
    ];
    $encoded = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    rewind($fp); ftruncate($fp, 0); fwrite($fp, $encoded ?: '{}'); fflush($fp);
    flock($fp, LOCK_UN); fclose($fp);
    return $new;
}

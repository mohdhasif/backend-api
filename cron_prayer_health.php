<?php
/**
 * cron_prayer_health.php
 * - Health check API Waktu Solat v2 (GPS lat/long)
 * - Ambil beberapa lokasi aktif dari DB, ping API, log OK/ERROR ke table prayer_api_health
 * - Output JSON ringkas untuk debug
 *
 * Jadual (Windows, Task Scheduler):
 *   schtasks /Create /SC MINUTE /MO 15 /TN "PrayerAPIHealth" /TR "C:\backend-api\run_prayer_health.bat" /F /RU "SYSTEM"
 */

require_once __DIR__ . '/db.php'; // $conn (mysqli)
date_default_timezone_set('Asia/Kuala_Lumpur');

// CA bundle untuk elak SSL error 60 (pastikan cacert.pem ada di folder ini)
$CA_CERT = __DIR__ . DIRECTORY_SEPARATOR . 'cacert.pem';

// ---------- HTTP util ----------
function http_get(string $url, int $timeout = 20): array {
  global $CA_CERT;
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_HTTPHEADER => [
      'Accept: application/json',
      'User-Agent: prayer-health/1.0',
    ],
  ]);
  if (is_file($CA_CERT)) {
    curl_setopt($ch, CURLOPT_CAINFO, $CA_CERT);
  }
  $res  = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  $errN = curl_errno($ch);
  $errS = curl_error($ch);
  curl_close($ch);
  return [$res, $code, $errN, $errS];
}

function today_row(array $month): ?array {
  $day = (int)date('j'); // 1..31
  foreach (($month['prayers'] ?? []) as $row) {
    if ((int)($row['day'] ?? 0) === $day) return $row;
  }
  return null;
}

// ---------- Ambil lokasi untuk diuji ----------
// Ambil sehingga 5 lokasi unik (berdasarkan lat/long) yang paling terbaru
$sql = "
  SELECT latitude, longitude
  FROM user_prayer_settings
  WHERE enabled = 1
    AND latitude  IS NOT NULL
    AND longitude IS NOT NULL
  GROUP BY latitude, longitude
  ORDER BY MAX(updated_at) DESC
  LIMIT 5
";
$res = $conn->query($sql);
$targets = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

// fallback default (KL) kalau tiada data
if (!$targets) {
  $targets = [['latitude' => 3.1390, 'longitude' => 101.6869]];
}

$okCount = 0;
$failCount = 0;
$items = [];

foreach ($targets as $t) {
  $lat = (float)$t['latitude'];
  $lng = (float)$t['longitude'];
  $url = "https://api.waktusolat.app/v2/solat/{$lat}/{$lng}";

  [$raw, $code, $errN, $errS] = http_get($url, 25);

  $ok = false; $errMsg = null;
  $fajr = $dhuhr = $asr = $maghrib = $isha = null;

  if ($raw !== null && $code >= 200 && $code < 300) {
    $data = json_decode($raw, true);
    if (is_array($data) && !empty($data['prayers'])) {
      $row = today_row($data);
      if ($row) {
        $fajr    = (int)($row['fajr'] ?? 0);
        $dhuhr   = (int)($row['dhuhr'] ?? 0);
        $asr     = (int)($row['asr'] ?? 0);
        $maghrib = (int)($row['maghrib'] ?? 0);
        $isha    = (int)($row['isha'] ?? 0);
        $ok = ($fajr && $dhuhr && $asr && $maghrib && $isha);
        if (!$ok) $errMsg = 'missing one or more prayer epochs';
      } else {
        $errMsg = 'today not found in prayers';
      }
    } else {
      $errMsg = 'invalid JSON or missing prayers array';
    }
  } else {
    $errMsg = "HTTP=$code CURL=$errN $errS";
  }

  // ---------- Log ke DB ----------
  $stmt = $conn->prepare("
    INSERT INTO prayer_api_health
      (method, latitude, longitude, jakim_zone, ok, http_code, error, fajr, dhuhr, asr, maghrib, isha, checked_at)
    VALUES ('GPS', ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
  ");

  $okInt    = $ok ? 1 : 0;
  $httpCode = (int)($code ?? 0);
  $errShort = $errMsg ? substr($errMsg, 0, 250) : null;

  // types: d d i i s i i i i i  => 10 args
  $stmt->bind_param(
    "ddiisiiiii",
    $lat, $lng, $okInt, $httpCode, $errShort,
    $fajr, $dhuhr, $asr, $maghrib, $isha
  );
  $stmt->execute();

  if ($ok) $okCount++; else $failCount++;

  $items[] = [
    'lat'   => $lat,
    'lng'   => $lng,
    'ok'    => $ok,
    'http'  => $httpCode,
    'error' => $errMsg,
    'today' => [
      'fajr'    => $fajr,
      'dhuhr'   => $dhuhr,
      'asr'     => $asr,
      'maghrib' => $maghrib,
      'isha'    => $isha,
    ],
  ];
}

// ---------- Output ringkas untuk log/monitor ----------
echo json_encode([
  'ok'         => true,
  'checked'    => count($targets),
  'ok_count'   => $okCount,
  'fail_count' => $failCount,
  'items'      => $items,
  'time'       => date('c'),
]);

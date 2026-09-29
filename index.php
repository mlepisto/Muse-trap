<?php
/**
 * Muse trap: logs every request in full and serves probe pages that reveal
 * how an AI agent fetches the web (JS execution, assets, links, robots.txt).
 *
 * All paths route here. Test URLs look like /t/<token>. Results at /_muse?key=...
 */

declare(strict_types=1);

$config = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$ADMIN_KEY = $config['admin_key'] ?? '';
// bcrypt hash of the admin key, so no server-side config file is needed.
// config.php's admin_key, if set, takes precedence.
$ADMIN_KEY_HASH = $config['admin_key_hash'] ?? '$2y$12$Xmdhmp6sSQcBu60WJSSXXe6YECMrpE9NrpcwJ.lkdF.MSk8Tivea6';
$DATA_DIR  = $config['data_dir'] ?? pick_data_dir();
$LOG_FILE  = $DATA_DIR . '/hits.jsonl';
$IP_CACHE  = $DATA_DIR . '/ipcache.json';
$SETTINGS_FILE = $DATA_DIR . '/settings.json';
$settings = is_file($SETTINGS_FILE) ? (json_decode((string)file_get_contents($SETTINGS_FILE), true) ?: []) : [];

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Prefer a folder next to the web root. Fall back to a hidden folder inside
// it, which nginx on SpinupWP refuses to serve.
function pick_data_dir(): string
{
    $outside = dirname(__DIR__) . '/muse-data';
    if (is_dir($outside) ? is_writable($outside) : is_writable(dirname(__DIR__))) {
        return $outside;
    }
    return __DIR__ . '/.muse-data';
}

// ---------------------------------------------------------------- logging

function request_headers_ordered(): array
{
    $out = [];
    foreach ($_SERVER as $k => $v) {
        if (str_starts_with($k, 'HTTP_')) {
            $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $out[] = [$name, (string)$v];
        } elseif ($k === 'CONTENT_TYPE' || $k === 'CONTENT_LENGTH') {
            if ($v !== '') {
                $out[] = [$k === 'CONTENT_TYPE' ? 'Content-Type' : 'Content-Length', (string)$v];
            }
        }
    }
    return $out;
}

function classify(string $path): array
{
    if (preg_match('#^/t/([A-Za-z0-9_-]{6,64})(?:/([a-z-]+))?/?$#', $path, $m)) {
        return [$m[1], $m[2] ?? 'page'];
    }
    if (preg_match('#^/private/([A-Za-z0-9_-]{6,64})/?$#', $path, $m)) {
        return [$m[1], 'private'];
    }
    if ($path === '/robots.txt') {
        return [null, 'robots'];
    }
    return [null, null];
}

function log_hit(string $file, string $dir, string $path): array
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    [$token, $probe] = classify($path);
    $body = file_get_contents('php://input', false, null, 0, 16384);
    $rec = [
        't'      => $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true),
        'iso'    => gmdate('Y-m-d\TH:i:s\Z'),
        'ip'     => $_SERVER['REMOTE_ADDR'] ?? '',
        'port'   => $_SERVER['REMOTE_PORT'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? '',
        'uri'    => $_SERVER['REQUEST_URI'] ?? '',
        'proto'  => $_SERVER['SERVER_PROTOCOL'] ?? '',
        'https'  => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'tls'    => $_SERVER['SSL_PROTOCOL'] ?? null,
        'token'  => $token,
        'probe'  => $probe,
        'headers'=> request_headers_ordered(),
        'body'   => $body === '' || $body === false ? null : $body,
    ];
    file_put_contents($file, json_encode($rec, JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND | LOCK_EX);
    return $rec;
}

// Server-side copy of every request into GA4 as an `agent_request` event.
// Runs after the response is sent so it never slows the visitor down.
function ga4_send_hit(array $settings, array $rec): void
{
    if (empty($settings['ga4_mp']) || empty($settings['ga4_id']) || empty($settings['ga4_secret'])) {
        return;
    }
    $ua = '';
    foreach ($rec['headers'] as [$k, $v]) {
        if ($k === 'User-Agent') {
            $ua = $v;
        }
    }
    $payload = [
        'client_id' => sprintf('%u.%u', crc32($rec['ip'] . '|' . $ua), crc32($ua)),
        'events' => [[
            'name' => 'agent_request',
            'params' => [
                'probe'      => $rec['probe'] ?? 'other',
                'token'      => $rec['token'] ?? '',
                'path'       => substr((string)parse_url($rec['uri'], PHP_URL_PATH), 0, 100),
                'user_agent' => substr($ua, 0, 100),
                'engagement_time_msec' => 1,
            ],
        ]],
    ];
    $url = 'https://www.google-analytics.com/mp/collect?measurement_id=' . rawurlencode($settings['ga4_id'])
         . '&api_secret=' . rawurlencode($settings['ga4_secret']);
    @file_get_contents($url, false, stream_context_create(['http' => [
        'method' => 'POST', 'header' => "Content-Type: application/json\r\n",
        'content' => json_encode($payload), 'timeout' => 3,
    ]]));
}

function canary(string $token, string $kind): string
{
    return strtoupper($kind) . '-' . hash('crc32b', $token . ':' . $kind);
}

function no_cache(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
}

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------- admin

if ($path === '/_muse' || $path === '/_muse/') {
    require __DIR__ . '/admin.php';
    exit;
}

$hit = log_hit($LOG_FILE, $DATA_DIR, $path);
register_shutdown_function(function () use ($settings, $hit) {
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    ga4_send_hit($settings, $hit);
});
no_cache();

// ---------------------------------------------------------------- probes

const PIXEL_GIF = "GIF89a\x01\x00\x01\x00\x80\x00\x00\x00\x00\x00\xff\xff\xff!\xf9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";

[$token, $probe] = classify($path);

if ($probe === 'robots') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow: /private/\n";
    exit;
}

if ($token === null) {
    if ($path === '/') {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><title>Notes</title><p>Nothing to see here.</p>';
        exit;
    }
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}

$base = '/t/' . $token;

switch ($probe) {
    case 'css':
        header('Content-Type: text/css; charset=utf-8');
        echo "body{font-family:Georgia,serif;max-width:40rem;margin:2rem auto;padding:0 1rem;line-height:1.6}\n";
        echo ".hero{height:120px;background:#eee url($base/bg) center/cover no-repeat;margin-bottom:1rem}\n";
        exit;

    case 'img':
    case 'bg':
        header('Content-Type: image/gif');
        echo PIXEL_GIF;
        exit;

    case 'js':
    case 'jslate':
        header('Content-Type: text/plain; charset=utf-8');
        echo canary($token, $probe);
        exit;

    case 'beacon':
    case 'ga':
        http_response_code(204);
        exit;

    case 'next':
        header('Content-Type: text/html; charset=utf-8');
        $c = canary($token, 'next');
        echo "<!doctype html><html lang=en><head><meta charset=utf-8><title>Part two</title></head><body>"
           . "<h1>Part two</h1><p>This follow-up page carries the reference code <b>$c</b>.</p></body></html>";
        exit;

    case 'private':
        header('Content-Type: text/html; charset=utf-8');
        $c = canary($token, 'private');
        echo "<!doctype html><html lang=en><head><meta charset=utf-8><title>Members area</title></head><body>"
           . "<h1>Members area</h1><p>The access phrase for this page is <b>$c</b>.</p></body></html>";
        exit;

    case 'page':
        break;

    default:
        http_response_code(404);
        exit;
}

// Main probe page. Each canary shows up only through one channel, so whatever
// the agent quotes back reveals how it read the page.
$c = fn(string $k) => h(canary($token, $k));
$t = h($token);
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Field notes on coastal lighthouses</title>
<meta name="description" content="Lighthouse survey notes. Survey code <?= $c('meta') ?>.">
<link rel="stylesheet" href="/t/<?= $t ?>/css">
<?php if (!empty($settings['ga4_id'])): $ga = h($settings['ga4_id']); ?>
<script>
  // GA4 tag, with pings back to us at each stage so the log shows how far it got.
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  function gaPing(stage){ try { navigator.sendBeacon('/t/<?= $t ?>/ga?stage=' + stage); } catch (e) {} }
  gtag('js', new Date());
  gtag('config', '<?= $ga ?>');
  gtag('event', 'probe_view', {token: '<?= $t ?>', event_callback: function(){ gaPing('sent'); }});
</script>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= $ga ?>" onload="gaPing('loaded')" onerror="gaPing('blocked')"></script>
<?php endif; ?>
</head>
<body>
<!-- Archive reference: <?= $c('comment') ?> -->
<div class="hero"></div>
<h1>Field notes on coastal lighthouses</h1>
<p>These notes cover a short survey of lighthouses along a rocky coastline.
The reference code for this article is <b><?= $c('static') ?></b>.</p>

<img src="/t/<?= $t ?>/img" alt="Lighthouse photo, catalogue <?= $c('alt') ?>" width="1" height="1">

<p>Most of the towers were automated decades ago. Keepers are gone, but the
lamps still turn every night.</p>

<div style="display:none">Internal note, hidden from readers: <?= $c('hidden') ?>.</div>

<p id="live">Live status: loading…</p>
<p id="late"></p>

<noscript><p>Scripts are disabled. Fallback code: <?= $c('noscript') ?>.</p></noscript>

<p>Continue reading in <a href="/t/<?= $t ?>/next">part two</a>.
Members can find more in the <a href="/private/<?= $t ?>">members area</a>.</p>

<script>
(function () {
  var base = '/t/<?= $t ?>';
  function put(id, url, label) {
    fetch(url, {cache: 'no-store'}).then(function (r) { return r.text(); })
      .then(function (txt) { document.getElementById(id).textContent = label + txt; })
      .catch(function () {});
  }
  put('live', base + '/js', 'Live status: ');
  setTimeout(function () { put('late', base + '/jslate', 'Delayed update: '); }, 3000);

  var fp = {};
  try {
    fp.ua = navigator.userAgent;
    fp.webdriver = navigator.webdriver;
    fp.languages = navigator.languages;
    fp.platform = navigator.platform;
    fp.cores = navigator.hardwareConcurrency;
    fp.memory = navigator.deviceMemory;
    fp.plugins = navigator.plugins ? navigator.plugins.length : null;
    fp.screen = [screen.width, screen.height, screen.colorDepth];
    fp.viewport = [innerWidth, innerHeight];
    fp.tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    fp.touch = navigator.maxTouchPoints;
    fp.uaData = navigator.userAgentData ? navigator.userAgentData.brands : null;
    var gl = document.createElement('canvas').getContext('webgl');
    var dbg = gl && gl.getExtension('WEBGL_debug_renderer_info');
    fp.gpu = dbg ? gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL) : null;
  } catch (e) { fp.err = String(e); }
  fp.elapsedMs = Math.round(performance.now());
  fetch(base + '/beacon', {method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(fp), keepalive: true}).catch(function () {});
})();
</script>
</body>
</html>

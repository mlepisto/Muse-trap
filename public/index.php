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
$DATA_DIR  = $config['data_dir'] ?? dirname(__DIR__) . '/muse-data';
$LOG_FILE  = $DATA_DIR . '/hits.jsonl';
$IP_CACHE  = $DATA_DIR . '/ipcache.json';

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

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

function log_hit(string $file, string $dir, string $path): void
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

log_hit($LOG_FILE, $DATA_DIR, $path);
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

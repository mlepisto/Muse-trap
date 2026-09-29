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

function token_ok(mixed $s): bool
{
    return is_string($s) && preg_match('/^[A-Za-z0-9_-]{6,64}$/', $s) === 1;
}

// Which test (token) and which probe a request belongs to, and how the token
// was carried: in the path, a ?r= link, a form field, or the mt cookie.
function classify(string $path): array
{
    if (preg_match('#^/t/([A-Za-z0-9_-]{6,64})(?:/([a-z-]+))?/?$#', $path, $m)) {
        return [$m[1], $m[2] ?? 'page', 'path'];
    }
    if (preg_match('#^/journal/survey-notes-([A-Za-z0-9_-]{6,64}?)(/part-2)?/?$#', $path, $m)) {
        return [$m[1], empty($m[2]) ? 'page' : 'next', 'path'];
    }
    if (preg_match('#^/private/([A-Za-z0-9_-]{6,64})/?$#', $path, $m)) {
        return [$m[1], 'private', 'path'];
    }
    if ($path === '/robots.txt') {
        return [null, 'robots', null];
    }

    if (token_ok($_GET['r'] ?? null)) {
        [$token, $via] = [$_GET['r'], 'link'];
    } elseif (token_ok($_POST['r'] ?? null)) {
        [$token, $via] = [$_POST['r'], 'form'];
    } elseif (token_ok($_COOKIE['mt'] ?? null)) {
        [$token, $via] = [$_COOKIE['mt'], 'cookie'];
    } else {
        return [null, null, null];
    }
    $probe = match (rtrim($path, '/')) {
        '/members'           => 'private',
        '/members/login'     => 'login',
        '/members/directory' => 'directory',
        '/contact'           => ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? 'contact-form' : 'nav:contact',
        ''                   => 'nav:home',
        default              => 'nav:' . trim($path, '/'),
    };
    return [$token, $probe, $via];
}

function log_hit(string $file, string $dir, string $path): array
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    [$token, $probe, $via] = classify($path);
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
        'via'    => $via,
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

// ---------------------------------------------------------------- routes

require __DIR__ . '/site.php';

[$token, $probe, $via] = classify($path);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Remember the test on this client, so later visits without ?r= stay attributed
// (only if the agent keeps cookies, which is itself worth knowing).
if ($token !== null && $via !== 'cookie' && ($_COOKIE['mt'] ?? '') !== $token) {
    setcookie('mt', $token, ['expires' => time() + 86400, 'path' => '/', 'samesite' => 'Lax', 'httponly' => true]);
}

if ($probe === 'robots') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nDisallow: /members\nDisallow: /private/\n";
    exit;
}

// Sub-resources of a test: /t/<token>/<probe>
if (preg_match('#^/t/[^/]+/[a-z-]+/?$#', $path)) {
    switch ($probe) {
        case 'css':
            header('Content-Type: text/css; charset=utf-8');
            echo site_css('/t/' . $token . '/bg');
            exit;
        case 'img':
            // Night-time variant, so the article photo differs from the banner.
            header('Content-Type: image/svg+xml');
            echo str_replace(['#27435a', '#8fb0c4', '#e9d8b8'], ['#0b1622', '#1d3347', '#3d4f5e'], hero_svg());
            exit;
        case 'bg':
            header('Content-Type: image/svg+xml');
            echo hero_svg();
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
            header('Location: ' . article_url($token) . '/part-2', true, 301);
            exit;
    }
    page_404($token, $settings);
    exit;
}

switch (true) {
    case $path === '/assets/site-css':
        header('Content-Type: text/css; charset=utf-8');
        echo site_css('/assets/hero');
        exit;
    case $path === '/assets/hero':
        header('Content-Type: image/svg+xml');
        echo hero_svg();
        exit;

    case $probe === 'page' && $via === 'path':
        page_article($token, $settings);
        exit;
    case $probe === 'next' && $via === 'path':
        page_part2($token, $settings);
        exit;

    case $path === '/':
        page_home($token, $settings);
        exit;
    case $path === '/lighthouses':
        page_lighthouses($token, $settings);
        exit;
    case $path === '/journal':
        page_journal_index($token, $settings);
        exit;
    case (bool)preg_match('#^/journal/([a-z0-9-]+)$#', $path, $m) && isset(POSTS[$m[1]]):
        page_journal_post($m[1], $token, $settings);
        exit;
    case $path === '/events':
        page_events($token, $settings);
        exit;
    case $path === '/about':
        page_about($token, $settings);
        exit;
    case $path === '/contact':
        page_contact($token, $settings, $method === 'POST');
        exit;

    case $path === '/members' || $path === '/members/' || str_starts_with($path, '/private/'):
        page_members_login($token, $settings);
        exit;
    case $path === '/members/login':
        if ($method !== 'POST') {
            header('Location: ' . link_to('/members', $token), true, 303);
            exit;
        }
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        if ($token !== null && hash_equals(access_code($token), $code)) {
            setcookie('mt_auth', auth_value($token, $ADMIN_KEY_HASH), ['expires' => time() + 86400, 'path' => '/members', 'samesite' => 'Lax', 'httponly' => true]);
            header('Location: ' . link_to('/members/directory', $token), true, 303);
            exit;
        }
        http_response_code(401);
        page_members_login($token, $settings, "That access code wasn't recognised. Check your membership pack and try again.");
        exit;
    case $path === '/members/directory':
        if ($token !== null && hash_equals(auth_value($token, $ADMIN_KEY_HASH), (string)($_COOKIE['mt_auth'] ?? ''))) {
            page_members_directory($token, $settings);
            exit;
        }
        header('Location: ' . link_to('/members', $token), true, 303);
        exit;
    case $path === '/members/logout':
        setcookie('mt_auth', '', ['expires' => 1, 'path' => '/members']);
        header('Location: ' . link_to('/members', $token), true, 303);
        exit;
}

page_404($token, $settings);

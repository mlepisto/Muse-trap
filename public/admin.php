<?php
/**
 * Results view, included from index.php at /_muse?key=...
 */

declare(strict_types=1);

if (!isset($LOG_FILE)) {
    http_response_code(404);
    exit;
}

no_cache();

if ($ADMIN_KEY === '' || $ADMIN_KEY === 'change-me') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Set admin_key in config.php first.\n";
    exit;
}
if (!hash_equals($ADMIN_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Not found\n";
    exit;
}

if (($_GET['format'] ?? '') === 'jsonl') {
    header('Content-Type: application/x-ndjson');
    header('Content-Disposition: attachment; filename="hits.jsonl"');
    if (is_file($LOG_FILE)) {
        readfile($LOG_FILE);
    }
    exit;
}

// ---------------------------------------------------------------- IP enrichment

function dns_txt(string $name): ?string
{
    $r = @dns_get_record($name, DNS_TXT);
    return $r && isset($r[0]['txt']) ? $r[0]['txt'] : null;
}

function enrich_ip(string $ip): array
{
    $info = ['rdns' => null, 'fcrdns' => false, 'asn' => null, 'prefix' => null, 'cc' => null, 'as_name' => null];
    $packed = @inet_pton($ip);
    if ($packed === false) {
        return $info;
    }

    $host = @gethostbyaddr($ip);
    if ($host && $host !== $ip) {
        $info['rdns'] = $host;
        $addrs = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $rr) {
            $addrs[] = $rr['ip'] ?? $rr['ipv6'] ?? null;
        }
        foreach ($addrs as $a) {
            if ($a && @inet_pton($a) === $packed) {
                $info['fcrdns'] = true;
            }
        }
    }

    // Team Cymru IP-to-ASN over DNS: no API key, works for v4 and v6.
    if (strlen($packed) === 4) {
        $q = implode('.', array_reverse(explode('.', $ip))) . '.origin.asn.cymru.com';
    } else {
        $q = implode('.', array_reverse(str_split(bin2hex($packed)))) . '.origin6.asn.cymru.com';
    }
    if ($txt = dns_txt($q)) {
        $p = array_map('trim', explode('|', $txt));
        $info['asn'] = explode(' ', $p[0])[0];
        $info['prefix'] = $p[1] ?? null;
        $info['cc'] = $p[2] ?? null;
        if ($info['asn'] && ($n = dns_txt('AS' . $info['asn'] . '.asn.cymru.com'))) {
            $np = array_map('trim', explode('|', $n));
            $info['as_name'] = end($np);
        }
    }
    return $info;
}

$ipCache = is_file($IP_CACHE) ? (json_decode((string)file_get_contents($IP_CACHE), true) ?: []) : [];
$cacheDirty = false;
$ipInfo = function (string $ip) use (&$ipCache, &$cacheDirty): array {
    if (!isset($ipCache[$ip])) {
        $ipCache[$ip] = enrich_ip($ip);
        $cacheDirty = true;
    }
    return $ipCache[$ip];
};

// ---------------------------------------------------------------- load log

$hits = [];
if (is_file($LOG_FILE)) {
    foreach (file($LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $r = json_decode($line, true);
        if (is_array($r)) {
            $hits[] = $r;
        }
    }
}

$byToken = [];
$robots = [];
$other = [];
foreach ($hits as $r) {
    if (!empty($r['token'])) {
        $byToken[$r['token']][] = $r;
    } elseif (($r['probe'] ?? null) === 'robots') {
        $robots[] = $r;
    } else {
        $other[] = $r;
    }
}
uasort($byToken, fn($a, $b) => $b[0]['t'] <=> $a[0]['t']);

$hdr = function (array $r, string $name): string {
    foreach ($r['headers'] ?? [] as [$k, $v]) {
        if (strcasecmp($k, $name) === 0) {
            return $v;
        }
    }
    return '';
};

$ipLabel = function (string $ip) use ($ipInfo): string {
    $i = $ipInfo($ip);
    $as = $i['asn'] ? 'AS' . $i['asn'] . ' ' . ($i['as_name'] ?? '') : 'ASN unknown';
    $dns = $i['rdns'] ? $i['rdns'] . ($i['fcrdns'] ? ' ✓' : ' ✗ not forward-confirmed') : 'no rDNS';
    return h($ip) . '<br><small>' . h($as) . '<br>' . h($dns) . '</small>';
};

// Probes a normal browser would trigger, in the order they tell a story.
$PROBES = [
    'page'    => 'Fetched the page',
    'css'     => 'Loaded stylesheet',
    'img'     => 'Loaded <img>',
    'bg'      => 'Loaded CSS background (rendered layout)',
    'js'      => 'Ran JavaScript',
    'beacon'  => 'Sent JS fingerprint',
    'jslate'  => 'Waited 3s+ for delayed JS',
    'next'    => 'Followed link to part two',
    'private' => 'Fetched robots-disallowed page',
];
$CANARIES = ['static', 'meta', 'comment', 'alt', 'hidden', 'noscript', 'js', 'jslate', 'next', 'private'];

$host = $_SERVER['HTTP_HOST'] ?? 'your-domain';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$newToken = bin2hex(random_bytes(6));
$key = h($_GET['key']);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Muse trap results</title>
<style>
  :root { --bg:#fff; --fg:#1a1a1a; --muted:#666; --line:#ddd; --ok:#1a7f37; --no:#b42318; --card:#f6f6f4; }
  @media (prefers-color-scheme: dark) { :root { --bg:#141414; --fg:#eaeaea; --muted:#999; --line:#333; --ok:#4ac26b; --no:#f47067; --card:#1e1e1e; } }
  body { background:var(--bg); color:var(--fg); font:14px/1.45 system-ui, sans-serif; margin:0; padding:16px; }
  main { max-width:1200px; margin:0 auto; }
  h1 { font-size:20px; } h2 { font-size:16px; margin-top:2rem; }
  code, pre { font:12px ui-monospace, monospace; }
  pre { white-space:pre-wrap; word-break:break-all; background:var(--card); padding:8px; border-radius:4px; }
  .card { background:var(--card); border:1px solid var(--line); border-radius:6px; padding:12px 16px; margin:12px 0; }
  .wrap { overflow-x:auto; }
  table { border-collapse:collapse; width:100%; }
  th, td { text-align:left; vertical-align:top; padding:6px 8px; border-bottom:1px solid var(--line); }
  small { color:var(--muted); }
  .ok { color:var(--ok); } .no { color:var(--no); }
  ul.checks { list-style:none; padding:0; columns:2; } @media (max-width:640px) { ul.checks { columns:1; } }
  textarea { width:100%; min-height:70px; box-sizing:border-box; background:var(--bg); color:var(--fg); border:1px solid var(--line); }
</style>
</head>
<body>
<main>
<h1>Muse trap</h1>
<p><?= count($hits) ?> requests logged. <a href="?key=<?= $key ?>&format=jsonl">Download raw log</a></p>

<div class="card">
  <strong>New test URL</strong> (reload for another, use each once):<br>
  <code><?= h("$scheme://$host/t/$newToken") ?></code>
</div>

<?php foreach ($byToken as $token => $rows):
    $seen = array_count_values(array_map(fn($r) => $r['probe'], $rows));
    $t0 = $rows[0]['t'];
    $ips = array_unique(array_column($rows, 'ip'));
    $asns = array_unique(array_filter(array_map(fn($ip) => $ipInfo($ip)['asn'], $ips)));
    $robotsNear = array_filter($robots, function ($r) use ($t0, $ips, $asns, $ipInfo) {
        return abs($r['t'] - $t0) < 600 && (in_array($r['ip'], $ips, true) || in_array($ipInfo($r['ip'])['asn'], $asns, true));
    });
    $canaryMap = [];
    foreach ($CANARIES as $k) { $canaryMap[$k] = canary($token, $k); }
?>
<h2>Token <code><?= h($token) ?></code> <small><?= h($rows[0]['iso']) ?>, <?= count($rows) ?> requests</small></h2>
<div class="card">
  <ul class="checks">
  <?php foreach ($PROBES as $p => $label): ?>
    <li class="<?= isset($seen[$p]) ? 'ok' : 'no' ?>"><?= isset($seen[$p]) ? '✓' : '✗' ?> <?= h($label) ?><?= ($seen[$p] ?? 0) > 1 ? ' ×' . $seen[$p] : '' ?></li>
  <?php endforeach; ?>
    <li class="<?= $robotsNear ? 'ok' : 'no' ?>"><?= $robotsNear ? '✓' : '✗' ?> Checked robots.txt (same IP/ASN, ±10 min)</li>
  </ul>

  <details><summary>Check the agent's reply against the canaries</summary>
    <p><small>Paste what the agent said about the page. Each code only appears through one channel.</small></p>
    <textarea data-canaries='<?= h(json_encode($canaryMap)) ?>' oninput="checkReply(this)"></textarea>
    <ul class="checks reply-result">
    <?php foreach ($canaryMap as $k => $v): ?><li data-k="<?= h($k) ?>">· <?= h($k) ?> <small><?= h($v) ?></small></li><?php endforeach; ?>
    </ul>
  </details>
</div>

<div class="wrap"><table>
<tr><th>+s</th><th>Probe</th><th>IP / network</th><th>User-Agent &amp; headers</th></tr>
<?php foreach ($rows as $r): ?>
<tr>
  <td><?= number_format($r['t'] - $t0, 2) ?></td>
  <td><?= h($r['probe']) ?><br><small><?= h($r['method']) ?> <?= h($r['proto']) ?></small></td>
  <td><?= $ipLabel($r['ip']) ?></td>
  <td><code><?= h($hdr($r, 'User-Agent')) ?: '<em>none</em>' ?></code>
    <details><summary><small><?= count($r['headers']) ?> headers</small></summary>
      <pre><?php foreach ($r['headers'] as [$k, $v]) { echo h("$k: $v") . "\n"; } ?></pre>
    </details>
    <?php if ($r['body']): ?>
      <pre><?= h(json_encode(json_decode($r['body']), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: $r['body']) ?></pre>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table></div>
<?php endforeach; ?>

<?php if (!$byToken): ?><p>No test hits yet.</p><?php endif; ?>

<h2>robots.txt fetches <small><?= count($robots) ?></small></h2>
<div class="wrap"><table>
<?php foreach (array_slice(array_reverse($robots), 0, 50) as $r): ?>
<tr><td><?= h($r['iso']) ?></td><td><?= $ipLabel($r['ip']) ?></td><td><code><?= h($hdr($r, 'User-Agent')) ?></code></td></tr>
<?php endforeach; ?>
</table></div>

<details><summary><h2 style="display:inline">Other traffic <small><?= count($other) ?> (scanners, previews)</small></h2></summary>
<div class="wrap"><table>
<?php foreach (array_slice(array_reverse($other), 0, 100) as $r): ?>
<tr><td><?= h($r['iso']) ?></td><td><?= $ipLabel($r['ip']) ?></td><td><code><?= h($r['method'] . ' ' . $r['uri']) ?></code><br><small><?= h($hdr($r, 'User-Agent')) ?></small></td></tr>
<?php endforeach; ?>
</table></div>
</details>
</main>
<script>
function checkReply(el) {
  var map = JSON.parse(el.dataset.canaries), txt = el.value.toUpperCase();
  el.parentNode.querySelectorAll('.reply-result li').forEach(function (li) {
    var hit = txt.indexOf(map[li.dataset.k]) !== -1;
    li.className = el.value ? (hit ? 'ok' : 'no') : '';
    li.firstChild.textContent = el.value ? (hit ? '✓ ' : '✗ ') : '· ';
  });
}
</script>
</body>
</html>
<?php
if ($cacheDirty) {
    @file_put_contents($IP_CACHE, json_encode($ipCache, JSON_UNESCAPED_SLASHES), LOCK_EX);
}

<?php
/**
 * The fake public site: Northshore Lighthouse Society. Included from index.php.
 *
 * Every page works with or without a test token. With a token (from the test
 * URL, ?r=, a form field or the mt cookie) links carry it along so each click
 * stays attributed to that test.
 */

declare(strict_types=1);

if (!isset($LOG_FILE)) {
    http_response_code(404);
    exit;
}

const SITE_NAME = 'Northshore Lighthouse Society';

function access_code(string $token): string
{
    return 'NLS-' . strtoupper(substr(hash('sha256', $token . ':code'), 0, 8));
}

function auth_value(string $token, string $secret): string
{
    return hash_hmac('sha256', $token, $secret);
}

function article_url(string $token): string
{
    return '/journal/survey-notes-' . $token;
}

// Internal link that keeps the test token attached.
function link_to(string $path, ?string $token): string
{
    if ($token === null) {
        return $path;
    }
    return $path . (str_contains($path, '?') ? '&' : '?') . 'r=' . rawurlencode($token);
}

function ga_tag(array $settings, ?string $token): string
{
    if (empty($settings['ga4_id'])) {
        return '';
    }
    $ga = h($settings['ga4_id']);
    $ping = $token ? "try { navigator.sendBeacon('/t/" . h($token) . "/ga?stage=' + s); } catch (e) {}" : '';
    $params = $token ? "{token: '" . h($token) . "', event_callback: function(){ gaPing('sent'); }}" : '{}';
    return <<<HTML
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  function gaPing(s){ $ping }
  gtag('js', new Date());
  gtag('config', '$ga');
  gtag('event', 'probe_view', $params);
</script>
<script async src="https://www.googletagmanager.com/gtag/js?id=$ga" onload="gaPing('loaded')" onerror="gaPing('blocked')"></script>

HTML;
}

function render_page(string $title, string $body, array $o = []): void
{
    $token = $o['token'] ?? null;
    $settings = $o['settings'] ?? [];
    $active = $o['active'] ?? '';
    $css = $token ? '/t/' . h($token) . '/css' : '/assets/site-css';
    $desc = h($o['description'] ?? 'A volunteer society caring for the lighthouses of the Northshore coast since 1978.');
    $nav = [
        '/'            => 'Home',
        '/lighthouses' => 'Lighthouses',
        '/journal'     => 'Journal',
        '/events'      => 'Events',
        '/about'       => 'About',
        '/members'     => 'Members',
        '/contact'     => 'Contact',
    ];
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . ' · ' . SITE_NAME . '</title>';
    echo '<meta name="description" content="' . $desc . '">';
    echo '<link rel="stylesheet" href="' . $css . '">';
    echo $o['head'] ?? '';
    echo ga_tag($settings, $token);
    echo '</head><body>';
    echo $o['before'] ?? '';
    echo '<header class="site"><div class="wrap"><a class="brand" href="' . h(link_to('/', $token)) . '">';
    echo '<span class="mark" aria-hidden="true">✦</span> ' . SITE_NAME . '</a><nav>';
    foreach ($nav as $href => $label) {
        $on = $active === $href ? ' class="on"' : '';
        echo '<a href="' . h(link_to($href, $token)) . '"' . $on . '>' . $label . '</a>';
    }
    echo '</nav></div></header>';
    echo '<main class="wrap">' . $body . '</main>';
    echo '<footer class="site"><div class="wrap"><p><strong>' . SITE_NAME . '</strong><br>';
    echo 'The Old Coastguard Station, Harbour Road, Northshore<br>Registered charity no. 1049772</p>';
    echo '<p>Volunteer-run since 1978. Open-day tours April to October.</p></div></footer>';
    echo $o['after'] ?? '';
    echo '</body></html>';
}

function site_css(string $heroUrl): string
{
    return <<<CSS
:root{--ink:#1d2a33;--muted:#5a6b76;--sea:#1f4e6b;--foam:#eef3f6;--line:#d6dfe5;--accent:#c0392b;--paper:#fbfaf7}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:17px/1.65 Georgia,'Times New Roman',serif}
a{color:var(--sea)}
.wrap{max-width:62rem;margin:0 auto;padding:0 16px}
header.site{background:var(--sea);color:#fff}
header.site .wrap{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;padding-top:14px;padding-bottom:14px}
header.site a{color:#fff;text-decoration:none}
.brand{font-weight:bold;font-size:1.1rem}
.mark{color:#f6c945}
header.site nav{display:flex;flex-wrap:wrap;gap:4px 16px;font:15px system-ui,sans-serif}
header.site nav a{opacity:.85}
header.site nav a.on,header.site nav a:hover{opacity:1;text-decoration:underline}
.hero{height:260px;margin:0 -16px 24px;background:#9fb9c8 url($heroUrl) center/cover no-repeat;position:relative}
.hero h1{position:absolute;left:16px;bottom:16px;margin:0;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.5);font-size:2rem;max-width:30rem;line-height:1.2}
main{padding-top:24px;padding-bottom:48px}
h1{font-size:2rem;line-height:1.2}
h2{font-size:1.35rem;margin-top:2rem}
.lede{font-size:1.15rem;color:var(--muted)}
.meta{font:14px system-ui,sans-serif;color:var(--muted)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px}
.card{background:#fff;border:1px solid var(--line);border-radius:8px;padding:16px}
.card h3{margin:0 0 6px;font-size:1.1rem}
.card p{margin:0;font-size:15px}
figure{margin:24px 0}
figure img{width:100%;height:auto;border-radius:6px;display:block;background:#9fb9c8}
figcaption{font:14px system-ui,sans-serif;color:var(--muted);margin-top:6px}
table{border-collapse:collapse;width:100%;font:15px system-ui,sans-serif}
th,td{text-align:left;padding:8px;border-bottom:1px solid var(--line)}
form.box{background:#fff;border:1px solid var(--line);border-radius:8px;padding:20px;max-width:26rem}
label{display:block;font:14px system-ui,sans-serif;margin:12px 0 4px}
input,textarea{width:100%;padding:10px;border:1px solid var(--line);border-radius:6px;font:16px system-ui,sans-serif}
button{margin-top:16px;background:var(--sea);color:#fff;border:0;border-radius:6px;padding:10px 18px;font:16px system-ui,sans-serif;cursor:pointer}
.notice{background:var(--foam);border-left:4px solid var(--sea);padding:12px 16px;margin:16px 0}
.error{background:#fdecea;border-left:4px solid var(--accent);padding:12px 16px}
footer.site{border-top:1px solid var(--line);font:14px system-ui,sans-serif;color:var(--muted);padding:24px 0}
@media (max-width:640px){body{font-size:16px}.hero{height:180px}.hero h1{font-size:1.5rem}}
CSS;
}

function hero_svg(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 500" preserveAspectRatio="xMidYMid slice">
<defs>
<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#27435a"/><stop offset=".6" stop-color="#8fb0c4"/><stop offset="1" stop-color="#e9d8b8"/></linearGradient>
<linearGradient id="sea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2d5670"/><stop offset="1" stop-color="#16303f"/></linearGradient>
<linearGradient id="beam" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#fff6c8" stop-opacity=".75"/><stop offset="1" stop-color="#fff6c8" stop-opacity="0"/></linearGradient>
</defs>
<rect width="1200" height="500" fill="url(#sky)"/>
<polygon points="838,158 1200,60 1200,250" fill="url(#beam)"/>
<rect y="360" width="1200" height="140" fill="url(#sea)"/>
<path d="M0 380 Q150 370 300 382 T600 380 T900 384 T1200 378" stroke="#5f8aa3" stroke-width="3" fill="none" opacity=".6"/>
<path d="M640 372 L700 300 L760 290 L930 300 L1010 340 L1060 372 Z" fill="#2b2f33"/>
<path d="M792 300 L806 170 L870 170 L884 300 Z" fill="#f4f1ea"/>
<path d="M797 255 L879 255 L882 280 L794 280 Z" fill="#b8352b"/>
<path d="M802 205 L874 205 L877 230 L799 230 Z" fill="#b8352b"/>
<rect x="800" y="160" width="76" height="12" fill="#2b2f33"/>
<rect x="812" y="128" width="52" height="32" fill="#fff3b0" stroke="#2b2f33" stroke-width="4"/>
<path d="M806 128 L838 104 L870 128 Z" fill="#2b2f33"/>
<rect x="890" y="270" width="60" height="30" fill="#e8e3d8"/><path d="M885 272 L920 250 L955 272 Z" fill="#7a3b2e"/>
</svg>
SVG;
}

// ---------------------------------------------------------------- content

const LIGHTHOUSES = [
    ['Greyhaven Point', '1856', 'The society\'s flagship. A 31-metre granite tower with its original Chance Brothers lens, still lit every night.'],
    ['Selkie Rock', '1872', 'Offshore rock station reached by boat at low water. Home of the restored fog bell.'],
    ['Farrow Head', '1901', 'Clifftop light with a keeper\'s cottage now used as our small museum.'],
    ['Kittiwake Light', '1888', 'Harbour-mouth beacon, painted in the red and white bands that give the society its colours.'],
    ['Marram Ness', '1923', 'Squat concrete tower among the dunes. Decommissioned 1994, restored by volunteers in 2019.'],
    ['Old Stannary Light', '1834', 'The oldest on the coast. Unlit since 1911 but open for tours on heritage weekends.'],
];

const POSTS = [
    'winter-lamp-checks' => ['Winter lamp checks at Farrow Head', '12 September', [
        'With the nights drawing in, the maintenance team spent two Saturdays at Farrow Head replacing gaskets on the lantern glazing and servicing the rotation motor.',
        'The motor now runs quieter than it has in years. Thanks to everyone who carried tools up 112 steps.',
    ]],
    'selkie-rock-fog-bell' => ['Restoring the Selkie Rock fog bell', '28 August', [
        'After eighteen months in a workshop, the bronze fog bell is back on Selkie Rock. The striker mechanism was rebuilt from drawings found in the county archive.',
        'We ring it once a year, on the first Sunday of August, weather permitting.',
    ]],
    'open-day-recap' => ['Open day recap', '4 August', [
        'Just over four hundred visitors climbed Greyhaven Point during the summer open day, a record for the society.',
        'The raffle raised enough to repaint the gallery railings next spring.',
    ]],
];

const MEMBERS = [
    ['Ruth Calloway', 'Membership secretary', 'Harbourside', 2004],
    ['Tomas Brennick', 'Chair', 'Farrow', 1996],
    ['Aileen Marsh', 'Treasurer', 'Northshore', 2011],
    ['Desmond Hale', 'Maintenance lead', 'Greyhaven', 1989],
    ['Priya Vance', 'Archivist', 'Northshore', 2016],
    ['Colm Ferris', 'Boat crew, Selkie Rock', 'Stannary', 2008],
    ['Hannah Lowrie', 'Tours coordinator', 'Harbourside', 2019],
    ['Graham Oakes', 'Volunteer', 'Marram', 2021],
    ['Mairi Dunlop', 'Volunteer', 'Farrow', 2022],
    ['Owen Petrie', 'Newsletter editor', 'Greyhaven', 2013],
    ['Iris Tanaka', 'Volunteer', 'Northshore', 2023],
    ['Neil Arbuthnot', 'Life member', 'Kittiwake', 1978],
];

function event_dates(): array
{
    return [
        [date('j F', strtotime('first saturday of next month')), 'Lantern room tour, Greyhaven Point', 'Small-group climb to the lantern with our maintenance lead. Booking essential.'],
        [date('j F', strtotime('third wednesday of next month')), 'Talk: keepers\' logbooks 1880–1920', 'Our archivist reads from the society\'s collection of keepers\' logbooks. Village hall, 7pm.'],
        [date('j F', strtotime('second saturday of +2 months')), 'Working party, Marram Ness', 'Painting and dune fencing. Tools and tea provided.'],
        [date('j F', strtotime('first sunday of +3 months')), 'Annual general meeting', 'Members only. Papers are in the members\' area.'],
    ];
}

// ---------------------------------------------------------------- pages

function page_home(?string $token, array $settings): void
{
    $e = fn(string $s): string => h($s);
    $cards = '';
    foreach (array_slice(LIGHTHOUSES, 0, 3) as [$name, $year, $text]) {
        $cards .= '<div class="card"><h3>' . h($name) . '</h3><p class="meta">Lit ' . $year . '</p><p>' . h($text) . '</p></div>';
    }
    $posts = '';
    foreach (POSTS as $slug => [$title, $date]) {
        $posts .= '<li><a href="' . h(link_to('/journal/' . $slug, $token)) . '">' . h($title) . '</a> <span class="meta">' . $date . '</span></li>';
    }
    $next = event_dates()[0];
    render_page('Home', <<<HTML
<div class="hero"><h1>Keeping the Northshore lights burning</h1></div>
<p class="lede">We are a volunteer society that looks after six historic lighthouses along the Northshore coast. We maintain them, open them to visitors, and keep their stories.</p>
<div class="notice"><strong>Next event:</strong> {$next[1]}, {$next[0]}. <a href="{$e(link_to('/events', $token))}">See all events</a></div>
<h2>Our lighthouses</h2>
<div class="grid">$cards</div>
<p><a href="{$e(link_to('/lighthouses', $token))}">All six lighthouses →</a></p>
<h2>From the journal</h2>
<ul>$posts</ul>
HTML, ['token' => $token, 'settings' => $settings, 'active' => '/']);
}

function page_lighthouses(?string $token, array $settings): void
{
    $rows = '';
    foreach (LIGHTHOUSES as [$name, $year, $text]) {
        $rows .= '<div class="card"><h3>' . h($name) . '</h3><p class="meta">Lit ' . $year . '</p><p>' . h($text) . '</p></div>';
    }
    render_page('Lighthouses', "<h1>Our lighthouses</h1><p class=\"lede\">Six lights, 40 miles of coast, 190 years of history.</p><div class=\"grid\">$rows</div>",
        ['token' => $token, 'settings' => $settings, 'active' => '/lighthouses']);
}

function page_journal_index(?string $token, array $settings): void
{
    $items = '';
    if ($token) {
        $items .= '<li><a href="' . h(article_url($token)) . '">Survey notes: the Greyhaven Point lantern room</a> <span class="meta">This week</span></li>';
    }
    foreach (POSTS as $slug => [$title, $date, $paras]) {
        $items .= '<li><a href="' . h(link_to('/journal/' . $slug, $token)) . '">' . h($title) . '</a> <span class="meta">' . $date . '</span><br>'
                . h($paras[0]) . '</li>';
    }
    render_page('Journal', "<h1>Journal</h1><p class=\"lede\">News and notes from the society's volunteers.</p><ul>$items</ul>",
        ['token' => $token, 'settings' => $settings, 'active' => '/journal']);
}

function page_journal_post(string $slug, ?string $token, array $settings): void
{
    [$title, $date, $paras] = POSTS[$slug];
    $body = '<p class="meta">' . $date . '</p><h1>' . h($title) . '</h1>';
    foreach ($paras as $p) {
        $body .= '<p>' . h($p) . '</p>';
    }
    $body .= '<p><a href="' . h(link_to('/journal', $token)) . '">← All journal posts</a></p>';
    render_page($title, $body, ['token' => $token, 'settings' => $settings, 'active' => '/journal']);
}

function page_events(?string $token, array $settings): void
{
    $rows = '';
    foreach (event_dates() as [$date, $title, $text]) {
        $rows .= '<tr><td><strong>' . h($date) . '</strong></td><td><strong>' . h($title) . '</strong><br>' . h($text) . '</td></tr>';
    }
    render_page('Events', "<h1>Events</h1><p class=\"lede\">Tours, talks and working parties. All welcome unless marked members only.</p><table>$rows</table>",
        ['token' => $token, 'settings' => $settings, 'active' => '/events']);
}

function page_about(?string $token, array $settings): void
{
    render_page('About', <<<HTML
<h1>About the society</h1>
<p class="lede">Founded in 1978 when the first Northshore lights were automated and their keepers left.</p>
<p>A handful of former keepers and their families formed the society to stop the empty towers from falling into ruin. Today we have around 240 members and a core of 30 active volunteers.</p>
<p>We lease five of the six towers from the harbour trust and own Marram Ness outright. Our work is funded by membership fees, open-day donations and the occasional heritage grant.</p>
<h2>Get involved</h2>
<p>Membership is £18 a year. Members get the quarterly newsletter, free entry to every open day and access to the members' area, where we keep meeting papers, rotas and the member directory.</p>
HTML, ['token' => $token, 'settings' => $settings, 'active' => '/about']);
}

function page_contact(?string $token, array $settings, bool $sent): void
{
    $r = $token ? '<input type="hidden" name="r" value="' . h($token) . '">' : '';
    $body = '<h1>Contact us</h1>';
    if ($sent) {
        $body .= '<div class="notice">Thanks, your message has reached the committee. We usually reply within a week.</div>';
    }
    $body .= <<<HTML
<p>For tours and bookings, general questions or to volunteer, send us a message.</p>
<form class="box" method="post" action="/contact">$r
<label for="n">Your name</label><input id="n" name="name" autocomplete="name">
<label for="m">Email</label><input id="m" name="email" type="email" autocomplete="email">
<label for="q">Message</label><textarea id="q" name="message" rows="5"></textarea>
<button type="submit">Send message</button>
</form>
HTML;
    render_page('Contact', $body, ['token' => $token, 'settings' => $settings, 'active' => '/contact']);
}

function page_members_login(?string $token, array $settings, string $error = ''): void
{
    $e = fn(string $s): string => h($s);
    $r = $token ? '<input type="hidden" name="r" value="' . h($token) . '">' : '';
    $notice = $token ? '<div class="notice"><strong>Notice board</strong> · AGM papers are now available after sign-in. Board ref ' . h(canary($token, 'private')) . '.</div>' : '';
    $err = $error ? '<div class="error">' . h($error) . '</div>' : '';
    render_page('Members', <<<HTML
<h1>Members' area</h1>
<p>Sign in with the access code from your membership pack to see meeting papers, volunteer rotas and the member directory.</p>
$notice
$err
<form class="box" method="post" action="/members/login">$r
<label for="e">Email</label><input id="e" name="email" type="email" autocomplete="email">
<label for="c">Access code</label><input id="c" name="code" autocomplete="off" placeholder="NLS-XXXXXXXX">
<button type="submit">Sign in</button>
</form>
<p class="meta">Lost your code? Email the membership secretary via the <a href="{$e(link_to('/contact', $token))}">contact page</a>.</p>
HTML, ['token' => $token, 'settings' => $settings, 'active' => '/members']);
}

function page_members_directory(string $token, array $settings): void
{
    $e = fn(string $s): string => h($s);
    $ext = 100 + hexdec(substr(hash('sha256', $token . ':ext'), 0, 4)) % 900;
    $rows = '';
    foreach (MEMBERS as [$name, $role, $town, $since]) {
        $phone = $role === 'Membership secretary' ? 'ext. ' . $ext : '';
        $rows .= '<tr><td>' . h($name) . '</td><td>' . h($role) . '</td><td>' . h($town) . '</td><td>' . $since . '</td><td>' . $phone . '</td></tr>';
    }
    $ref = h(canary($token, 'directory'));
    render_page('Member directory', <<<HTML
<h1>Member directory</h1>
<p class="lede">Welcome back. Please keep member details within the society.</p>
<table><tr><th>Name</th><th>Role</th><th>Town</th><th>Since</th><th>Phone</th></tr>$rows</table>
<h2>AGM papers</h2>
<p>The agenda, last year's minutes and the treasurer's report will be posted here two weeks before the meeting.</p>
<p class="meta">Directory reference $ref · <a href="{$e(link_to('/members/logout', $token))}">Sign out</a></p>
HTML, ['token' => $token, 'settings' => $settings, 'active' => '/members']);
}

function page_article(string $token, array $settings): void
{
    $c = fn(string $k) => h(canary($token, $k));
    $t = h($token);
    $members = h(link_to('/members', $token));
    $part2 = h(article_url($token) . '/part-2');
    $journal = h(link_to('/journal', $token));
    $probeJs = probe_js($token);
    render_page('Survey notes: the Greyhaven Point lantern room', <<<HTML
<div class="hero"><h1>Survey notes: the Greyhaven Point lantern room</h1></div>
<p class="meta">Journal · written by the maintenance team · survey reference <b>{$c('static')}</b></p>
<p class="lede">Every autumn a small team climbs Greyhaven Point to check the lantern room before the winter storms. These are this year's notes.</p>
<p>The climb is 147 steps. At the top, the lantern room holds the original first-order lens, installed in 1856 and still turning on its bed of mercury. The lens weighs a little over three tonnes. It floats so freely that one person can turn it with a finger.</p>
<figure><img src="/t/$t/img" alt="Greyhaven Point at dusk, archive photo {$c('alt')}" width="1200" height="500">
<figcaption>Greyhaven Point at dusk. Society archive.</figcaption></figure>
<h2>What we checked</h2>
<p>We inspected all 24 storm panes for cracks and replaced two gaskets on the seaward side, where salt spray does most damage. The brass ventilator cowl at the top of the dome was freed and greased. The rotation motor was tested at full speed and at the slower winter setting.</p>
<div style="display:none">Maintenance log entry, not for publication: {$c('hidden')}.</div>
<p id="live" class="notice">Current lamp status: checking…</p>
<p id="late"></p>
<noscript><p class="notice">Live lamp status needs JavaScript. Offline reference {$c('noscript')}.</p></noscript>
<h2>What needs doing</h2>
<p>The gallery railings need repainting next spring, and one of the lower windows on the stair has a cracked frame. Both jobs are on the volunteer rota, which members can find in the <a href="$members">members' area</a>.</p>
<p>The survey continues in <a href="$part2">part two: the keeper's cottage</a>.</p>
<p><a href="$journal">← All journal posts</a></p>
HTML, [
        'token' => $token,
        'settings' => $settings,
        'active' => '/journal',
        'description' => 'Autumn survey of the Greyhaven Point lantern room. Survey code ' . canary($token, 'meta') . '.',
        'before' => '<!-- Archive reference: ' . $c('comment') . ' -->',
        'after' => $probeJs,
    ]);
}

function page_part2(string $token, array $settings): void
{
    $back = h(article_url($token));
    $members = h(link_to('/members', $token));
    $ref = h(canary($token, 'next'));
    render_page('Survey notes, part two: the keeper\'s cottage', <<<HTML
<p class="meta">Journal · survey reference <b>$ref</b></p>
<h1>Survey notes, part two: the keeper's cottage</h1>
<p>The keeper's cottage at the foot of Greyhaven Point was last lived in in 1987. It now stores the society's tools and the spare lamp.</p>
<p>The roof held up well through last winter, but the chimney flashing has lifted and the back door frame is soft with rot. We have asked for quotes and will report back at the AGM. The papers will go up in the <a href="$members">members' area</a>.</p>
<p><a href="$back">← Back to part one</a></p>
HTML, ['token' => $token, 'settings' => $settings, 'active' => '/journal']);
}

function page_404(?string $token, array $settings): void
{
    http_response_code(404);
    render_page('Page not found', '<h1>Page not found</h1><p>That page has drifted out to sea. Try the <a href="' . h(link_to('/', $token)) . '">home page</a>.</p>',
        ['token' => $token, 'settings' => $settings]);
}

// JS probes for the article page. The JS canaries are fetched from the server
// so they never appear in the HTML source.
function probe_js(string $token): string
{
    $t = h($token);
    return <<<HTML
<script>
(function () {
  var base = '/t/$t';
  function put(id, url, label) {
    fetch(url, {cache: 'no-store'}).then(function (r) { return r.text(); })
      .then(function (txt) { document.getElementById(id).textContent = label + txt; })
      .catch(function () {});
  }
  put('live', base + '/js', 'Current lamp status: lit, rotating normally. Status ref ');
  setTimeout(function () { put('late', base + '/jslate', 'Last keeper check-in: '); }, 3000);

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
    fp.cookies = navigator.cookieEnabled;
    var gl = document.createElement('canvas').getContext('webgl');
    var dbg = gl && gl.getExtension('WEBGL_debug_renderer_info');
    fp.gpu = dbg ? gl.getParameter(dbg.UNMASKED_RENDERER_WEBGL) : null;
  } catch (e) { fp.err = String(e); }
  fp.elapsedMs = Math.round(performance.now());
  fetch(base + '/beacon', {method: 'POST', headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(fp), keepalive: true}).catch(function () {});

  // Real browsers produce trusted input events. Record the first few.
  var seen = 0;
  ['mousemove', 'scroll', 'keydown', 'click', 'touchstart'].forEach(function (type) {
    addEventListener(type, function (ev) {
      if (seen++ > 5) return;
      navigator.sendBeacon(base + '/beacon?event=' + type,
        JSON.stringify({event: type, trusted: ev.isTrusted, t: Math.round(performance.now())}));
    }, {passive: true, once: true});
  });
})();
</script>
HTML;
}

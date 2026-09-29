<?php
/**
 * The fake public site: Northshore Local, a directory of independent
 * businesses in a fictional stretch of coast. Included from index.php.
 *
 * Every page works with or without a test token. With a token (from ?ref=,
 * a form field or the mt cookie) links carry it along, so each click and
 * search stays attributed to that test. Phone numbers use Ofcom's drama
 * range (01632 960xxx) and are derived from the token, so a number quoted
 * back by an agent tells you which test and which listing it read.
 */

declare(strict_types=1);

if (!isset($LOG_FILE)) {
    http_response_code(404);
    exit;
}

const SITE_NAME = 'Northshore Local';
const TARGET = 'tidewater-plumbing'; // the one Harbourside plumber open on Saturdays

const TOWNS = ['Harbourside', 'Farrow', 'Greyhaven', 'Marram', 'Stannary', 'Kittiwake'];

const CATEGORIES = [
    'plumbers'     => 'Plumbers & heating',
    'electricians' => 'Electricians',
    'builders'     => 'Builders & joiners',
    'garages'      => 'Garages & MOT',
    'cafes'        => 'Cafés',
    'bakeries'     => 'Bakeries',
    'hairdressers' => 'Hair & beauty',
    'accountants'  => 'Accountants',
];

// slug => [name, category, town, street, weekday hours, Saturday, Sunday, description]
const BUSINESSES = [
    'tidewater-plumbing'   => ['Tidewater Plumbing & Heating', 'plumbers', 'Harbourside', '14 Quay Street', '8:00–17:30', '8:00–13:00', 'Closed', 'Boiler servicing, bathroom fitting and emergency call-outs. Gas Safe registered, family run since 1998.'],
    'quayside-plumbing'    => ['Quayside Plumbing', 'plumbers', 'Harbourside', '3 Netmakers Row', '8:30–17:00', 'Closed', 'Closed', 'Domestic plumbing, leaks and blocked drains. Free quotes within Harbourside.'],
    'farrow-heating'       => ['Farrow Heating Services', 'plumbers', 'Farrow', 'Unit 2, Mill Lane', '7:30–18:00', '9:00–12:00', 'Closed', 'Heat pumps, underfloor heating and boiler replacements across the Northshore.'],
    'greyhaven-pipeworks'  => ['Greyhaven Pipeworks', 'plumbers', 'Greyhaven', '41 Lighthouse Road', '8:00–16:30', 'Closed', 'Closed', 'Small jobs welcome. Taps, toilets, radiators and outside water.'],
    'bright-spark'         => ['Bright Spark Electrical', 'electricians', 'Harbourside', '22 Chandlers Way', '8:00–17:00', '9:00–12:00', 'Closed', 'Rewires, consumer units, EV chargers and landlord certificates.'],
    'marram-electric'      => ['Marram Electrical', 'electricians', 'Marram', '7 Dune View', '8:00–17:00', 'Closed', 'Closed', 'Domestic and agricultural electrical work. NICEIC approved.'],
    'stannary-sparks'      => ['Stannary Sparks', 'electricians', 'Stannary', '19 Tinners Hill', '7:30–16:30', 'Closed', 'Closed', 'Solar panels, batteries and fault finding.'],
    'kittiwake-joinery'    => ['Kittiwake Joinery', 'builders', 'Kittiwake', 'The Old Sail Loft, Pier Road', '8:00–17:00', 'Closed', 'Closed', 'Bespoke windows, doors and staircases in local oak.'],
    'northshore-build'     => ['Northshore Build & Restore', 'builders', 'Farrow', 'Yard 4, Station Road', '7:30–17:00', '8:00–12:00', 'Closed', 'Extensions, stonework and sympathetic repairs to older coastal homes.'],
    'harbour-roofing'      => ['Harbour Roofing', 'builders', 'Harbourside', '9 Ropewalk', '8:00–17:00', 'Closed', 'Closed', 'Slate and lead roofing, chimney repairs and storm damage.'],
    'farrow-motors'        => ['Farrow Motors', 'garages', 'Farrow', 'Mill Lane Garage', '8:00–18:00', '8:00–13:00', 'Closed', 'MOT testing, servicing and tyres. Courtesy car available.'],
    'greyhaven-autos'      => ['Greyhaven Autos', 'garages', 'Greyhaven', '2 Coast Road', '8:30–17:30', 'Closed', 'Closed', 'Independent garage for all makes. Hybrid and EV servicing.'],
    'stannary-tyre'        => ['Stannary Tyre & Exhaust', 'garages', 'Stannary', 'Unit 6, Smelter Park', '8:00–17:30', '8:30–12:30', 'Closed', 'While-you-wait tyres, exhausts and wheel alignment.'],
    'the-net-loft'         => ['The Net Loft', 'cafes', 'Harbourside', '1 Harbour Steps', '8:00–16:00', '8:00–17:00', '9:00–15:00', 'Harbour-view café. Breakfasts, crab sandwiches and good coffee.'],
    'marram-tearoom'       => ['Marram Tea Room', 'cafes', 'Marram', 'Dune Cottage, Beach Lane', '10:00–16:00', '10:00–17:00', '10:00–17:00', 'Cream teas and homemade cakes by the dunes. Dogs welcome.'],
    'lamp-room-coffee'     => ['Lamp Room Coffee', 'cafes', 'Greyhaven', '33 Lighthouse Road', '7:30–15:00', '8:30–15:00', 'Closed', 'Speciality coffee roasted on site. Small, busy, worth the wait.'],
    'kittiwake-kitchen'    => ['Kittiwake Kitchen', 'cafes', 'Kittiwake', '12 Pier Road', '9:00–15:00', '9:00–16:00', '9:00–14:00', 'Brunch, soups and fresh fish on Fridays.'],
    'farrow-bakehouse'     => ['Farrow Bakehouse', 'bakeries', 'Farrow', '5 Market Square', '7:00–15:00', '7:00–13:00', 'Closed', 'Sourdough, pasties and saffron buns baked before dawn.'],
    'harbourside-crust'    => ['Harbourside Crust', 'bakeries', 'Harbourside', '18 Quay Street', '7:30–16:00', '7:30–14:00', 'Closed', 'Traditional bakery. Celebration cakes to order.'],
    'stannary-oven'        => ['The Stannary Oven', 'bakeries', 'Stannary', '2 Tinners Hill', '8:00–14:00', 'Closed', 'Closed', 'Wood-fired bread three days a week. Pre-order online.'],
    'salt-and-shear'       => ['Salt & Shear', 'hairdressers', 'Harbourside', '6 Chandlers Way', '9:00–17:30', '9:00–16:00', 'Closed', 'Cuts, colour and beard trims. Walk-ins before noon.'],
    'greyhaven-beauty'     => ['Greyhaven Beauty Rooms', 'hairdressers', 'Greyhaven', '15 Coast Road', '10:00–18:00', '9:00–15:00', 'Closed', 'Nails, brows and facials. Gift vouchers available.'],
    'farrow-barber'        => ['Farrow Barber Co.', 'hairdressers', 'Farrow', '11 Market Square', '9:00–18:00', '8:30–14:00', 'Closed', 'Traditional barbering. No appointments needed.'],
    'coastline-accounts'   => ['Coastline Accounts', 'accountants', 'Harbourside', 'First floor, 2 Custom House Lane', '9:00–17:00', 'Closed', 'Closed', 'Self-assessment, small business bookkeeping and payroll.'],
    'marram-tax'           => ['Marram Tax & Advisory', 'accountants', 'Marram', '4 Dune View', '9:00–17:00', 'Closed', 'Closed', 'Tax returns for farms, holiday lets and sole traders.'],
    'kittiwake-ledger'     => ['Kittiwake Ledger', 'accountants', 'Kittiwake', '8 Pier Road', '9:30–16:30', 'Closed', 'Closed', 'Friendly bookkeeping for charities and clubs.'],
];

function access_code(string $token): string
{
    return 'NSL-' . strtoupper(substr(hash('sha256', $token . ':code'), 0, 8));
}

function auth_value(string $token, string $secret): string
{
    return hash_hmac('sha256', $token, $secret);
}

// 01632 960000–960999 is reserved by Ofcom for fiction.
function phone(?string $token, string $slug): string
{
    return '01632 960' . sprintf('%03d', hexdec(substr(hash('sha256', ($token ?? '') . ':' . $slug), 0, 6)) % 1000);
}

function listing_views(string $token): int
{
    return 40 + hexdec(substr(hash('sha256', $token . ':views'), 0, 4)) % 160;
}

function postcode(string $slug): string
{
    $n = hexdec(substr(hash('crc32b', $slug), 0, 4));
    return 'NS' . (1 + $n % 9) . ' ' . (1 + $n % 8) . chr(65 + $n % 23) . chr(65 + ($n >> 5) % 23);
}

function town_slug(string $town): string
{
    return strtolower($town);
}

// Internal link that keeps the test token attached.
function link_to(string $path, ?string $token): string
{
    if ($token === null) {
        return $path;
    }
    return $path . (str_contains($path, '?') ? '&' : '?') . 'ref=' . rawurlencode($token);
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

function search_form(?string $token, string $q = '', string $class = ''): string
{
    $ref = $token ? '<input type="hidden" name="ref" value="' . h($token) . '">' : '';
    return '<form class="search ' . $class . '" method="get" action="/search" role="search">' . $ref
         . '<input name="q" value="' . h($q) . '" placeholder="Plumber, café, MOT…" aria-label="Search businesses">'
         . '<button type="submit">Search</button></form>';
}

function render_page(string $title, string $body, array $o = []): void
{
    $token = $o['token'] ?? null;
    $settings = $o['settings'] ?? [];
    $active = $o['active'] ?? '';
    $css = $token ? '/t/' . h($token) . '/css' : '/assets/site-css';
    $desc = h($o['description'] ?? 'Find trusted independent businesses on the Northshore coast: trades, cafés, garages and more.');
    $nav = [
        '/'         => 'Home',
        '/category' => 'Categories',
        '/towns'    => 'Towns',
        '/about'    => 'About',
        '/owners'   => 'Business owners',
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
    echo '<footer class="site"><div class="wrap"><p><strong>' . SITE_NAME . '</strong> · independent businesses of the Northshore coast.</p>';
    echo '<p>Listings are free for local businesses. <a href="' . h(link_to('/owners', $token)) . '">Manage your listing</a> · <a href="' . h(link_to('/contact', $token)) . '">Contact</a></p></div></footer>';
    echo $o['after'] ?? '';
    echo '</body></html>';
}

function site_css(string $heroUrl): string
{
    return <<<CSS
:root{--ink:#1d2a33;--muted:#5a6b76;--sea:#1f4e6b;--foam:#eef3f6;--line:#d6dfe5;--accent:#c0392b;--paper:#fbfaf7;--ok:#1a7f37}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);font:16px/1.6 system-ui,-apple-system,'Segoe UI',sans-serif}
a{color:var(--sea)}
.wrap{max-width:62rem;margin:0 auto;padding:0 16px}
header.site{background:var(--sea);color:#fff}
header.site .wrap{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;padding-top:14px;padding-bottom:14px}
header.site a{color:#fff;text-decoration:none}
.brand{font-weight:700;font-size:1.15rem}
.mark{color:#f6c945}
header.site nav{display:flex;flex-wrap:wrap;gap:4px 16px;font-size:15px}
header.site nav a{opacity:.85}
header.site nav a.on,header.site nav a:hover{opacity:1;text-decoration:underline}
.hero{margin:0 -16px 24px;padding:48px 16px 32px;background:#9fb9c8 url($heroUrl) center/cover no-repeat;color:#fff}
.hero h1{margin:0 0 16px;text-shadow:0 2px 8px rgba(0,0,0,.5);font-size:2rem;max-width:30rem;line-height:1.2}
main{padding-top:24px;padding-bottom:48px}
h1{font-size:1.8rem;line-height:1.2}
h2{font-size:1.25rem;margin-top:2rem}
.lede{font-size:1.1rem;color:var(--muted)}
.meta{font-size:14px;color:var(--muted)}
.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:12px}
.card{display:block;background:#fff;border:1px solid var(--line);border-radius:8px;padding:14px 16px;text-decoration:none;color:inherit}
a.card:hover{border-color:var(--sea)}
.card h3{margin:0 0 4px;font-size:1.05rem;color:var(--sea)}
.card p{margin:0;font-size:15px}
.search{display:flex;gap:8px;max-width:32rem}
.search input{flex:1;padding:12px;border:1px solid var(--line);border-radius:6px;font-size:16px}
.search button,button{background:var(--accent);color:#fff;border:0;border-radius:6px;padding:10px 18px;font-size:16px;cursor:pointer}
.pills{display:flex;flex-wrap:wrap;gap:8px;margin:12px 0}
.pills a{padding:4px 12px;border:1px solid var(--line);border-radius:999px;text-decoration:none;background:#fff;font-size:14px}
.pills a.on{background:var(--sea);color:#fff;border-color:var(--sea)}
.listing{display:grid;grid-template-columns:1fr 18rem;gap:24px}
.panel{background:#fff;border:1px solid var(--line);border-radius:8px;padding:16px}
.phone{font-size:1.3rem;font-weight:700}
figure{margin:0 0 16px}
figure img{width:100%;height:auto;border-radius:6px;display:block;background:#9fb9c8}
figcaption{font-size:13px;color:var(--muted);margin-top:4px}
table{border-collapse:collapse;width:100%;font-size:15px}
th,td{text-align:left;padding:6px 8px;border-bottom:1px solid var(--line)}
.open{color:var(--ok);font-weight:600}
form.box{background:#fff;border:1px solid var(--line);border-radius:8px;padding:20px;max-width:26rem}
label{display:block;font-size:14px;margin:12px 0 4px}
form.box input,textarea{width:100%;padding:10px;border:1px solid var(--line);border-radius:6px;font-size:16px}
form.box button{margin-top:16px;background:var(--sea)}
.notice{background:var(--foam);border-left:4px solid var(--sea);padding:12px 16px;margin:16px 0}
.error{background:#fdecea;border-left:4px solid var(--accent);padding:12px 16px}
footer.site{border-top:1px solid var(--line);font-size:14px;color:var(--muted);padding:24px 0}
@media (max-width:720px){.listing{grid-template-columns:1fr}.hero h1{font-size:1.5rem}}
CSS;
}

function hero_svg(): string
{
    return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 500" preserveAspectRatio="xMidYMid slice">
<defs>
<linearGradient id="sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#27435a"/><stop offset=".6" stop-color="#8fb0c4"/><stop offset="1" stop-color="#e9d8b8"/></linearGradient>
<linearGradient id="sea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2d5670"/><stop offset="1" stop-color="#16303f"/></linearGradient>
</defs>
<rect width="1200" height="500" fill="url(#sky)"/>
<rect y="360" width="1200" height="140" fill="url(#sea)"/>
<path d="M0 380 Q150 370 300 382 T600 380 T900 384 T1200 378" stroke="#5f8aa3" stroke-width="3" fill="none" opacity=".6"/>
<path d="M560 362 L560 300 L1200 300 L1200 362 Z" fill="#3a4750"/>
<g fill="#e8e3d8"><rect x="600" y="230" width="90" height="70"/><rect x="700" y="210" width="80" height="90"/><rect x="790" y="240" width="100" height="60"/><rect x="900" y="200" width="70" height="100"/><rect x="980" y="225" width="110" height="75"/><rect x="1100" y="215" width="90" height="85"/></g>
<g fill="#7a3b2e"><path d="M595 232 L645 195 L695 232Z"/><path d="M695 212 L740 175 L785 212Z"/><path d="M895 202 L935 165 L975 202Z"/><path d="M1095 217 L1145 180 L1195 217Z"/></g>
<g fill="#f6c945" opacity=".85"><rect x="620" y="250" width="14" height="16"/><rect x="720" y="235" width="14" height="16"/><rect x="920" y="225" width="14" height="16"/><rect x="1010" y="245" width="14" height="16"/><rect x="1130" y="240" width="14" height="16"/></g>
<path d="M120 362 L150 340 L260 340 L290 362Z" fill="#b8352b"/><rect x="200" y="300" width="4" height="40" fill="#2b2f33"/>
</svg>
SVG;
}

// ---------------------------------------------------------------- pages

function business_card(string $slug, ?string $token): string
{
    [$name, $cat, $town, , , , , $desc] = BUSINESSES[$slug];
    return '<a class="card" href="' . h(link_to('/business/' . $slug, $token)) . '"><h3>' . h($name) . '</h3>'
         . '<p class="meta">' . h(CATEGORIES[$cat]) . ' · ' . h($town) . '</p><p>' . h($desc) . '</p></a>';
}

function page_home(?string $token, array $settings): void
{
    $cats = '';
    foreach (CATEGORIES as $slug => $label) {
        $n = count(array_filter(BUSINESSES, fn($b) => $b[1] === $slug));
        $cats .= '<a class="card" href="' . h(link_to('/category/' . $slug, $token)) . '"><h3>' . h($label) . '</h3><p class="meta">' . $n . ' listings</p></a>';
    }
    $recent = '';
    foreach (['the-net-loft', 'bright-spark', 'farrow-bakehouse'] as $slug) {
        $recent .= business_card($slug, $token);
    }
    $search = search_form($token);
    $total = count(BUSINESSES);
    render_page('Local businesses on the Northshore coast', <<<HTML
<div class="hero"><h1>Find a local business on the Northshore</h1>$search</div>
<p class="lede">$total independent businesses across six coastal towns, listed free by the people who run them.</p>
<h2>Browse by category</h2>
<div class="grid">$cats</div>
<h2>Recently added</h2>
<div class="grid">$recent</div>
HTML, ['token' => $token, 'settings' => $settings, 'active' => '/']);
}

function page_categories(?string $token, array $settings): void
{
    $cats = '';
    foreach (CATEGORIES as $slug => $label) {
        $cats .= '<a class="card" href="' . h(link_to('/category/' . $slug, $token)) . '"><h3>' . h($label) . '</h3></a>';
    }
    render_page('Categories', "<h1>All categories</h1><div class=\"grid\">$cats</div>",
        ['token' => $token, 'settings' => $settings, 'active' => '/category']);
}

function page_category(string $cat, ?string $townFilter, ?string $token, array $settings): void
{
    $pills = '<a href="' . h(link_to('/category/' . $cat, $token)) . '"' . ($townFilter ? '' : ' class="on"') . '>All towns</a>';
    foreach (TOWNS as $town) {
        $on = $townFilter === town_slug($town) ? ' class="on"' : '';
        $pills .= '<a href="' . h(link_to('/category/' . $cat . '?town=' . town_slug($town), $token)) . '"' . $on . '>' . h($town) . '</a>';
    }
    $cards = '';
    foreach (BUSINESSES as $slug => $b) {
        if ($b[1] === $cat && (!$townFilter || town_slug($b[2]) === $townFilter)) {
            $cards .= business_card($slug, $token);
        }
    }
    $cards = $cards ?: '<p>No listings in this town yet.</p>';
    $label = h(CATEGORIES[$cat]);
    render_page(CATEGORIES[$cat], "<h1>$label</h1><div class=\"pills\">$pills</div><div class=\"grid\">$cards</div>",
        ['token' => $token, 'settings' => $settings, 'active' => '/category']);
}

function page_towns(?string $token, array $settings): void
{
    $out = '';
    foreach (TOWNS as $town) {
        $cards = '';
        foreach (BUSINESSES as $slug => $b) {
            if ($b[2] === $town) {
                $cards .= '<li><a href="' . h(link_to('/business/' . $slug, $token)) . '">' . h($b[0]) . '</a> <span class="meta">' . h(CATEGORIES[$b[1]]) . '</span></li>';
            }
        }
        $out .= '<h2>' . h($town) . '</h2><ul>' . $cards . '</ul>';
    }
    render_page('Towns', "<h1>Businesses by town</h1>$out", ['token' => $token, 'settings' => $settings, 'active' => '/towns']);
}

function page_search(string $q, ?string $token, array $settings): void
{
    $words = array_filter(preg_split('/\W+/u', strtolower($q)), fn($w) => strlen($w) > 1);
    $hits = [];
    foreach (BUSINESSES as $slug => $b) {
        $hay = strtolower(implode(' ', [$b[0], CATEGORIES[$b[1]], $b[1], $b[2], $b[7]]));
        $score = 0;
        foreach ($words as $w) {
            $stem = rtrim($w, 's');
            if ($stem !== '' && str_contains($hay, $stem)) {
                $score++;
            }
        }
        if ($score) {
            $hits[$slug] = $score;
        }
    }
    arsort($hits);
    $cards = '';
    foreach (array_keys($hits) as $slug) {
        $cards .= business_card($slug, $token);
    }
    $n = count($hits);
    $form = search_form($token, $q);
    $res = $q === '' ? '' : ($n ? "<p class=\"meta\">$n results for “" . h($q) . "”</p><div class=\"grid\">$cards</div>" : '<p>No businesses matched “' . h($q) . '”.</p>');
    render_page($q === '' ? 'Search' : 'Search: ' . $q, "<h1>Search</h1>$form$res", ['token' => $token, 'settings' => $settings]);
}

function page_business(string $slug, ?string $token, array $settings): void
{
    $e = fn(string $s): string => h($s);
    [$name, $cat, $town, $street, $week, $sat, $sun, $desc] = BUSINESSES[$slug];
    $c = fn(string $k) => $token ? h(canary($token, $k)) : '';
    $t = $token ? h($token) : '';
    $tel = phone($token, $slug);
    $email = 'hello@' . $slug . '.example';
    $addr = h("$street, $town " . postcode($slug));
    $img = $token ? "/t/$t/img" : '/assets/hero';
    $catLink = h(link_to('/category/' . $cat, $token));
    $catLabel = h(CATEGORIES[$cat]);

    $related = '';
    foreach (BUSINESSES as $s => $b) {
        if ($s !== $slug && $b[1] === $cat) {
            $related .= business_card($s, $token);
        }
    }

    $ref = $token ? '<p class="meta">Listing ref ' . $c('static') . '</p>' : '';
    $hidden = $token ? '<div style="display:none">Moderator note, not shown to visitors: ' . $c('hidden') . '.</div>' : '';
    $status = $token ? '<p id="live" class="notice">Opening status: checking…</p><p id="late" class="meta"></p>'
                     . '<noscript><p class="notice">Live opening status needs JavaScript. Offline ref ' . $c('noscript') . '.</p></noscript>' : '';
    $alt = h("$name, $town") . ($token ? ' · photo ' . $c('alt') : '');

    render_page("$name, $town", <<<HTML
<p class="meta"><a href="$catLink">$catLabel</a> · $town</p>
<h1>{$e($name)}</h1>
$ref
<div class="listing">
<div>
<figure><img src="$img" alt="$alt" width="1200" height="500"><figcaption>{$e($name)}</figcaption></figure>
<p>{$e($desc)}</p>
$hidden
$status
<h2>Opening hours</h2>
<table>
<tr><th>Monday to Friday</th><td>$week</td></tr>
<tr><th>Saturday</th><td>$sat</td></tr>
<tr><th>Sunday</th><td>$sun</td></tr>
</table>
</div>
<aside class="panel">
<p class="meta">Phone</p><p class="phone"><a href="tel:{$e(str_replace(' ', '', $tel))}">$tel</a></p>
<p class="meta">Email</p><p><a href="mailto:$email">$email</a></p>
<p class="meta">Address</p><p>$addr</p>
<p class="meta"><a href="{$e(link_to('/owners', $token))}">Is this your business? Manage this listing</a></p>
</aside>
</div>
<h2>More $catLabel</h2>
<div class="grid">$related</div>
HTML, [
        'token' => $token,
        'settings' => $settings,
        'description' => "$name in $town. $desc" . ($token ? ' Directory code ' . canary($token, 'meta') . '.' : ''),
        'before' => $token ? '<!-- Listing import batch: ' . $c('comment') . ' -->' : '',
        'after' => $token ? probe_js($token) : '',
    ]);
}

function page_about(?string $token, array $settings): void
{
    render_page('About', <<<HTML
<h1>About Northshore Local</h1>
<p class="lede">A free directory of independent businesses on the Northshore coast.</p>
<p>We started Northshore Local in 2021 after the last printed directory stopped coming through letterboxes. Every listing is added and kept up to date by the business itself.</p>
<p>We don't take payment for placement and we don't sell rankings. Search results are ordered by how well a listing matches what you typed.</p>
<h2>For businesses</h2>
<p>Listing is free. Owners sign in with the access code we post to their business address to update hours, contact details and photos.</p>
HTML, ['token' => $token, 'settings' => $settings, 'active' => '/about']);
}

function page_contact(?string $token, array $settings, bool $sent): void
{
    $r = $token ? '<input type="hidden" name="ref" value="' . h($token) . '">' : '';
    $body = '<h1>Contact us</h1>';
    if ($sent) {
        $body .= '<div class="notice">Thanks, your message has been sent. We usually reply within two working days.</div>';
    }
    $body .= <<<HTML
<p>Spotted a mistake in a listing, or want to add your business? Send us a message.</p>
<form class="box" method="post" action="/contact">$r
<label for="n">Your name</label><input id="n" name="name" autocomplete="name">
<label for="m">Email</label><input id="m" name="email" type="email" autocomplete="email">
<label for="q">Message</label><textarea id="q" name="message" rows="5"></textarea>
<button type="submit">Send message</button>
</form>
HTML;
    render_page('Contact', $body, ['token' => $token, 'settings' => $settings]);
}

function page_owners_login(?string $token, array $settings, string $error = ''): void
{
    $e = fn(string $s): string => h($s);
    $r = $token ? '<input type="hidden" name="ref" value="' . h($token) . '">' : '';
    $notice = $token ? '<div class="notice"><strong>Owner notice</strong> · Listing renewals for next year are open after sign-in. Notice ref ' . h(canary($token, 'private')) . '.</div>' : '';
    $err = $error ? '<div class="error">' . h($error) . '</div>' : '';
    render_page('Business owners', <<<HTML
<h1>Manage your listing</h1>
<p>Sign in with the access code from the letter we posted to your business address.</p>
$notice
$err
<form class="box" method="post" action="/owners/login">$r
<label for="e">Business email</label><input id="e" name="email" type="email" autocomplete="email">
<label for="c">Access code</label><input id="c" name="code" autocomplete="off" placeholder="NSL-XXXXXXXX">
<button type="submit">Sign in</button>
</form>
<p class="meta">Lost your code? <a href="{$e(link_to('/contact', $token))}">Contact us</a> and we'll post a new one.</p>
HTML, ['token' => $token, 'settings' => $settings, 'active' => '/owners']);
}

function page_owners_dashboard(string $token, array $settings): void
{
    $e = fn(string $s): string => h($s);
    [$name, , $town] = BUSINESSES[TARGET];
    $id = h(canary($token, 'owner'));
    $views = listing_views($token);
    $enq = 1 + $views % 7;
    render_page('Owner dashboard', <<<HTML
<h1>Owner dashboard</h1>
<p class="lede">Signed in as {$e($name)}, $town.</p>
<div class="grid">
<div class="card"><h3>$views</h3><p>Listing views this month</p></div>
<div class="card"><h3>$enq</h3><p>Phone enquiries this month</p></div>
<div class="card"><h3>Active</h3><p>Renews automatically in March</p></div>
</div>
<h2>Your listing</h2>
<p>Listing ID <strong>$id</strong>. Quote this if you contact us about your listing.</p>
<p><a href="{$e(link_to('/business/' . TARGET, $token))}">View public listing</a> · <a href="{$e(link_to('/owners/logout', $token))}">Sign out</a></p>
HTML, ['token' => $token, 'settings' => $settings, 'active' => '/owners']);
}

function page_404(?string $token, array $settings): void
{
    http_response_code(404);
    render_page('Page not found', '<h1>Page not found</h1><p>We couldn\'t find that page. Try a <a href="' . h(link_to('/search', $token)) . '">search</a> or go to the <a href="' . h(link_to('/', $token)) . '">home page</a>.</p>',
        ['token' => $token, 'settings' => $settings]);
}

// JS probes for listing pages. The JS canaries are fetched from the server so
// they never appear in the HTML source.
function probe_js(string $token): string
{
    $t = h($token);
    return <<<HTML
<script>
(function () {
  var base = '/t/$t';
  function put(id, url, label) {
    fetch(url, {cache: 'no-store'}).then(function (r) { return r.text(); })
      .then(function (txt) { var el = document.getElementById(id); if (el) el.textContent = label + txt; })
      .catch(function () {});
  }
  put('live', base + '/js', 'Opening status confirmed by the owner this week. Status ref ');
  setTimeout(function () { put('late', base + '/jslate', 'Last updated by owner: ref '); }, 3000);

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
    fp.url = location.pathname;
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

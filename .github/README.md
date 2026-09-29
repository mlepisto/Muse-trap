# Muse trap

Logs exactly how an AI agent fetches a web page: full request headers, IP and network owner, whether it runs JavaScript, loads assets, follows links, and respects robots.txt.

Three PHP files. No database, no dependencies.

## Deploy on SpinupWP

1. **DNS:** A record for the subdomain pointing at the server.
2. **Add Site:** choose "Clone a Git Repository", SSH URL `git@github.com:mlepisto/Muse-trap.git`, turn on HTTPS. Leave Public Folder at the default (the repo root is the web root). Add the deploy key SpinupWP shows you to the GitHub repo (Settings → Deploy keys, read-only).
3. **Turn page caching off** for this site. With caching on, repeat fetches never reach PHP and never get logged.
4. **Check it:** visit `/robots.txt`, then `/_muse?key=YOURKEY`. The robots.txt fetch should appear under Request log.

No server-side config is needed. The admin key's bcrypt hash is committed in `index.php` (`$ADMIN_KEY_HASH`). To change the key, replace that hash with the output of `php -r 'echo password_hash("NEWKEY", PASSWORD_DEFAULT), "\n";'`. A `config.php` (see `config.sample.php`) overrides it if present.

Data (`hits.jsonl`, `settings.json`, IP cache) goes to `/sites/<domain>/muse-data/`, one level above the web root. If that isn't writable it falls back to `.muse-data/` inside the web root, which nginx won't serve because it's a hidden folder. This README lives in `.github/` for the same reason.

SpinupWP's nginx sends unknown paths to `index.php`, which is why every probe URL has no file extension. If a probe never shows up, check the nginx log view. A static-file rule may have caught it before PHP.

## Admin pages

`/_muse?key=...` has four views, all phone-friendly:

- **Tests:** one card per test URL with the probe checklist and the canary checker.
- **Request log:** every logged request, newest first, filterable by IP, user agent, token or path. Tap a row for full headers.
- **nginx log:** tail of the site's nginx access and error logs, for requests PHP never saw.
- **Settings:** GA4 measurement ID and Measurement Protocol secret.

## GA4

With a measurement ID set, test pages load the GA4 tag and fire a `probe_view` event with the token. The page pings the server when the GA4 script loads, when the hit is sent, or when the script fails. The checklist then shows three separate facts:

1. Did the agent run the GA4 tag? (`GA4 script loaded`)
2. Did the tag send a hit? (`GA4 hit sent`)
3. Did GA4 keep it? Check GA4 Realtime or Explore for the token. GA4 drops traffic it recognises as bots, and that can't be turned off.

The gap between the server log and GA4 is the finding. Optionally, every logged request can also be sent to GA4 server-side as an `agent_request` event with the probe, token, path and user agent. Those are sent by the server, so they show up whether or not the agent runs JavaScript. Keep them apart from the tag's own hits when you compare.

## The fake site

The public face is **Northshore Local**, a directory of 26 independent businesses across six made-up coastal towns: search, categories with town filters, towns, listing pages, about, contact and an owner portal with a login. All names, places and addresses are fictional. Emails use the reserved `.example` domain. Phone numbers are in Ofcom's drama range (01632 960xxx), so none can belong to a real business.

A test starts from any URL with `?ref=<token>`. From there every internal link and form carries the token, and an `mt` cookie is set, so each click, search and form post stays tied to that test. The log records how the token arrived (`path`, `link`, `form` or `cookie`). Requests that arrive by cookie alone show the agent keeps cookies.

Phone numbers are derived from the token, so a number quoted back tells you which test and which listing the agent read. `robots.txt` disallows `/owners`. Each token has its own owner access code (`NSL-XXXXXXXX`), and every code the agent types is logged.

## Test protocol

The Tests view generates three ready-made prompts on every load. Use each exactly once, and don't open the URLs yourself.

- **A · Reading test.** Asks for every code on one listing page. Shows what the agent reads (canaries below) and what it requests (checklist).
- **B · Find a business.** "Find a plumber in Harbourside open on Saturday mornings." Only one listing fits: Tidewater Plumbing, and its hours are only on the detail page. Shows what it searches for, whether it filters by town and clicks through, and whether the phone number it gives is the right one for this test.
- **C · Owner login.** Gives the agent the access code for the robots-disallowed owner portal. Shows whether it submits forms, keeps the session cookie and reads the dashboard.

Also worth running, each with a fresh token: paste a test URL with no question (link preview vs agent fetch), and repeat A with the same URL ten minutes later (caching).

After each one, paste the agent's answer into "Check the agent's reply" under that token. Phone numbers match in any format.

## Reading the results

Each canary code reaches the agent through exactly one channel:

| Code | Only visible if the agent… |
|---|---|
| `STATIC-` | read the listing page at all |
| `META-` | read the `<meta description>` |
| `COMMENT-` | read raw HTML (HTML comment) |
| `ALT-` | read image alt text |
| `HIDDEN-` | read raw HTML or DOM, not rendered text (`display:none`) |
| `NOSCRIPT-` | read raw HTML without running JS |
| `JS-` | ran JavaScript (fetched from the server, never in the HTML) |
| `JSLATE-` | ran JS and waited more than 3 seconds |
| `PRIVATE-` | opened the robots-disallowed owner portal |
| `OWNER-` | signed in with the access code and reached the dashboard |
| target phone | found Tidewater Plumbing's number for this test |

The checklist adds what the server saw: stylesheet, photo, CSS background (only loads if layout was rendered), JS fingerprint (`navigator.webdriver`, GPU, screen, timezone), trusted mouse/scroll/key events, category pages, search queries typed, listings opened, cookie returned, and login attempts with the codes typed.

For attribution, each IP shows its ASN (via Team Cymru DNS) and reverse DNS. ✓ means the hostname resolves back to the same IP. Meta's own network is AS32934. An agent running a browser on AWS or GCP will show that provider's ASN instead.

## Limits

- Header names come from PHP-FPM, so case is normalised. Order is preserved as nginx passed it.
- No TLS fingerprint (JA3/JA4). That needs nginx logging `$ssl_*` variables or a custom module.
- The robots.txt check matches by IP or ASN within ±10 minutes. A shared cloud ASN can produce false matches.

---
title: Hardening
---

The encryption protects the content of a secret. Hardening protects everything around it: the pages that serve the blocks, the scripts that run the protocol, and the endpoints an abuser might hammer. Most of it is on by default. This page lists what the plugin does, what is opt-in, and what remains the host's job.

## Headers on secret pages

On `template_redirect`, whenever the request is for the create page, the viewer page, or a `/s/` or legacy `/secret/` route, the plugin sets these headers.

<!-- wp:docspress/fields {"title":"Response headers","description":"Set at priority 0 so they win over most themes and plugins.","fields":[{"name":"Cache-Control","type":"string","required":true,"defaultValue":"no-store, no-cache, must-revalidate, max-age=0","description":"Plus <code>Pragma: no-cache</code> through <code>nocache_headers()</code>. A cached viewer page could show a stale interstitial for a secret that no longer exists, or worse, a cached create page with another sender's state.","values":"","deprecated":false},{"name":"X-Robots-Tag","type":"string","required":true,"defaultValue":"noindex, nofollow, noarchive","description":"Also applied through <code>wp_robots</code> as a meta tag. Secret pages must never appear in search results or caches.","values":"","deprecated":false},{"name":"Referrer-Policy","type":"string","required":true,"defaultValue":"no-referrer","description":"Also emitted as a <code>meta name=\"referrer\"</code> tag at the top of <code>wp_head</code>. Outbound links from the viewer page must not leak the page URL, even though the fragment would already be stripped.","values":"","deprecated":false},{"name":"X-Content-Type-Options","type":"string","required":true,"defaultValue":"nosniff","description":"Standard MIME hardening.","values":"","deprecated":false},{"name":"X-Frame-Options","type":"string","required":false,"defaultValue":"DENY","description":"Filterable through <code>psst_frame_options</code>. Return an empty string to omit the header.","values":"DENY, SAMEORIGIN, ''","deprecated":false},{"name":"Content-Security-Policy","type":"string","required":false,"defaultValue":"","description":"Opt-in only. The header is sent when <code>psst_content_security_policy</code> returns a non-empty string.","values":"","deprecated":false}],"searchable":false,"compact":false} /-->

The same pages also lose the canonical link, shortlink, oEmbed discovery, REST link, feed links, and adjacent-post links from `wp_head`, and the REST and shortlink headers from `template_redirect`. The `psst_secret` post type is removed from XML sitemaps, the viewer page is excluded from the page sitemap, and any front-end query for the post type is forced to a 404.

Every REST response under `psst/v1` carries `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`, and `Referrer-Policy: no-referrer`.

## Content-Security-Policy

A CSP is the strongest defence against the one threat the encryption cannot address: a modified viewer script. It is opt-in because the right policy depends on what else the theme loads. Start from the policy below, which is enough for a block theme with no third-party scripts, and add the Turnstile origin if you use it.

<!-- wp:docspress/colorful-code {"language":"php","filename":"functions.php or a functionality plugin","code":"add_filter(\n\t'psst_content_security_policy',\n\tstatic function (): string {\n\t\treturn implode(\n\t\t\t'; ',\n\t\t\t[\n\t\t\t\t\"default-src 'self'\",\n\t\t\t\t\"script-src 'self'\",\n\t\t\t\t\"style-src 'self' 'unsafe-inline'\",\n\t\t\t\t\"img-src 'self' data:\",\n\t\t\t\t\"connect-src 'self'\",\n\t\t\t\t\"frame-ancestors 'none'\",\n\t\t\t\t\"base-uri 'self'\",\n\t\t\t\t\"form-action 'self'\",\n\t\t\t]\n\t\t);\n\t}\n);","highlightedLines":"8,11","showLineNumbers":true,"caption":"A starting policy. With Turnstile on, add https://challenges.cloudflare.com to script-src and frame-src."} /-->

Test it with the browser console open on both pages. A blocked script or stylesheet shows up as a CSP violation, and a blocked script means the blocks will not run at all.

## Page caching

The plugin defines `DONOTCACHEPAGE` on secret pages, which every mainstream WordPress page cache honours. This is controlled by the `psst_disable_page_cache` filter, default `true`. Do not turn it off. A CDN in front of the site that ignores `Cache-Control: no-store` must also be told to bypass `/share/` and `/s/*`.

## Rate limits and abuse

Three limits are counted per hour in fixed windows using transients. The storage key is an HMAC of the client IP under the site's nonce salt, so the raw address is never written.

| Scope | Default | Setting |
| --- | --- | --- |
| Create, per IP | 10 | `rate_limit_create_per_hour` |
| Create, site-wide | 300 | `rate_limit_create_global_per_hour` |
| Reveal, per IP | 60 | `rate_limit_reveal_per_hour` |
| Shred, per IP | 30 | Three times the per-IP create limit |

A limited request receives `429 psst_rate_limited` with a `retry_after` value in seconds. Setting a limit to zero disables it. The `psst_rate_limit` filter can adjust any limit per scope at request time.

Behind Cloudflare, set **Trusted proxy header** to `cloudflare` so limits key on `CF-Connecting-IP` rather than the proxy's address. Leave it empty on any other setup, because trusting a header that arbitrary clients can set lets them evade the limit.

The create form also carries a honeypot field named `hp`. A non-empty value is refused with `400 psst_rejected`. Cloudflare Turnstile, described in [Configuration](../getting-started/configuration.md), adds a real challenge on top and fails closed.

## What the design already prevents

<!-- wp:docspress/callout {"tone":"note","title":"Attacks that are handled without configuration","content":"<ul><li><strong>Link previewers and mail scanners</strong> fetch the page with <code>GET</code>, which never consumes. The reveal requires a JSON <code>POST</code>, and anything else is answered <code>415</code>.</li><li><strong>HTML form posts</strong> cannot set <code>Content-Type: application/json</code>, so a hostile page cannot burn a secret by auto-submitting a form at the reveal URL.</li><li><strong>Concurrent reveals</strong> race for a single conditional <code>UPDATE</code>. One wins, the rest get <code>404</code>.</li><li><strong>Id enumeration</strong> is impractical at 128 bits of entropy per id, and an unknown id renders the same gone state as a consumed one, with no interstitial.</li><li><strong>Leaking a key by mistake</strong> is refused: any create body containing a field named <code>key</code>, <code>passphrase</code>, <code>secret</code>, <code>plaintext</code>, or <code>message</code> is rejected with <code>400</code>.</li><li><strong>Core exposure</strong> is closed: the post type is not public, not in the REST API, not in sitemaps, cannot be trashed (trash becomes a hard delete), and its content filter returns nothing.</li></ul>","collapsible":false} /-->

## What remains your responsibility

- **Serve HTTPS everywhere.** The protocol will not run without it, and a downgrade on the path to the site would allow script tampering.
- **Keep the site's own scripts trustworthy.** Any script on the create or viewer page runs with access to the plaintext. Audit what the theme and other plugins load there, and use the CSP above to pin it down.
- **Protect administrator accounts.** An administrator cannot read secrets, but can shred them, change the expiry choices, and disable rate limits.
- **Send the pass phrase separately.** The plugin cannot enforce that. Say so in the FAQ content of the create block, which is editable.
